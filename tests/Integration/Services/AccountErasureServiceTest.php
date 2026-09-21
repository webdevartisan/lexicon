<?php

declare(strict_types=1);

use App\Interfaces\UploadServiceInterface;
use App\Models\AccountErasureRecordModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\ModerationCaseModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\AccountErasureService;
use App\Services\BlogDeletionService;
use App\Services\MediaUsageResolver;
use App\Services\PublicCacheInvalidator;
use Tests\Factories\BlogFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The upload service is a mock: storage/uploads/ is shared with development, so
 * the real one would delete real files.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->comments = new CommentModel($this->db);

    $this->db->execute(
        "INSERT INTO users (handle, email, password, display_name_cached, is_active)
         VALUES ('deleted-user', 'deleted-user@lexicon.invalid', '', 'Deleted user', 0)"
    );
    $this->deletedUserId = (int) $this->db->getConnection()->lastInsertId();

    $this->uploader = Mockery::mock(UploadServiceInterface::class)->shouldIgnoreMissing();
    $this->uploader->shouldReceive('blogUploadsBy')->andReturn([])->byDefault();
    $cacheInvalidator = Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing();

    $this->service = new AccountErasureService(
        $this->db,
        $this->users,
        new BlogDeletionService(
            $this->blogs,
            $this->posts,
            new BlogSettingsModel($this->db),
            new UserPreferencesModel($this->db),
            $this->uploader,
            $cacheInvalidator
        ),
        $this->uploader,
        new MediaUsageResolver($this->db),
        $cacheInvalidator,
        new AccountErasureRecordModel($this->db),
        'deleted-user'
    );

    $this->personId = UserFactory::new($this->users)
        ->withAttributes(['handle' => 'leaving-person', 'email' => 'leaving@example.test', 'display_name_cached' => 'Lea Ving'])
        ->create();
    $this->otherId = UserFactory::new($this->users)->create();

    $this->otherBlogId = BlogFactory::new($this->blogs)->published()->create($this->otherId);
    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->personId, 'blog_id' => $this->otherBlogId])
        ->published()
        ->create();
    $this->commentId = CommentFactory::new($this->comments)->create($this->postId, $this->otherId);
});

afterEach(function () {
    Mockery::close();
});

function countRows(\Framework\Database $db, string $sql, array $params): int
{
    return (int) $db->query($sql, $params)->fetchColumn();
}

test('erasing removes posts and comments, and empties a comment others replied to', function () {
    $repliedId = CommentFactory::new($this->comments)->create($this->postId, $this->personId);
    $plainId = CommentFactory::new($this->comments)->create($this->postId, $this->personId);

    $otherPostId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->otherId, 'blog_id' => $this->otherBlogId])
        ->published()
        ->create();
    $threadId = CommentFactory::new($this->comments)->create($otherPostId, $this->personId);
    $replyId = CommentFactory::new($this->comments)
        ->withAttributes(['parent_comment_id' => $threadId])
        ->create($otherPostId, $this->otherId);

    $soloBlogId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $this->uploader->shouldReceive('deleteBlogUploads')->once()->with($soloBlogId);

    $this->service->erase($this->personId);

    $thread = $this->comments->find($threadId);

    expect($this->blogs->find($soloBlogId))->toBeNull()
        ->and($this->posts->find($this->postId))->toBeNull()
        ->and($this->comments->find($plainId))->toBeNull()
        ->and($this->comments->find($repliedId))->toBeNull()
        ->and($thread['user_id'])->toBeNull()
        ->and($thread['content'])->toBe('')
        ->and($thread['deleted_at'])->not->toBeNull()
        ->and($this->comments->find($replyId))->not->toBeNull();
});

test('erasing removes a post even when someone else commented on it, taking their comment with it', function () {
    $othersCommentId = CommentFactory::new($this->comments)->create($this->postId, $this->otherId);

    $this->service->erase($this->personId);

    expect($this->posts->find($this->postId))->toBeNull()
        ->and($this->comments->find($othersCommentId))->toBeNull();
});

test('erasing deletes a solo blog even when someone else commented in it', function () {
    $soloBlogId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $soloPostId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->personId, 'blog_id' => $soloBlogId])
        ->published()
        ->create();
    $othersCommentId = CommentFactory::new($this->comments)->create($soloPostId, $this->otherId);
    $this->uploader->shouldReceive('deleteBlogUploads')->once()->with($soloBlogId);

    $this->service->erase($this->personId);

    expect($this->blogs->find($soloBlogId))->toBeNull()
        ->and($this->posts->find($soloPostId))->toBeNull()
        ->and($this->comments->find($othersCommentId))->toBeNull();
});

test('erasing deletes a solo blog even when it holds a departed collaborator\'s post', function () {
    $soloBlogId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $formerCollaboratorId = UserFactory::new($this->users)->create();
    $formerCollaboratorPostId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $formerCollaboratorId, 'blog_id' => $soloBlogId])
        ->published()
        ->create();
    $this->uploader->shouldReceive('deleteBlogUploads')->once()->with($soloBlogId);

    $this->service->erase($this->personId);

    expect($this->blogs->find($soloBlogId))->toBeNull()
        ->and($this->posts->find($formerCollaboratorPostId))->toBeNull();
});

