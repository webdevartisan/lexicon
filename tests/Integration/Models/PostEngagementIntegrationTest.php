<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostBookmarkModel;
use App\Models\PostLikeModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for like and bookmark toggling against a real database.
 */
beforeEach(function () {
    $this->likeModel = new PostLikeModel($this->db);
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

it('toggles a like on and off', function () {
    expect($this->likeModel->userLikes($this->userId, $this->postId))->toBeFalse();

    expect($this->likeModel->toggle($this->userId, $this->postId))->toBeTrue()
        ->and($this->likeModel->userLikes($this->userId, $this->postId))->toBeTrue()
        ->and($this->likeModel->countByPost($this->postId))->toBe(1);

    expect($this->likeModel->toggle($this->userId, $this->postId))->toBeFalse()
        ->and($this->likeModel->countByPost($this->postId))->toBe(0);
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
