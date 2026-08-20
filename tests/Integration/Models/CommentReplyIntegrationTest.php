<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\CommentRemovalService;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for threaded comment replies and the per-blog
 * replies_auto_publish setting, against a real database.
 */
beforeEach(function () {
    $this->commentModel = new CommentModel($this->db);
    $this->settingsModel = new BlogSettingsModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->postModel = new PostModel($this->db);

    $this->userId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);
    $this->postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $this->blogId])
        ->published()
        ->create();

    $this->parentId = (int) $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'content' => 'top level comment',
        'status' => 'approved',
    ]);
});

it('threads approved replies under their parent', function () {
    $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'parent_comment_id' => $this->parentId,
        'content' => 'a reply',
        'status' => 'approved',
    ]);

    $threads = $this->commentModel->forPostThreaded($this->postId);

    expect($threads)->toHaveCount(1)
        ->and($threads[0]['id'])->toBe($this->parentId)
        ->and($threads[0]['replies'])->toHaveCount(1)
        ->and($threads[0]['replies'][0]['content'])->toBe('a reply');
});

it('keeps pending replies out of the public thread', function () {
    $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'parent_comment_id' => $this->parentId,
        'content' => 'held for moderation',
        'status' => 'pending',
    ]);

    $threads = $this->commentModel->forPostThreaded($this->postId);

    expect($threads[0]['replies'])->toBeEmpty();
});

it('rejects reply targets from another post', function () {
    $otherPost = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $this->blogId])
        ->published()
        ->create();

    expect($this->commentModel->findApprovedParent($this->parentId, $otherPost))->toBeNull()
        ->and($this->commentModel->findApprovedParent($this->parentId, $this->postId))->toBeArray();
});

it('reads the replies_auto_publish blog setting with a default of on', function () {
    // Factory blogs may not create a settings row; default must be true
    expect($this->settingsModel->repliesAutoPublish($this->blogId))->toBeTrue();

    $this->settingsModel->createDefaultForBlog($this->blogId, ['replies_auto_publish' => 0]);

    expect($this->settingsModel->repliesAutoPublish($this->blogId))->toBeFalse();

    $this->settingsModel->updateForBlog($this->blogId, ['replies_auto_publish' => 1]);

    expect($this->settingsModel->repliesAutoPublish($this->blogId))->toBeTrue();
});

it('treats a reply held at the depth cap as an answer to what it aimed at', function () {
    $target = (int) $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'parent_comment_id' => $this->parentId,
        'content' => 'answered from past the cap',
        'status' => 'approved',
    ]);

    // What the depth cap produces: hung under the grandparent, aimed at $target.
    $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'parent_comment_id' => $this->parentId,
        'reply_to_comment_id' => $target,
        'content' => 'the clamped reply',
        'status' => 'approved',
    ]);

    // Nothing hangs off $target structurally, but removing it outright would
    // leave that reply pointing at a comment nobody can read any more.
    expect($this->commentModel->hasReplies($target))->toBeTrue();
});

it('keeps a removed comment in the thread while replies still hang off it', function () {
    $replyId = (int) $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'parent_comment_id' => $this->parentId,
        'content' => 'a reply that must survive',
        'status' => 'approved',
    ]);

    (new CommentRemovalService($this->commentModel))
        ->remove($this->parentId, CommentRemovalService::BY_AUTHOR);

    $threads = $this->commentModel->forPostThreaded($this->postId);

    expect($threads)->toHaveCount(1)
        ->and($threads[0]['id'])->toBe($this->parentId)
        ->and($threads[0]['content'])->toBe('')
        ->and($threads[0]['deleted_by'])->toBe('author')
        ->and($threads[0]['deleted_at'])->not->toBeNull()
        ->and($threads[0]['replies'])->toHaveCount(1)
        ->and($threads[0]['replies'][0]['id'])->toBe($replyId);
});

it('cascades reply deletion when the parent comment is removed', function () {
    $replyId = (int) $this->commentModel->insert([
        'post_id' => $this->postId,
        'user_id' => $this->userId,
        'parent_comment_id' => $this->parentId,
        'content' => 'doomed reply',
        'status' => 'approved',
    ]);

    $this->db->query('DELETE FROM comments WHERE id = ?', [$this->parentId]);

    $remaining = $this->db->query('SELECT COUNT(*) FROM comments WHERE id = ?', [$replyId])->fetchColumn();

    expect((int) $remaining)->toBe(0);
});
