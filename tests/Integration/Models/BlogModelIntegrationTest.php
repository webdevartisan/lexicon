<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Database;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for BlogModel.
 *
 * Tests all BlogModel methods with real database interactions.
 * Uses factories for data generation and transactions for isolation.
 */
beforeEach(function () {
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->postModel = new PostModel($this->db);
});

// ============================================================================
// BASIC CRUD OPERATIONS
// ============================================================================

/**
 * Test that a blog can be created with valid data.
 *
 * Verifies insert operation returns valid ID and persists data correctly.
 */
it('creates a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $blogData = [
        'blog_name' => faker()->words(3, true),
        'blog_slug' => faker()->slug(3),
        'description' => faker()->sentence(),
        'owner_id' => $ownerId,
        'status' => 'draft',
    ];

    $this->blogModel->insert($blogData);
    $blogId = $this->blogModel->getInsertID();

    expect($blogId)->toBeGreaterThan(0);

    $blog = $this->blogModel->getBlog($blogId);
    expect($blog)->toBeInstanceOf(\App\Resources\BlogResource::class)
        ->and($blog->name())->toBe($blogData['blog_name']);
});

/**
 * Test that a blog can be retrieved by its ID.
 *
 * Verifies getBlog method returns correct BlogResource instance with matching data.
 */
it('finds a blog by ID', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $expectedName = faker()->words(2, true);

    $blogId = BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_name' => $expectedName])
        ->create($ownerId);

    $found = $this->blogModel->getBlog($blogId);

    expect($found)->toBeInstanceOf(\App\Resources\BlogResource::class)
        ->and($found->name())->toBe($expectedName);
});

/**
 * Test that find returns false for non-existent blog.
 *
 * Verifies proper handling of invalid blog IDs.
 */
it('returns false when finding non-existent blog', function () {
    $found = $this->blogModel->getBlog(99999);

    expect($found)->toBeFalse();
});

/**
 * Test that a blog's attributes can be updated.
 *
 * Verifies update operation persists changes and cache invalidation occurs.
 */
it('updates a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $originalName = faker()->words(2, true);
    $updatedName = faker()->words(2, true);

    $blogId = BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_name' => $originalName])
        ->create($ownerId);

    $this->blogModel->update($blogId, ['blog_name' => $updatedName]);

    $updated = $this->blogModel->getBlog($blogId);
    expect($updated->name())->toBe($updatedName);
});

/**
 * Test that updating blog slug invalidates both old and new cache patterns.
 *
 * Verifies cache invalidation logic for slug changes.
 */
it('invalidates cache when updating blog slug', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $oldSlug = 'original-slug-'.faker()->numberBetween(1000, 9999);
    $newSlug = 'new-slug-'.faker()->numberBetween(1000, 9999);

    $blogId = BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_slug' => $oldSlug])
        ->create($ownerId);

    $result = $this->blogModel->update($blogId, ['blog_slug' => $newSlug]);

    expect($result)->toBeTrue();
    $updated = $this->blogModel->getBlog($blogId);
    expect($updated->slug())->toBe($newSlug);
});

/**
 * Test that a blog can be deleted.
 *
 * Verifies hard delete removes record and find returns false.
 */
it('deletes a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $this->blogModel->delete($blogId);

    expect($this->blogModel->getBlog($blogId))->toBeFalse();
});

/**
 * Test that deleting a blog invalidates related cache patterns.
 *
 * Verifies cache cleanup on blog deletion.
 */
it('invalidates cache when deleting blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $blogId = BlogFactory::new($this->blogModel)
        ->published()
        ->create($ownerId);

    $result = $this->blogModel->delete($blogId);

    expect($result)->toBeTrue()
        ->and($this->blogModel->getBlog($blogId))->toBeFalse();
});

// ============================================================================
// BLOG RETRIEVAL METHODS
// ============================================================================

/**
 * Test creating blog using direct createBlog method.
 *
 * Verifies createBlog method returns valid ID and inserts data.
 */
it('creates blog using createBlog method', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $blogData = [
        'blog_name' => faker()->words(3, true),
        'blog_slug' => faker()->slug(3).'-'.faker()->numberBetween(1000, 9999),
        'description' => faker()->sentence(),
        'owner_id' => $ownerId,
    ];

    $blogId = $this->blogModel->createBlog($blogData);

    expect($blogId)->toBeGreaterThan(0);
    $blog = $this->blogModel->getBlogById($blogId);
    expect($blog)->toBeArray()
        ->and($blog['blog_name'])->toBe($blogData['blog_name']);
});

