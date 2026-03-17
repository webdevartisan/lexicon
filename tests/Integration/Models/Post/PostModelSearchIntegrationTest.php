<?php

declare(strict_types=1);

use App\Models\PostModel;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\Models\CategoryModel;
use Tests\Factories\UserFactory;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\CategoryFactory;

/**
 * Integration tests for PostModel search and filtering methods.
 * 
 * Tests search, pagination, and filtering functionality.
 * Part 5 of 5: Search, recent posts, filters, pagination, visibility.
 */

beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->categoryModel = new CategoryModel($this->db);
    
    expect($this->db->getConnection())->toHaveActiveTransaction();
});

// ============================================================================
// SEARCH PUBLISHED POSTS
// ============================================================================

/**
 * Test searching posts by title.
 * 
 * Verifies searchPublishedPosts matches title content.
 */
it('finds posts by title content', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'Understanding PHP Testing',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'JavaScript Basics',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->searchPublishedPosts('PHP', 1, 8);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toContain('PHP')
        ->and($result['totalPosts'])->toBe(1);
});

/**
 * Test searching posts by content.
 * 
 * Verifies searchPublishedPosts matches post content.
 */
it('finds posts by content', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'content' => 'This content mentions Laravel framework',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->searchPublishedPosts('Laravel', 1, 8);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['content'])->toContain('Laravel');
});

/**
 * Test searching posts by blog name.
 * 
 * Verifies searchPublishedPosts matches blog name.
 */
it('finds posts by blog name', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $blogId = BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_name' => 'DevOps Daily'])
        ->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->searchPublishedPosts('DevOps', 1, 8);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['blog_name'])->toBe('DevOps Daily');
});

/**
 * Test filtering search results by category.
 * 
 * Verifies searchPublishedPosts respects category filter.
 */
it('filters search results by category', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $categoryId = CategoryFactory::new($this->categoryModel)
        ->withAttributes(['name' => 'Technology'])
        ->create();
    
    $otherCategoryId = CategoryFactory::new($this->categoryModel)
        ->withAttributes(['name' => 'Lifestyle'])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $categoryId,
            'title' => 'Tech Post',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $otherCategoryId,
            'title' => 'Lifestyle Post',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->searchPublishedPosts('Post', 1, 8, $categoryId);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toBe('Tech Post');
});

/**
 * Test search results pagination.
 * 
 * Verifies searchPublishedPosts returns correct pagination metadata.
 */
it('returns paginated search results', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    for ($i = 1; $i <= 15; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'title' => "Testing Post $i",
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s', strtotime("-$i minutes")),
            ])
            ->create();
    }
    
    $result = $this->postModel->searchPublishedPosts('Testing', 1, 8);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(8)
        ->and($result['totalPages'])->toBe(2)
        ->and($result['currentPage'])->toBe(1)
        ->and($result['totalPosts'])->toBe(15);
});

/**
 * Test that search returns empty results for non-matching query.
 */
it('returns empty results for non-matching search', function () {
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
    
    $result = $this->postModel->searchPublishedPosts('NonExistentTerm', 1, 8);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(0)
        ->and($result['totalPosts'])->toBe(0);
});

// ============================================================================
// GET RECENT PUBLISHED WITH PAGINATION
// ============================================================================

/**
 * Test retrieving recent published posts with pagination.
 * 
 * Verifies getRecentPublishedWithPagination returns paginated recent posts.
 */
it('returns recent published posts with pagination', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    for ($i = 1; $i <= 10; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s', strtotime("-$i hours")),
            ])
            ->create();
    }
    
    $result = $this->postModel->getRecentPublishedWithPagination(1, 5);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(5)
        ->and($result['totalPages'])->toBe(2)
        ->and($result['totalPosts'])->toBe(10);
});

/**
 * Test filtering recent posts by category.
 * 
 * Verifies getRecentPublishedWithPagination respects category filter.
 */
it('filters recent posts by category', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    $categoryId = CategoryFactory::new($this->categoryModel)->create();
    $otherCategoryId = CategoryFactory::new($this->categoryModel)->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $categoryId,
            'title' => 'Tech Post',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'category_id' => $otherCategoryId,
            'title' => 'Lifestyle Post',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->getRecentPublishedWithPagination(1, 8, $categoryId);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toBe('Tech Post');
});

// ============================================================================
// GET INDEX FEED
// ============================================================================

/**
 * Test index feed returns recent posts without query.
 * 
 * Verifies getIndexFeed returns recent posts when no search query provided.
 */
