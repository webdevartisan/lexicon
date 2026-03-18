<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for PostModel CRUD and core operations.
 *
 * Tests basic post operations, status management, pagination, and workflow.
 * Part 2 of 5: Core CRUD, finding, status updates, counting, pagination, workflow.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);

});

// ============================================================================
// CRUD OPERATIONS
// ============================================================================

/**
 * Test creating a new post.
 *
 * Verifies insert operation returns valid ID and persists data correctly.
 */
it('creates a new post and returns ID', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
        ])
        ->create();

    expect($postId)->toBeInt()
        ->and($postId)->toBeGreaterThan(0);

    $post = $this->postModel->find($postId);
    expect($post['title'])->toBeString();
});

/**
 * Test updating an existing post.
 *
 * Verifies update operation persists changes to database.
 */
it('updates existing post data', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    $result = $this->postModel->update($postId, [
        'title' => 'Updated Title',
        'content' => 'Updated content',
    ]);

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['title'])->toBe('Updated Title')
        ->and($post['content'])->toBe('Updated content');
});

/**
 * Test deleting a post.
 *
 * Verifies delete operation removes post from database.
 */
it('deletes post from database', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    $result = $this->postModel->delete($postId);

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post)->toBeNull();
});

// ============================================================================
// FINDING POSTS
// ============================================================================

/**
 * Test finding post by slug.
 *
 * Verifies findBySlug returns correct post data.
 */
it('finds post by slug', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $slug = 'unique-slug-'.faker()->unique()->numberBetween(1000, 9999);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'slug' => $slug,
        ])
        ->create();

    $post = $this->postModel->findBySlug($slug);

    expect($post)->toBeArray()
        ->and($post['slug'])->toBe($slug);
});

/**
 * Test that findBySlug returns null for non-existent slug.
 */
it('returns null when slug does not exist', function () {
    $post = $this->postModel->findBySlug('non-existent-slug-'.faker()->uuid());

    expect($post)->toBeNull();
});

/**
 * Test finding posts by author.
 *
 * Verifies findByAuthorId returns all author posts.
 */
it('finds all posts by author', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->count(2);

    $posts = $this->postModel->findByAuthorId($userId);

    expect($posts)->toBeArray()
        ->and($posts)->toHaveCount(2);
});

/**
 * Test retrieving only published posts.
 *
 * Verifies published() excludes draft and archived posts.
 */
it('returns only published posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'draft',
        ])
        ->create();

    $posts = $this->postModel->published();

    expect($posts)->toBeArray()
        ->and($posts)->toHaveCount(1)
        ->and($posts[0]['status'])->toBe('published');
});

// ============================================================================
// STATUS UPDATES
// ============================================================================

/**
 * Test publishing a draft post.
 *
 * Verifies publishPost changes status to published.
 */
it('publishes draft post', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'draft',
        ])
        ->create();

    $result = $this->postModel->publishPost($postId);

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['status'])->toBe('published');
});

/**
 * Test unpublishing a published post.
 *
 * Verifies unpublishPost changes status to draft.
 */
it('unpublishes published post', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
        ])
        ->create();

    $result = $this->postModel->unpublishPost($postId);

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['status'])->toBe('draft');
});

/**
 * Test updating post status to arbitrary value.
 *
 * Verifies updateStatus changes post status.
 */
it('updates post status to any valid value', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'draft',
        ])
        ->create();

    $result = $this->postModel->updateStatus($postId, 'archived');

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['status'])->toBe('archived');
});

// ============================================================================
// COUNTING & BLOG OPERATIONS
// ============================================================================

/**
 * Test counting posts by blog.
 *
 * Verifies countByBlogId returns accurate count.
 */
it('counts posts by blog ID', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->count(2);

    $count = $this->postModel->countByBlogId($blogId);

    expect($count)->toBe(2);
});

/**
 * Test retrieving all posts for a blog.
 *
 * Verifies getAllByBlogId returns all posts regardless of status.
 */
it('gets all posts by blog ID', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->count(2);

    $posts = $this->postModel->getAllByBlogId($blogId);

    expect($posts)->toBeArray()
        ->and($posts)->toHaveCount(2);
});

/**
 * Test deleting all posts in a blog.
 *
 * Verifies deleteByBlogId performs cascade deletion and returns count.
 */
it('deletes all posts by blog ID', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->count(2);

    $deleted = $this->postModel->deleteByBlogId($blogId);

    expect($deleted)->toBe(2);

    $count = $this->postModel->countByBlogId($blogId);
    expect($count)->toBe(0);
});

// ============================================================================
// PAGINATION
// ============================================================================

/**
 * Test paginated retrieval of published posts.
 *
 * Verifies findPublishedByBlogIdWithPagination returns correct page data.
 */
it('returns paginated published posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    for ($i = 1; $i <= 10; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
            ])
            ->create();
    }

    $result = $this->postModel->findPublishedByBlogIdWithPagination($blogId, 1, 5);

    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(5)
        ->and($result['totalPages'])->toBe(2)
        ->and($result['currentPage'])->toBe(1)
        ->and($result['totalPosts'])->toBe(10);
});

// ============================================================================
// WORKFLOW
// ============================================================================

/**
 * Test transitioning post workflow state.
 *
 * Verifies transitionWorkflow changes state and records metadata.
 */
it('transitions post workflow state', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'workflow_state' => 'draft',
        ])
        ->create();

    $result = $this->postModel->transitionWorkflow($postId, 'in_review', $userId);

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['workflow_state'])->toBe('in_review');
});

/**
 * Test that invalid workflow state throws exception.
 *
 * Verifies transitionWorkflow validates state against whitelist.
 */
it('throws exception for invalid workflow state', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    expect(fn () => $this->postModel->transitionWorkflow($postId, 'invalid_state', $userId))
        ->toThrow(InvalidArgumentException::class);
});

// ============================================================================
// RESOURCE METHODS
// ============================================================================

/**
 * Test finding post as Resource for authorization.
 *
 * Verifies findResource returns PostResource with blog loaded.
 */
it('finds post as resource with blog loaded', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId])
        ->create();

    $resource = $this->postModel->findResource($postId);

    expect($resource)->toBeInstanceOf(\App\Resources\PostResource::class);
});

/**
 * Test that findResource returns false for non-existent post.
 */
it('returns false when finding non-existent post as resource', function () {
    $resource = $this->postModel->findResource(999999);

    expect($resource)->toBeFalse();
});
