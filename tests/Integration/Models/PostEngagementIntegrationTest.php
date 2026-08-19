<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostBookmarkModel;
use App\Models\PostVoteModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for post voting and bookmarking against a real database.
 */
beforeEach(function () {
    $this->voteModel = new PostVoteModel($this->db);
    $this->bookmarkModel = new PostBookmarkModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->postModel = new PostModel($this->db);

    $this->userId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->published()->create($this->userId);
    $this->postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $this->blogId])
        ->published()
        ->create();
});

it('casts, flips and clears a vote', function () {
    expect($this->voteModel->userVote($this->userId, $this->postId))->toBe(0);

    expect($this->voteModel->apply($this->userId, $this->postId, PostVoteModel::UP))
        ->toBe(['up' => 1, 'down' => 0, 'mine' => 1]);

    // The other direction replaces the row rather than adding a second one.
    expect($this->voteModel->apply($this->userId, $this->postId, PostVoteModel::DOWN))
        ->toBe(['up' => 0, 'down' => 1, 'mine' => -1]);

    expect($this->voteModel->apply($this->userId, $this->postId, PostVoteModel::DOWN))
        ->toBe(['up' => 0, 'down' => 0, 'mine' => 0])
        ->and($this->voteModel->userVote($this->userId, $this->postId))->toBe(0);
});

it('keeps down votes out of the liked library', function () {
    $this->voteModel->apply($this->userId, $this->postId, PostVoteModel::DOWN);

    expect($this->voteModel->likedPosts($this->userId))->toBeEmpty();

    $this->voteModel->apply($this->userId, $this->postId, PostVoteModel::UP);

    expect($this->voteModel->likedPosts($this->userId))->toHaveCount(1);
});

it('toggles a bookmark on and off', function () {
    expect($this->bookmarkModel->toggle($this->userId, $this->postId))->toBeTrue()
        ->and($this->bookmarkModel->userBookmarks($this->userId, $this->postId))->toBeTrue()
        ->and($this->bookmarkModel->countByPost($this->postId))->toBe(1);

    expect($this->bookmarkModel->toggle($this->userId, $this->postId))->toBeFalse()
        ->and($this->bookmarkModel->countByPost($this->postId))->toBe(0);
});

it('keeps likes and bookmarks independent per user', function () {
    $secondUser = UserFactory::new($this->userModel)->create();

    $this->likeModel->toggle($this->userId, $this->postId);
    $this->likeModel->toggle($secondUser, $this->postId);
    $this->bookmarkModel->toggle($secondUser, $this->postId);

    expect($this->likeModel->countByPost($this->postId))->toBe(2)
        ->and($this->bookmarkModel->countByPost($this->postId))->toBe(1)
        ->and($this->bookmarkModel->userBookmarks($this->userId, $this->postId))->toBeFalse();
});

it('lists bookmarked posts newest first', function () {
    $secondPost = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $this->userId, 'blog_id' => $this->blogId])
        ->published()
        ->create();

    $this->bookmarkModel->toggle($this->userId, $this->postId);
    $this->bookmarkModel->toggle($this->userId, $secondPost);

    $saved = $this->bookmarkModel->bookmarkedPosts($this->userId);

    expect($saved)->toHaveCount(2)
        ->and(array_column($saved, 'id'))->toContain($this->postId, $secondPost);
});

it('cascades likes and bookmarks when the post is removed', function () {
    $this->likeModel->toggle($this->userId, $this->postId);
    $this->bookmarkModel->toggle($this->userId, $this->postId);

    $this->db->query('DELETE FROM posts WHERE id = ?', [$this->postId]);

    expect($this->likeModel->countByPost($this->postId))->toBe(0)
        ->and($this->bookmarkModel->countByPost($this->postId))->toBe(0);
});
