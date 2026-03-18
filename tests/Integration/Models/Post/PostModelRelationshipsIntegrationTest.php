<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\CategoryFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\TagFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for PostModel relationship methods.
 *
 * Tests relationships between posts and related entities.
 * Part 4 of 5: Author, category, tags, and comments relationships.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->categoryModel = new CategoryModel($this->db);
    $this->tagModel = new TagModel($this->db);
    $this->commentModel = new CommentModel($this->db);

});

// ============================================================================
// AUTHOR RELATIONSHIP
// ============================================================================

/**
 * Test retrieving post author.
 *
 * Verifies author() returns user data for valid user ID.
 */
it('returns author data for valid user ID', function () {
    $userId = UserFactory::new($this->userModel)
        ->withAttributes([
            'username' => 'authoruser',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ])
        ->create();

    $author = $this->postModel->author($userId);

    expect($author)->toBeArray()
        ->and($author['username'])->toBe('authoruser')
        ->and($author['first_name'])->toBe('John')
        ->and($author['last_name'])->toBe('Doe');
});

/**
 * Test that author returns null for non-existent user.
 */
it('returns null for non-existent author', function () {
    $author = $this->postModel->author(999999);

    expect($author)->toBeNull();
});

// ============================================================================
// CATEGORY RELATIONSHIP
// ============================================================================

/**
 * Test retrieving post category.
 *
 * Verifies category() returns category data for valid category ID.
 */
it('returns category data for valid category ID', function () {
    $categoryId = CategoryFactory::new($this->categoryModel)
        ->withAttributes([
            'name' => 'Technology',
            'slug' => 'technology-'.faker()->unique()->numberBetween(1000, 9999),
        ])
        ->create();

    $category = $this->postModel->category($categoryId);

    expect($category)->toBeArray()
        ->and($category['name'])->toBe('Technology');
});

/**
 * Test that category returns null for null category ID.
 */
it('returns null for null category ID', function () {
    $category = $this->postModel->category(null);

    expect($category)->toBeNull();
});

/**
 * Test that category returns null for non-existent category.
 */
it('returns null for non-existent category', function () {
    $category = $this->postModel->category(999999);

    expect($category)->toBeNull();
});

// ============================================================================
// TAGS RELATIONSHIP
// ============================================================================

/**
 * Test retrieving post tags.
 *
 * Verifies tags() returns all tags associated with a post.
 */
it('returns all tags for a post', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    $tag1Id = TagFactory::new($this->tagModel)
        ->withAttributes(['name' => 'PHP'])
        ->create();

    $tag2Id = TagFactory::new($this->tagModel)
        ->withAttributes(['name' => 'Testing'])
        ->create();

    $this->tagModel->attachToPost($postId, $tag1Id);
    $this->tagModel->attachToPost($postId, $tag2Id);

    $tags = $this->postModel->tags($postId);

    expect($tags)->toBeArray()
        ->and($tags)->toHaveCount(2)
        ->and($tags[0]['name'])->toBeIn(['PHP', 'Testing'])
        ->and($tags[1]['name'])->toBeIn(['PHP', 'Testing']);
});

/**
 * Test that tags returns empty array when post has no tags.
 */
it('returns empty array when post has no tags', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    $tags = $this->postModel->tags($postId);

    expect($tags)->toBeArray()
        ->and($tags)->toHaveCount(0);
});

// ============================================================================
// COMMENTS RELATIONSHIP
// ============================================================================

/**
 * Test retrieving post comments.
 *
 * Verifies comments() returns all comments for a post.
 */
it('returns all comments for a post', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    CommentFactory::new($this->commentModel)
        ->withAttributes(['post_id' => $postId, 'user_id' => $userId])
        ->count(2);

    $comments = $this->postModel->comments($postId);

    expect($comments)->toBeArray()
        ->and($comments)->toHaveCount(2);
});

/**
 * Test that comments returns empty array when post has no comments.
 */
it('returns empty array when post has no comments', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    $comments = $this->postModel->comments($postId);

    expect($comments)->toBeArray()
        ->and($comments)->toHaveCount(0);
});