test('the person is removed from places that are not theirs', function () {
    $this->db->execute('INSERT INTO blog_subscribers (blog_id, user_id, email, token) VALUES (?, NULL, ?, ?)', [$this->otherBlogId, 'leaving@example.test', bin2hex(random_bytes(16))]);
    $this->db->execute('INSERT INTO notifications (user_id, type, data) VALUES (?, ?, ?)', [$this->otherId, 'collaborator.removed', json_encode(['actor_handle' => 'leaving-person'])]);
    $this->db->execute('INSERT INTO notifications (user_id, type, data) VALUES (?, ?, ?)', [$this->otherId, 'comment.on_your_post', json_encode(['commenter_name' => 'Lea Ving', 'comment_excerpt' => 'my words'])]);
    $this->db->execute('INSERT INTO activity_log (user_id, action, resource_type, resource_id, details) VALUES (?, ?, ?, ?, ?)', [$this->otherId, 'user.created', 'user', $this->personId, json_encode(['handle' => 'leaving-person', 'email' => 'leaving@example.test'])]);
    $this->db->execute("INSERT INTO mail_queue (to_email, subject, body_html, body_text, status, tier) VALUES (?, 'Hi', '<p>Hi</p>', 'Hi', 'sent', 'standard')", ['leaving@example.test']);

    $this->service->erase($this->personId);

    $notifications = $this->db->query('SELECT data FROM notifications WHERE user_id = ? ORDER BY id', [$this->otherId])->fetchAll(\PDO::FETCH_COLUMN);
    $details = (string) $this->db->query('SELECT details FROM activity_log WHERE resource_id = ?', [$this->personId])->fetchColumn();

    expect(countRows($this->db, 'SELECT COUNT(*) FROM blog_subscribers WHERE email = ?', ['leaving@example.test']))->toBe(0)
        ->and(countRows($this->db, 'SELECT COUNT(*) FROM mail_queue WHERE to_email = ?', ['leaving@example.test']))->toBe(0)
        ->and(json_decode($notifications[0], true)['actor_handle'])->toBe('deleted-user')
        ->and(json_decode($notifications[1], true))->toBe(['commenter_name' => 'Deleted user', 'comment_excerpt' => ''])
        ->and($details)->not->toContain('leaving@example.test')
        ->and($details)->not->toContain('leaving-person');
});

test('an owner of a blog with collaborators cannot be erased', function () {
    $teamBlogId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $this->blogs->addUserToBlog($teamBlogId, $this->otherId, 'author', $this->personId);

    expect($this->service->blockers($this->personId)['shared_blogs'])->toHaveCount(1)
        ->and($this->service->canErase($this->personId))->toBeFalse()
        ->and(fn () => $this->service->erase($this->personId))
        ->toThrow(RuntimeException::class);

    expect($this->users->find($this->personId))->not->toBeNull();
});

test('the shared deleted-user account cannot be erased', function () {
    expect(fn () => $this->service->erase($this->deletedUserId))
        ->toThrow(InvalidArgumentException::class);
});

test('a reported post blocks erasure until the report is resolved', function () {
    (new ModerationCaseModel($this->db))->openFor('post', $this->postId, $this->personId, null, null, null);

    expect($this->service->blockers($this->personId)['reported_content'])->toBeTrue()
        ->and($this->service->canErase($this->personId))->toBeFalse()
        ->and(fn () => $this->service->erase($this->personId))
        ->toThrow(RuntimeException::class);

    expect($this->users->find($this->personId))->not->toBeNull();
});

test('a reported comment blocks erasure the same way', function () {
    $ownCommentId = CommentFactory::new($this->comments)->create($this->postId, $this->personId);
    (new ModerationCaseModel($this->db))->openFor('comment', $ownCommentId, $this->personId, null, null, null);

    expect($this->service->canErase($this->personId))->toBeFalse();
});

test('a resolved case, or only the blog badge, no longer blocks erasure', function () {
    $caseId = (new ModerationCaseModel($this->db))->openFor('post', $this->postId, $this->personId, null, null, null);
    $this->db->execute("UPDATE moderation_cases SET status = 'resolved', open_key = NULL WHERE id = ?", [$caseId]);
    $this->db->execute('UPDATE posts SET reports_count = 3 WHERE id = ?', [$this->postId]);

    expect($this->service->blockers($this->personId)['reported_content'])->toBeFalse();
});

test('erasing keeps a private record of who the account was', function () {
    $ownCommentId = CommentFactory::new($this->comments)->create($this->postId, $this->personId);

    $this->service->erase($this->personId, 42, '203.0.113.5');

    $record = $this->db->query(
        'SELECT * FROM account_erasure_records WHERE original_user_id = ?',
        [$this->personId]
    )->fetch(\PDO::FETCH_ASSOC);

    expect($record)->not->toBeFalse()
        ->and($record['handle'])->toBe('leaving-person')
        ->and($record['email'])->toBe('leaving@example.test')
        ->and(json_decode($record['post_ids'], true))->toBe([$this->postId])
        ->and(json_decode($record['comment_ids'], true))->toBe([$ownCommentId])
        ->and((int) $record['erased_by'])->toBe(42)
        ->and($record['erased_by_ip'])->toBe('203.0.113.5');
});

test('the record covers what the account wrote in its own blog too', function () {
    $soloBlogId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $soloPostId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->personId, 'blog_id' => $soloBlogId])
        ->published()
        ->create();
    $soloCommentId = CommentFactory::new($this->comments)->create($soloPostId, $this->personId);
    $this->uploader->shouldReceive('deleteBlogUploads')->once()->with($soloBlogId);

    $this->service->erase($this->personId);

    $record = $this->db->query(
        'SELECT post_ids, comment_ids FROM account_erasure_records WHERE original_user_id = ?',
        [$this->personId]
    )->fetch(\PDO::FETCH_ASSOC);

    expect(json_decode($record['post_ids'], true))->toContain($soloPostId)
        ->and(json_decode($record['comment_ids'], true))->toContain($soloCommentId);
});
