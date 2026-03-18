<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for PostModel navigation methods.
 *
 * Tests previous/next post navigation and related posts functionality.
 * Part 3 of 5: Navigation by author/blog, recent posts, random posts.
 */
beforeEach(function () {
    $this->postModel = new PostModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->blogModel = new BlogModel($this->db);

    
});

// ============================================================================
// PREVIOUS/NEXT BY AUTHOR
// ============================================================================

/**
 * Test finding previous post by author and date.
 *
 * Verifies findPreviousByAuthorAndDate returns chronologically previous post.
 */
it('finds previous post by author and date', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-02 10:00:00',
        ])
        ->create();

    $previous = $this->postModel->findPreviousByAuthorAndDate($userId, '2024-01-02 10:00:00');

    expect($previous)->toBeArray()
        ->and($previous['published_at'])->toBe('2024-01-01 10:00:00');
});

/**
 * Test that previous returns null when no earlier post exists.
 */
it('returns null when no previous post exists by author', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    $previous = $this->postModel->findPreviousByAuthorAndDate($userId, '2024-01-01 10:00:00');

    expect($previous)->toBeNull();
});

/**
 * Test finding next post by author and date.
 *
 * Verifies findNextByAuthorAndDate returns chronologically next post.
 */
it('finds next post by author and date', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-02 10:00:00',
        ])
        ->create();

    $next = $this->postModel->findNextByAuthorAndDate($userId, '2024-01-01 10:00:00');

    expect($next)->toBeArray()
        ->and($next['published_at'])->toBe('2024-01-02 10:00:00');
});

/**
 * Test that next returns null when no later post exists.
 */
it('returns null when no next post exists by author', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    $next = $this->postModel->findNextByAuthorAndDate($userId, '2024-01-01 10:00:00');

    expect($next)->toBeNull();
});

// ============================================================================
// PREVIOUS/NEXT BY BLOG ID
// ============================================================================

/**
 * Test finding previous post by blog and date.
 *
 * Verifies findPreviousByBlogIdAndDate returns chronologically previous post in blog.
 */
it('finds previous post by blog ID and date', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-02 10:00:00',
        ])
        ->create();

    $previous = $this->postModel->findPreviousByBlogIdAndDate($blogId, '2024-01-02 10:00:00');

    expect($previous)->toBeArray()
        ->and($previous['published_at'])->toBe('2024-01-01 10:00:00');
});

/**
 * Test finding next post by blog and date.
 *
 * Verifies findNextByBlogIdAndDate returns chronologically next post in blog.
 */
it('finds next post by blog ID and date', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-02 10:00:00',
        ])
        ->create();

    $next = $this->postModel->findNextByBlogIdAndDate($blogId, '2024-01-01 10:00:00');

    expect($next)->toBeArray()
        ->and($next['published_at'])->toBe('2024-01-02 10:00:00');
});

// ============================================================================
// RECENT POSTS EXCLUDING SLUG
// ============================================================================

/**
 * Test finding recent posts by author excluding current.
 *
 * Verifies findRecentByAuthorExcludingSlug returns recent posts without specified slug.
 */
it('finds recent posts by author excluding current slug', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $currentSlug = 'current-post-'.faker()->unique()->numberBetween(1000, 9999);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'slug' => $currentSlug,
            'status' => 'published',
            'published_at' => '2024-01-03 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-02 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    $recent = $this->postModel->findRecentByAuthorExcludingSlug($userId, $currentSlug, 4);

    expect($recent)->toBeArray()
        ->and($recent)->toHaveCount(2)
        ->and(array_column($recent, 'slug'))->not->toContain($currentSlug);
});

/**
 * Test finding recent posts by blog excluding current.
 *
 * Verifies findRecentByBlogIdExcludingSlug returns recent posts without specified slug.
 */
it('finds recent posts by blog ID excluding current slug', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $currentSlug = 'current-post-'.faker()->unique()->numberBetween(1000, 9999);

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'slug' => $currentSlug,
            'status' => 'published',
            'published_at' => '2024-01-03 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-02 10:00:00',
        ])
        ->create();

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'published',
            'published_at' => '2024-01-01 10:00:00',
        ])
        ->create();

    $recent = $this->postModel->findRecentByBlogIdExcludingSlug($blogId, $currentSlug, 4);

    expect($recent)->toBeArray()
        ->and($recent)->toHaveCount(2)
        ->and(array_column($recent, 'slug'))->not->toContain($currentSlug);
});

/**
 * Test that recent posts respects limit parameter.
 */
it('respects limit when finding recent posts by author', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    $currentSlug = 'post-1-'.faker()->unique()->numberBetween(1000, 9999);

    for ($i = 1; $i <= 6; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'slug' => $i === 1 ? $currentSlug : 'post-'.$i,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s', strtotime("-$i days")),
            ])
            ->create();
    }

    $recent = $this->postModel->findRecentByAuthorExcludingSlug($userId, $currentSlug, 2);

    expect($recent)->toHaveCount(2);
});

// ============================================================================
// RANDOM POSTS
// ============================================================================

/**
 * Test finding random published posts.
 *
 * Verifies findRandomPublicPosts returns only published posts.
 */
it('finds random published posts only', function () {
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

    PostFactory::new($this->postModel)
        ->withAttributes([
            'author_id' => $userId,
            'blog_id' => $blogId,
            'status' => 'draft',
        ])
        ->create();

    $random = $this->postModel->findRandomPublicPosts(6);

    expect($random)->toBeArray()
        ->and($random)->toHaveCount(6)
        ->and(array_column($random, 'status'))->each->toBe('published');
});

/**
 * Test that random posts respects limit parameter.
 */
it('respects limit when finding random posts', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $blogId = BlogFactory::new($this->blogModel)->create($userId);

    for ($i = 1; $i <= 20; $i++) {
        PostFactory::new($this->postModel)
            ->withAttributes([
                'author_id' => $userId,
                'blog_id' => $blogId,
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
            ])
            ->create();
    }

    $random = $this->postModel->findRandomPublicPosts(3);

    expect($random)->toHaveCount(3);
});

/**
 * Test that random posts returns empty array when none exist.
 */
it('returns empty array when no published posts exist for random', function () {
    $random = $this->postModel->findRandomPublicPosts(6);

    expect($random)->toBeArray()
        ->and($random)->toHaveCount(0);
});
