<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\PublishedPostModel;
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
    $this->publishedPosts = new PublishedPostModel($this->db);
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

// ============================================================================
// STATUS UPDATES
// ============================================================================

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

/**
 * Test that updateStatus refuses a moderated post.
 *
 * Reversing a moderation hide belongs to its report case; a status write
 * that bypassed it here would let a hidden post slip back into view.
 */
it('does not change the status of a moderated post', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'moderated'])
        ->create();

    $result = $this->postModel->updateStatus($postId, 'published');

    expect($result)->toBeFalse();

    $post = $this->postModel->find($postId);
    expect($post['status'])->toBe('moderated');
});

/**
 * Test updating post visibility.
 *
 * Verifies updateVisibility changes visibility independently of status.
 */
it('updates post visibility to any valid value', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'published', 'visibility' => 'public'])
        ->create();

    $result = $this->postModel->updateVisibility($postId, 'unlisted');

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['visibility'])->toBe('unlisted')
        ->and($post['status'])->toBe('published');
});

/**
 * Test that updateVisibility does not touch status, even on a moderated post.
 *
 * Visibility does not gate public access on its own, status does; there is
 * nothing for this to bypass, so unlike updateStatus it needs no guard.
 */
it('updates visibility on a moderated post without lifting the moderation status', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'moderated', 'visibility' => 'public'])
        ->create();

    $result = $this->postModel->updateVisibility($postId, 'private');

    expect($result)->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['visibility'])->toBe('private')
        ->and($post['status'])->toBe('moderated');
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

    $result = $this->publishedPosts->findPublishedByBlogIdWithPagination($blogId, 1, 5);

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

it('refuses a guarded transition once the post has left the expected state', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'pending', 'workflow_state' => 'in_review'])
        ->create();

    $first = $this->postModel->transitionWorkflow($postId, 'approved', $userId, ['in_review']);
    $second = $this->postModel->transitionWorkflow($postId, 'needs_changes', $userId, ['in_review']);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($this->postModel->find($postId)['workflow_state'])->toBe('approved');
});

it('enters review with status and review state together, once', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'draft', 'workflow_state' => 'draft'])
        ->create();

    expect($this->postModel->enterReview($postId, $userId))->toBeTrue()
        ->and($this->postModel->enterReview($postId, $userId))->toBeFalse();

    $post = $this->postModel->find($postId);
    expect($post['status'])->toBe('pending')
        ->and($post['workflow_state'])->toBe('in_review');
});

it('never puts a published post into review', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'published', 'workflow_state' => 'draft'])
        ->create();

    expect($this->postModel->enterReview($postId, $userId))->toBeFalse()
        ->and($this->postModel->find($postId)['status'])->toBe('published');
});

it('takes a pending post out of review back to draft', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'pending', 'workflow_state' => 'approved'])
        ->create();

    expect($this->postModel->leaveReview($postId, $userId))->toBeTrue();

    $post = $this->postModel->find($postId);
    expect($post['status'])->toBe('draft')
        ->and($post['workflow_state'])->toBe('draft');
});

it('clears the review state whenever a post stops being pending', function (string $via) {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'pending', 'workflow_state' => 'in_review'])
        ->create();

    $via === 'updateStatus'
        ? $this->postModel->updateStatus($postId, 'published')
        : $this->postModel->update($postId, ['status' => 'published']);

    expect($this->postModel->find($postId)['workflow_state'])->toBe('draft');
})->with(['updateStatus', 'update']);

it('knows whether a reviewer has taken on a submission', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $reviewerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'pending', 'workflow_state' => 'in_review'])
        ->create();

    $before = $this->postModel->findResource($postId)->reviewStarted();
    $this->db->query('INSERT INTO post_reviewers (post_id, reviewer_id, assigned_by) VALUES (?, ?, ?)', [$postId, $reviewerId, $reviewerId]);
    $after = $this->postModel->findResource($postId)->reviewStarted();

    expect($before)->toBeFalse()->and($after)->toBeTrue();
});

it('keeps the review state while a post stays pending', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    $postId = PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'pending', 'workflow_state' => 'needs_changes'])
        ->create();

    $this->postModel->updateStatus($postId, 'pending');
    $this->postModel->update($postId, ['title' => 'A better title']);

    expect($this->postModel->find($postId)['workflow_state'])->toBe('needs_changes');
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