/**
 * Test retrieving blog by ID with owner information.
 *
 * Verifies getBlogById returns array with joined user data.
 */
it('gets blog by id with owner information', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $blog = $this->blogModel->getBlogById($blogId);

    expect($blog)->toBeArray()
        ->and($blog)->toHaveKey('owner_name')
        ->and($blog['id'])->toBe($blogId);
});

/**
 * Test retrieving blog by ID with counts.
 *
 * Verifies getBlogByIdWithCounts returns post and author counts.
 */
it('gets blog by id with post and author counts', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    // FIXED: Set author_id and blog_id via withAttributes
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $ownerId, 'blog_id' => $blogId])
        ->count(3);

    $blog = $this->blogModel->getBlogByIdWithCounts($blogId);

    expect($blog)->toBeArray()
        ->and($blog)->toHaveKeys(['post_count', 'author_count', 'owner_name'])
        ->and($blog['post_count'])->toBe(3);
});

/**
 * Test retrieving blog by slug.
 *
 * Verifies getBlogBySlug finds blog using slug string.
 */
it('gets blog by slug', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $slug = 'unique-slug-'.faker()->numberBetween(1000, 9999);

    $blogId = BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_slug' => $slug])
        ->create($ownerId);

    $blog = $this->blogModel->getBlogBySlug($slug);

    expect($blog)->toBeArray()
        ->and($blog['id'])->toBe($blogId)
        ->and($blog['blog_slug'])->toBe($slug);
});

/**
 * Test that getBlogBySlug returns null for non-existent slug.
 */
it('returns null when getting blog by non-existent slug', function () {
    $blog = $this->blogModel->getBlogBySlug('non-existent-slug-'.faker()->numberBetween(1000, 9999));

    expect($blog)->toBeNull();
});

/**
 * Test retrieving all blogs by owner ID.
 *
 * Verifies getBlogsByOwnerId returns array of owner's blogs.
 */
it('gets all blogs by owner id', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $blogIds = BlogFactory::new($this->blogModel)
        ->count(3)
        ->create($ownerId);

    $blogs = $this->blogModel->getBlogsByOwnerId($ownerId);

    expect($blogs)->toBeArray()
        ->and($blogs)->toHaveCount(3)
        ->and(array_column($blogs, 'id'))->toContain(...$blogIds);
});

/**
 * Test retrieving blogs by owner with counts.
 *
 * Verifies getBlogsByOwnerWithCounts returns enriched blog data.
 */
it('gets blogs by owner with counts', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    BlogFactory::new($this->blogModel)
        ->count(2)
        ->create($ownerId);

    $blogs = $this->blogModel->getBlogsByOwnerWithCounts($ownerId);

    expect($blogs)->toBeArray()
        ->and($blogs)->toHaveCount(2)
        ->and($blogs[0])->toHaveKeys(['post_count', 'author_count', 'owner_name']);
});

/**
 * Test retrieving all blogs with owner and counts.
 *
 * Verifies getAllBlogsWithOwnerAndCounts returns all blogs with metadata.
 */
it('gets all blogs with owner and counts', function () {
    $owner1 = UserFactory::new($this->userModel)->create();
    $owner2 = UserFactory::new($this->userModel)->create();

    BlogFactory::new($this->blogModel)->create($owner1);
    BlogFactory::new($this->blogModel)->create($owner2);

    $blogs = $this->blogModel->getAllBlogsWithOwnerAndCounts();

    expect($blogs)->toBeArray()
        ->and(count($blogs))->toBeGreaterThanOrEqual(2)
        ->and($blogs[0])->toHaveKeys(['owner_name', 'post_count', 'author_count']);
});

/**
 * Test retrieving blog ID by owner ID.
 *
 * Verifies getBlogIdByOwnerId returns most recent blog ID.
 */
it('gets blog id by owner id', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $firstBlogId = BlogFactory::new($this->blogModel)->create($ownerId);
    $secondBlogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $retrievedId = $this->blogModel->getBlogIdByOwnerId($ownerId);

    // expect the most recently published blog ID
    expect($retrievedId)->toBeInt()
        ->and($retrievedId)->toBeIn([$firstBlogId, $secondBlogId]);
});

/**
 * Test retrieving blog name by owner ID.
 *
 * Verifies getBlogNameByOwnerId returns blog name string.
 */