it('returns recent posts when no search query provided', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    for ($i = 1; $i <= 5; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
            ])
            ->create();
    }
    
    $result = $this->postModel->getIndexFeed(['page' => 1, 'perPage' => 8]);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(5);
});

/**
 * Test index feed performs search when query provided.
 * 
 * Verifies getIndexFeed delegates to searchPublishedPosts when query present.
 */
it('performs search when query provided in index feed', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'PHP Tutorial',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'JavaScript Guide',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->getIndexFeed(['query' => 'PHP', 'page' => 1, 'perPage' => 8]);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toContain('PHP');
});

// ============================================================================
// FIND BY AUTHOR WITH FILTERS
// ============================================================================

/**
 * Test finding author posts without filters.
 * 
 * Verifies findByAuthorWithFilters returns all author posts when no filters applied.
 */
it('returns all author posts without filters', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'published', 'published_at' => date('Y-m-d H:i:s')])
        ->count(3);
    
    $result = $this->postModel->findByAuthorWithFilters($userId);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(3);
});

/**
 * Test filtering author posts by blog ID.
 * 
 * Verifies findByAuthorWithFilters respects blog ID filter.
 */
it('filters author posts by blog ID', function () {
    $userId = UserFactory::new($this->userModel)->create();
    
    $blog1Id = BlogFactory::new($this->blogModel)->create($userId);
    $blog2Id = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog1Id, 'status' => 'published', 'published_at' => date('Y-m-d H:i:s')])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blog2Id, 'status' => 'published', 'published_at' => date('Y-m-d H:i:s')])
        ->create();
    
    $result = $this->postModel->findByAuthorWithFilters($userId, $blog1Id);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['blog_id'])->toBe($blog1Id);
});

/**
 * Test filtering author posts by status.
 * 
 * Verifies findByAuthorWithFilters respects status filter.
 */
it('filters author posts by status', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'published', 'published_at' => date('Y-m-d H:i:s')])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'draft'])
        ->create();
    
    $result = $this->postModel->findByAuthorWithFilters($userId, null, 'draft');
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['status'])->toBe('draft');
});

/**
 * Test filtering author posts by search query.
 * 
 * Verifies findByAuthorWithFilters respects search query filter.
 */
it('filters author posts by search query', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'Laravel Tutorial',
            'content' => 'Content about Laravel',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'Symfony Guide',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->findByAuthorWithFilters($userId, null, '', 'Laravel');
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['title'])->toContain('Laravel');
});

// ============================================================================
// FIND BY AUTHOR WITH FILTERS PAGINATION
// ============================================================================

/**
 * Test paginated author filtering.
 * 
 * Verifies findByAuthorWithFiltersPagination returns correct pagination metadata.
 */
it('returns paginated filtered author posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    for ($i = 1; $i <= 15; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s', strtotime("-$i minutes")),
            ])
            ->create();
    }
    
    $result = $this->postModel->findByAuthorWithFiltersPagination($userId, 1, 10);
    
    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(10)
        ->and($result['pagination']['total_pages'])->toBe(2)
        ->and($result['pagination']['total_records'])->toBe(15)
        ->and($result['pagination']['has_previous'])->toBeFalse()
        ->and($result['pagination']['has_next'])->toBeTrue();
});

/**
 * Test that perPage parameter is capped at 100.
 * 
 * Verifies findByAuthorWithFiltersPagination enforces maximum perPage limit.
 */
it('caps perPage at 100 for security', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes(['author_id' => $userId, 'blog_id' => $blogId, 'status' => 'published', 'published_at' => date('Y-m-d H:i:s')])
        ->count(5);
    
    $result = $this->postModel->findByAuthorWithFiltersPagination($userId, 1, 200);
    
    expect($result)->toBeArray()
        ->and($result['pagination']['per_page'])->toBe(100);
});

// ============================================================================
// LIST BY AUTHOR VISIBILITY
// ============================================================================

/**
 * Test listing posts by visibility.
 * 
 * Verifies listByAuthorVisibility filters by visibility setting.
 */
it('lists posts by author with specified visibility', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'visibility' => 'private',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();
    
    $result = $this->postModel->listByAuthorVisibility($userId, ['public'], 10);
    
    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['visibility'])->toBe('public');
});

/**
 * Test that visibility listing respects limit parameter.
 */
it('respects limit when listing by visibility', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);
    
    for ($i = 1; $i <= 20; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => date('Y-m-d H:i:s', strtotime("-$i minutes")),
            ])
            ->create();
    }
    
    $result = $this->postModel->listByAuthorVisibility($userId, ['public'], 5);
    
    expect($result)->toHaveCount(5);
});
