<?php

declare(strict_types=1);

use App\Models\PostModel;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\Models\CommentModel;
use App\Models\TagModel;
use Tests\Factories\UserFactory;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\TagFactory;

/**
 * Integration tests for PostModel blog deletion operations.
 * 
 * Tests cascade deletion methods used when removing a blog.
 * Part 1 of 5: countCommentsByBlogId, deleteCommentsByBlogId, deletePostTagsByBlogId.
 * 
 * @property \Framework\Database $db
 */

beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->commentModel = new CommentModel($this->db);
    $this->tagModel = new TagModel($this->db);
    
    expect($this->db->getConnection())->toHaveActiveTransaction();
});

// ============================================================================
// COUNT COMMENTS BY BLOG ID
// ============================================================================

/**
 * Test counting all comments across blog posts.
 * 
 * Verifies countCommentsByBlogId aggregates comments from all posts in a blog.
 */
it('counts comments across all posts in a blog', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $post1Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();
    
    $post2Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();
    
    CommentFactory::new($this->commentModel)
        ->withAttributes(['post_id' => $post1Id, 'user_id' => $userId])
        ->count(2);
    
    CommentFactory::new($this->commentModel)
        ->withAttributes(['post_id' => $post2Id, 'user_id' => $userId])
        ->create($post2Id, $userId);
    
    $count = $this->postModel->countCommentsByBlogId($blogId);
    
    expect($count)->toBe(3);
});

/**
 * Test that count returns zero when blog has no comments.
 */
it('returns zero when blog has no comments', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();
    
    $count = $this->postModel->countCommentsByBlogId($blogId);
    
    expect($count)->toBe(0);
});

/**
 * Test that count excludes comments from other blogs.
 * 
 * Verifies proper blog isolation in comment counting.
 */
it('excludes comments from other blogs when counting', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $blog1Id = BlogFactory::new($this->blogModel)->create($userId);
    $blog2Id = BlogFactory::new($this->blogModel)->create($userId);
    
    $post1Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog1Id])
        ->create();
    
    $post2Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog2Id])
        ->create();
    
    CommentFactory::new($this->commentModel)->create($post1Id, $userId);
    CommentFactory::new($this->commentModel)->create($post2Id, $userId);
    
    $count = $this->postModel->countCommentsByBlogId($blog1Id);
    
    expect($count)->toBe(1);
});

// ============================================================================
// DELETE COMMENTS BY BLOG ID
// ============================================================================

/**
 * Test deleting all comments in a blog.
 * 
 * Verifies deleteCommentsByBlogId performs cascade deletion and returns count.
 */
it('deletes all comments across blog posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();
    
    CommentFactory::new($this->commentModel)
        ->withAttributes(['post_id' => $postId, 'user_id' => $userId])
        ->count(2);
    
    $deleted = $this->postModel->deleteCommentsByBlogId($blogId);
    
    expect($deleted)->toBe(2);
    
    $count = $this->postModel->countCommentsByBlogId($blogId);
    expect($count)->toBe(0);
});

/**
 * Test that delete returns zero when no comments exist.
 */
it('returns zero when deleting comments from blog with none', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $deleted = $this->postModel->deleteCommentsByBlogId($blogId);
    
    expect($deleted)->toBe(0);
});

/**
 * Test that delete only affects specified blog.
 * 
 * Verifies cascade deletion respects blog boundaries.
 */
it('only deletes comments from specified blog', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $blog1Id = BlogFactory::new($this->blogModel)->create($userId);
    $blog2Id = BlogFactory::new($this->blogModel)->create($userId);
    
    $post1Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog1Id])
        ->create();
    
    $post2Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog2Id])
        ->create();
    
    CommentFactory::new($this->commentModel)->create($post1Id, $userId);
    CommentFactory::new($this->commentModel)->create($post2Id, $userId);
    
    $deleted = $this->postModel->deleteCommentsByBlogId($blog1Id);
    
    expect($deleted)->toBe(1);
    
    $count = $this->postModel->countCommentsByBlogId($blog2Id);
    expect($count)->toBe(1);
});

// ============================================================================
// DELETE POST TAGS BY BLOG ID
// ============================================================================

/**
 * Test deleting all post-tag relationships in a blog.
 * 
 * Verifies deletePostTagsByBlogId removes pivot table entries.
 */
it('deletes all post-tag relationships for blog', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();
    
    $tag1Id = TagFactory::new($this->tagModel)->create();
    $tag2Id = TagFactory::new($this->tagModel)->create();
    
    $this->tagModel->attachToPost($postId, $tag1Id);
    $this->tagModel->attachToPost($postId, $tag2Id);
    
    $deleted = $this->postModel->deletePostTagsByBlogId($blogId);
    
    expect($deleted)->toBe(2);
    
    $tags = $this->postModel->tags($postId);
    expect($tags)->toHaveCount(0);
});

/**
 * Test that delete returns zero when no tags exist.
 */
it('returns zero when deleting tags from blog with none', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $deleted = $this->postModel->deletePostTagsByBlogId($blogId);
    
    expect($deleted)->toBe(0);
});

/**
 * Test that delete only affects specified blog.
 * 
 * Verifies tag deletion respects blog boundaries.
 */
it('only deletes tags from specified blog posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $blog1Id = BlogFactory::new($this->blogModel)->create($userId);
    $blog2Id = BlogFactory::new($this->blogModel)->create($userId);
    
    $post1Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog1Id])
        ->create();
    
    $post2Id = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog2Id])
        ->create();
    
    $tagId = TagFactory::new($this->tagModel)->create();
    
    $this->tagModel->attachToPost($post1Id, $tagId);
    $this->tagModel->attachToPost($post2Id, $tagId);
    
    $deleted = $this->postModel->deletePostTagsByBlogId($blog1Id);
    
    expect($deleted)->toBe(1);
    
    $tags = $this->postModel->tags($post2Id);
    expect($tags)->toHaveCount(1);
});