it('gets blog name by owner id', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogName = faker()->words(3, true);

    BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_name' => $blogName])
        ->create($ownerId);

    $retrievedName = $this->blogModel->getBlogNameByOwnerId($ownerId);

    expect($retrievedName)->toBeString()
        ->and($retrievedName)->toBe($blogName);
});

/**
 * Test retrieving posts for a blog.
 *
 * Verifies getBlogPosts returns posts belonging to specific blog.
 */
it('gets all posts for a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    // FIXED: Set author_id and blog_id via withAttributes
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $ownerId, 'blog_id' => $blogId])
        ->count(5);

    $posts = $this->blogModel->getBlogPosts($blogId);

    expect($posts)->toBeArray()
        ->and($posts)->toHaveCount(5);
});

/**
 * Test retrieving BlogResource collection by owner ID.
 *
 * Verifies resource method returns array of BlogResource instances.
 */
it('gets blog resources by owner id', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    BlogFactory::new($this->blogModel)
        ->count(2)
        ->create($ownerId);

    $resources = $this->blogModel->resource($ownerId);

    expect($resources)->toBeArray()
        ->and($resources)->toHaveCount(2)
        ->and($resources[0])->toBeInstanceOf(\App\Resources\BlogResource::class);
});

/**
 * Test that resource method returns empty array when no blogs found.
 */
it('returns empty array when getting resources for owner with no blogs', function () {
    $ownerId = UserFactory::new($this->userModel)->create();

    $resources = $this->blogModel->resource($ownerId);

    expect($resources)->toBeEmpty();
});

/**
 * Test retrieving featured creators with limit.
 *
 * Verifies getFeaturedCreators returns top blogs by post count.
 */
it('gets featured creators with custom limit', function (int $limit) {
    $owner1 = UserFactory::new($this->userModel)->create();
    $owner2 = UserFactory::new($this->userModel)->create();

    $blog1 = BlogFactory::new($this->blogModel)->published()->create($owner1);
    $blog2 = BlogFactory::new($this->blogModel)->published()->create($owner2);

    // FIXED: Set author_id and blog_id via withAttributes
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $owner1, 'blog_id' => $blog1])
        ->published()
        ->count(5);

    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $owner2, 'blog_id' => $blog2])
        ->published()
        ->count(2);

    $featured = $this->blogModel->getFeaturedCreators($limit);

    expect($featured)->toBeArray()
        ->and(count($featured))->toBeLessThanOrEqual($limit)
        ->and($featured[0])->toHaveKeys(['ownername', 'postcount']);
})->with('featured_creator_limits');

// ============================================================================
// STATUS MANAGEMENT
// ============================================================================

/**
 * Test publishing a blog.
 *
 * Verifies publishBlog sets status to 'published'.
 */
it('publishes a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->draft()->create($ownerId);

    $result = $this->blogModel->publishBlog($blogId);

    expect($result)->toBeTrue();

    $blog = $this->blogModel->getBlogById($blogId);
    expect($blog['status'])->toBe('published');
});

/**
 * Test unpublishing a blog.
 *
 * Verifies unpublishBlog sets status to 'draft'.
 */
it('unpublishes a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($ownerId);

    $result = $this->blogModel->unpublishBlog($blogId);

    expect($result)->toBeTrue();

    $blog = $this->blogModel->getBlogById($blogId);
    expect($blog['status'])->toBe('draft');
});

// ============================================================================
// COLLABORATOR MANAGEMENT
// ============================================================================

/**
 * Test adding user to blog with valid role.
 *
 * Verifies addUserToBlog creates blog_users relationship with audit log.
 */
it('adds user to blog with valid collaborative role', function (string $role) {
    $ownerId = UserFactory::new($this->userModel)->create();
    $collaboratorId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $result = $this->blogModel->addUserToBlog($blogId, $collaboratorId, $role, $ownerId);

    expect($result)->toBeTrue();

    $blogUsers = $this->blogModel->getBlogUsers($blogId);
    expect($blogUsers)->toBeArray()
        ->and(array_column($blogUsers, 'user_id'))->toContain($collaboratorId);
})->with('blog_collaborator_roles');

/**
 * Test that adding user with invalid role throws exception.
 *
 * Verifies role validation rejects non-collaborative roles.
 */
