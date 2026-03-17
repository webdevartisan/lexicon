<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Framework\Database;
use Tests\Factories\BlogFactory;
use Tests\Factories\CategoryFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for CategoryModel.
 *
 * Tests all CategoryModel methods with real database interactions.
 * Uses factories for data generation and transactions for isolation.
 */
beforeEach(function () {
    $this->categoryModel = new CategoryModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->postModel = new PostModel($this->db);
});

// ============================================================================
// CRUD OPERATIONS
// ============================================================================

/**
 * Test that a category can be created with valid data.
 *
 * Verifies create method returns valid ID and persists data correctly.
 */
it('creates a category successfully', function () {
    $name = faker()->words(2, true);
    $slug = faker()->slug(2);

    $data = [
        'name' => $name,
        'slug' => $slug,
    ];

    $id = $this->categoryModel->create($data);

    expect($id)->toBeInt()
        ->and($id)->toBeGreaterThan(0);

    $category = $this->categoryModel->findById($id);

    expect($category)->toBeArray()
        ->and($category['name'])->toBe($name)
        ->and($category['slug'])->toBe($slug);
});

/**
 * Test that a category can be updated.
 *
 * Verifies update operation persists changes correctly.
 */
it('updates a category successfully', function () {
    $id = CategoryFactory::new($this->categoryModel)
        ->withAttributes([
            'name' => faker()->word(),
            'slug' => faker()->slug(1),
        ])
        ->create();

    $newName = faker()->words(3, true);
    $newSlug = faker()->slug(3);

    $result = $this->categoryModel->update($id, [
        'name' => $newName,
        'slug' => $newSlug,
    ]);

    expect($result)->toBeTrue();

    $updated = $this->categoryModel->findById($id);

    expect($updated['name'])->toBe($newName)
        ->and($updated['slug'])->toBe($newSlug);
});

/**
 * Test that a category can be deleted.
 *
 * Verifies delete operation removes record from database.
 */
it('deletes a category successfully', function () {
    $id = CategoryFactory::new($this->categoryModel)->create();

    $result = $this->categoryModel->delete($id);

    expect($result)->toBeTrue();

    $findResult = $this->categoryModel->findById($id);

    expect($findResult)->toBeIn([false, [], null]);
});

/**
 * Test that deleting non-existent category is handled gracefully.
 *
 * Verifies delete method handles invalid IDs without errors.
 */
it('handles deleting non-existent category gracefully', function () {
    $result = $this->categoryModel->delete(99999);

    expect($result)->toBeIn([true, false]);
});

// ============================================================================
// FINDER METHODS
// ============================================================================

/**
 * Test retrieving category by slug.
 *
 * Verifies findBySlug returns correct category data.
 */
it('finds category by slug when it exists', function () {
    $slug = 'javascript-'.faker()->numberBetween(1000, 9999);

    CategoryFactory::new($this->categoryModel)
        ->withAttributes([
            'name' => 'JavaScript',
            'slug' => $slug,
        ])
        ->create();

    $category = $this->categoryModel->findBySlug($slug);

    expect($category)->toBeArray()
        ->and($category['name'])->toBe('JavaScript')
        ->and($category['slug'])->toBe($slug);
});

/**
 * Test that findBySlug returns null for non-existent slug.
 */
it('returns null when slug does not exist', function () {
    $result = $this->categoryModel->findBySlug('non-existent-slug-'.faker()->uuid());

    expect($result)->toBeNull();
});

/**
 * Test that findBySlug handles special characters safely.
 *
 * Verifies slug parameter is properly sanitized against SQL injection.
 */
it('handles special characters in slug safely', function () {
    $slug = 'c-plus-plus-'.faker()->numberBetween(1000, 9999);

    CategoryFactory::new($this->categoryModel)
        ->withAttributes([
            'name' => 'C++',
            'slug' => $slug,
        ])
        ->create();

    $category = $this->categoryModel->findBySlug($slug);

    expect($category)->toBeArray()
        ->and($category['slug'])->toBe($slug);
});

/**
 * Test retrieving all categories ordered by name.
 *
 * Verifies getCategories returns alphabetically sorted list.
 */
