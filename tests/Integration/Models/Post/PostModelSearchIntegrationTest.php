<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\PublishedPostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\CategoryFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for PostModel search and filtering methods.
 *
 * Tests search, pagination, and filtering functionality.
 * Part 5 of 5: Search, recent posts, filters, pagination, visibility.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->publishedPosts = new PublishedPostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);
    $this->categoryModel = new CategoryModel($this->db);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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

    $result = $this->publishedPosts->searchPublishedPosts('PHP', 1, 8);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'content' => 'This content mentions Laravel framework',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    $result = $this->publishedPosts->searchPublishedPosts('Laravel', 1, 8);

    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['content'])->toContain('Laravel');
});

/**
 * Search is word-prefix based, so a partial word still finds the post.
 */
it('matches a word prefix', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'Of Mercury Rising',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    $result = $this->publishedPosts->searchPublishedPosts('merc', 1, 8);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toBe('Of Mercury Rising');
});

/**
 * Every word has to match, so adding a word narrows the results.
 */
it('requires every search word to match', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    foreach (['Mercury Rising', 'Mercury Falling'] as $title) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'title' => $title,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
            ])
            ->create();
    }

    $result = $this->publishedPosts->searchPublishedPosts('mercury ris', 1, 8);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toBe('Mercury Rising');
});

/**
 * Boolean operators typed by a visitor are stripped, never interpreted.
 */
it('treats FULLTEXT operators in the query as plain text', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'Mercury Rising',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    $operators = $this->publishedPosts->searchPublishedPosts('-mercury', 1, 8);
    $onlyOperators = $this->publishedPosts->searchPublishedPosts('+-"*()@<>~', 1, 8);

    expect($operators['data'])->toHaveCount(1)
        ->and($onlyOperators['data'])->toBe([])
        ->and($onlyOperators['totalPosts'])->toBe(0);
});

/**
 * Greek text is split on spaces like any other, so prefixes work in el.
 */
it('searches non-latin text', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'title' => 'Ο Ερμής ανεβαίνει',
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    $result = $this->publishedPosts->searchPublishedPosts('Ερμ', 1, 8);

    expect($result['data'])->toHaveCount(1);
});

/**
 * Blog names are not part of the post search; the Blogs tab covers them.
 */
it('does not match a post through its blog name', function () {
    $userId = UserFactory::new($this->userModel)->create();

    $blogId = BlogFactory::new($this->blogModel)
        ->withAttributes(['blog_name' => 'DevOps Daily'])
        ->published()
        ->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    $result = $this->publishedPosts->searchPublishedPosts('DevOps', 1, 8);

    expect($result['data'])->toHaveCount(0);
});

/**
 * Test filtering search results by category.
 *
 * Verifies searchPublishedPosts respects category filter.
 */
it('filters search results by category', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    $categoryId = CategoryFactory::new($this->categoryModel)
        ->forBlog($blogId)
        ->withAttributes(['name' => 'Technology'])
        ->create();

    $otherCategoryId = CategoryFactory::new($this->categoryModel)
        ->forBlog($blogId)
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

    // Category filter matches by name (topic filter spans all blogs)
    $result = $this->publishedPosts->searchPublishedPosts('Post', 1, 8, 'Technology');

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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

    $result = $this->publishedPosts->searchPublishedPosts('Testing', 1, 8);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ])
        ->create();

    $result = $this->publishedPosts->searchPublishedPosts('NonExistentTerm', 1, 8);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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

    $result = $this->publishedPosts->getRecentPublishedWithPagination(1, 5);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

    $categoryId = CategoryFactory::new($this->categoryModel)->forBlog($blogId)
        ->withAttributes(['name' => 'Technology'])
        ->create();
    $otherCategoryId = CategoryFactory::new($this->categoryModel)->forBlog($blogId)
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

    // Category filter matches by name (topic filter spans all blogs)
    $result = $this->publishedPosts->getRecentPublishedWithPagination(1, 8, 'Technology');

    expect($result)->toBeArray()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['title'])->toBe('Tech Post');
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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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

    $result = $this->publishedPosts->listByAuthorVisibility($userId, ['public'], 10);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(1)
        ->and($result[0]['visibility'])->toBe('public');
});

/**
 * Test that visibility listing respects limit parameter.
 */
it('respects limit when listing by visibility', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->published()->create($userId);

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

    $result = $this->publishedPosts->listByAuthorVisibility($userId, ['public'], 5);

    expect($result)->toHaveCount(5);
});