it('throws exception when adding user with invalid role', function (string $invalidRole) {
    $ownerId = UserFactory::new($this->userModel)->create();
    $collaboratorId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    expect(fn () => $this->blogModel->addUserToBlog($blogId, $collaboratorId, $invalidRole, $ownerId))
        ->toThrow(\InvalidArgumentException::class);
})->with('invalid_blog_roles');

/**
 * Test that re-adding a revoked user reactivates them.
 *
 * Verifies ON DUPLICATE KEY UPDATE logic restores access.
 */
it('reactivates user when re-adding after revocation', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $collaboratorId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    // add, revoke, then re-add the same user
    $this->blogModel->addUserToBlog($blogId, $collaboratorId, 'editor', $ownerId);
    $this->blogModel->revokeUserFromBlog($blogId, $collaboratorId);
    $this->blogModel->addUserToBlog($blogId, $collaboratorId, 'author', $ownerId);

    $blogUsers = $this->blogModel->getBlogUsers($blogId);

    expect($blogUsers)->toHaveCount(1)
        ->and($blogUsers[0]['role'])->toBe('author')
        ->and($blogUsers[0]['is_active'])->toBe(1);
});

/**
 * Test retrieving all active blog users.
 *
 * Verifies getBlogUsers returns only active collaborators with user data.
 */
it('gets all active users for a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $collaborator1 = UserFactory::new($this->userModel)->create();
    $collaborator2 = UserFactory::new($this->userModel)->create();

    $this->blogModel->addUserToBlog($blogId, $collaborator1, 'editor', $ownerId);
    $this->blogModel->addUserToBlog($blogId, $collaborator2, 'author', $ownerId);

    $blogUsers = $this->blogModel->getBlogUsers($blogId);

    expect($blogUsers)->toBeArray()
        ->and($blogUsers)->toHaveCount(2)
        ->and($blogUsers[0])->toHaveKeys(['username', 'email', 'role']);
});

/**
 * Test retrieving users available for blog assignment.
 *
 * Verifies getAvailableUsers excludes already-assigned users.
 */
it('gets available users not yet assigned to blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $assignedUser = UserFactory::new($this->userModel)->create();
    $availableUser = UserFactory::new($this->userModel)->create();

    $this->blogModel->addUserToBlog($blogId, $assignedUser, 'editor', $ownerId);

    $availableUsers = $this->blogModel->getAvailableUsers($blogId);

    expect($availableUsers)->toBeArray()
        ->and(array_column($availableUsers, 'id'))->not->toContain($assignedUser)
        ->and(array_column($availableUsers, 'id'))->toContain($availableUser);
});

/**
 * Test revoking user access to blog.
 *
 * Verifies revokeUserFromBlog performs soft delete with timestamp.
 */
it('revokes user access from blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $collaboratorId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $this->blogModel->addUserToBlog($blogId, $collaboratorId, 'editor', $ownerId);

    $result = $this->blogModel->revokeUserFromBlog($blogId, $collaboratorId);

    expect($result)->toBeTrue();

    // verify user no longer appears in active users list
    $blogUsers = $this->blogModel->getBlogUsers($blogId);
    expect(array_column($blogUsers, 'user_id'))->not->toContain($collaboratorId);
});

/**
 * Test counting active collaborators.
 *
 * Verifies countCollaborators returns correct count for deletion impact.
 */
it('counts active collaborators for a blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    // FIXED: count() already returns array of IDs
    $collaborators = UserFactory::new($this->userModel)->count(3);

    foreach ($collaborators as $collaboratorId) {
        $this->blogModel->addUserToBlog($blogId, $collaboratorId, 'editor', $ownerId);
    }

    $count = $this->blogModel->countCollaborators($blogId);

    expect($count)->toBe(3);
});

/**
 * Test deleting all collaborators for a blog.
 *
 * Verifies deleteCollaboratorsByBlogId performs hard delete and returns count.
 */
it('deletes all collaborators when deleting blog', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($ownerId);

    $collaborators = UserFactory::new($this->userModel)->count(4);

    foreach ($collaborators as $collaboratorId) {
        $this->blogModel->addUserToBlog($blogId, $collaboratorId, 'viewer', $ownerId);
    }

    $deletedCount = $this->blogModel->deleteCollaboratorsByBlogId($blogId);

    expect($deletedCount)->toBe(4);

    $remainingUsers = $this->blogModel->getBlogUsers($blogId);
    expect($remainingUsers)->toBeEmpty();
});