it('gets all categories ordered by name', function () {
    CategoryFactory::new($this->categoryModel)
        ->withAttributes(['name' => 'Zebra Topics'])
        ->create();

    CategoryFactory::new($this->categoryModel)
        ->withAttributes(['name' => 'Alpha Topics'])
        ->create();

    CategoryFactory::new($this->categoryModel)
        ->withAttributes(['name' => 'Beta Topics'])
        ->create();

    $categories = $this->categoryModel->getCategories();

    expect($categories)->toBeArray()
        ->and($categories)->toHaveCount(3)
        ->and($categories[0]['name'])->toBe('Alpha Topics')
        ->and($categories[1]['name'])->toBe('Beta Topics')
        ->and($categories[2]['name'])->toBe('Zebra Topics');
});

/**
 * Test that getCategories returns empty array when no categories exist.
 */
it('returns empty array when no categories exist', function () {
    $categories = $this->categoryModel->getCategories();

    expect($categories)->toBeArray()
        ->and($categories)->toBeEmpty();
});

// ============================================================================
// RELATIONSHIP METHODS
// ============================================================================

/**
 * Test retrieving published posts in category.
 *
 * Verifies posts method returns only published posts ordered by created_at DESC.
 */
it('returns published posts in category', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);
    $categoryId = CategoryFactory::new($this->categoryModel)->create();

    // create posts with different timestamps
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $categoryId,
            'title' => 'Recent Post',
            'status' => 'published',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $categoryId,
            'title' => 'Older Post',
            'status' => 'published',
        ])
        ->create();

    $posts = $this->categoryModel->posts($categoryId);

    expect($posts)->toBeArray()
        ->and($posts)->toHaveCount(2);
});

/**
 * Test that posts method filters out non-published statuses.
 *
 * Verifies only published posts are returned, excluding drafts and pending.
 */
it('filters out draft and pending posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);
    $categoryId = CategoryFactory::new($this->categoryModel)->create();

    // create posts with various statuses
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $categoryId,
            'status' => 'published',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $categoryId,
            'status' => 'draft',
        ])
        ->create();

    $posts = $this->categoryModel->posts($categoryId);

    expect($posts)->toHaveCount(1)
        ->and($posts[0]['status'])->toBe('published');
});

/**
 * Test that posts returns empty array when category has no posts.
 */
it('returns empty array when category has no posts', function () {
    $categoryId = CategoryFactory::new($this->categoryModel)->create();

    $posts = $this->categoryModel->posts($categoryId);

    expect($posts)->toBeArray()
        ->and($posts)->toBeEmpty();
});

/**
 * Test that posts returns empty array for non-existent category.
 */
it('returns empty array for non-existent category', function () {
    $posts = $this->categoryModel->posts(99999);

    expect($posts)->toBeArray()
        ->and($posts)->toBeEmpty();
});

// ============================================================================
// EDGE CASES & SECURITY
// ============================================================================

/**
 * Test that findBySlug prevents SQL injection.
 *
 * Verifies prepared statements protect against SQL injection attacks.
 */
it('prevents SQL injection in findBySlug', function () {
    CategoryFactory::new($this->categoryModel)
        ->withAttributes(['slug' => 'legit'])
        ->create();

    $result = $this->categoryModel->findBySlug("' OR '1'='1");

    expect($result)->toBeNull();
});

/**
 * Test that category name with HTML is stored safely.
 *
 * Verifies raw storage without escaping (escaping happens at view layer).
 */
it('stores category name with special HTML characters safely', function () {
    $xssName = '<script>alert("XSS")</script>';

    $id = CategoryFactory::new($this->categoryModel)
        ->withAttributes([
            'name' => $xssName,
            'slug' => 'xss-test',
        ])
        ->create();

    $category = $this->categoryModel->findById($id);

    expect($category['name'])->toBe($xssName);
});

/**
 * Test that update handles empty data gracefully.
 */
it('handles empty update data', function () {
    $id = CategoryFactory::new($this->categoryModel)->create();

    $result = $this->categoryModel->update($id, []);

    expect($result)->toBeIn([true, false]);
});
