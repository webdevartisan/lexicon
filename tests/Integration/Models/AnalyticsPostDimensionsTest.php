<?php

declare(strict_types=1);

use App\Models\AnalyticsRawStatsModel;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\ValueObjects\AnalyticsScope;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\AnalyticsFixture;

/**
 * Two posts in one blog: one in a category with two tags, read twice from
 * Google; one with neither, read once directly.
 */
beforeEach(function () {
    $this->authorId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->authorId);

    $this->db->execute("INSERT INTO categories (blog_id, name, slug) VALUES (?, 'Field notes', 'field-notes')", [$this->blogId]);
    $this->categoryId = (int) $this->db->getConnection()->lastInsertId();

    $posts = new PostModel($this->db);
    $this->taggedPost = PostFactory::new($posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->authorId, 'category_id' => $this->categoryId])
        ->published()->create();
    $this->plainPost = PostFactory::new($posts)
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->authorId, 'category_id' => null])
        ->published()->create();

    foreach (['birds', 'rivers'] as $tag) {
        $this->db->execute('INSERT INTO tags (blog_id, name, slug) VALUES (?, ?, ?)', [$this->blogId, ucfirst($tag), $tag]);
        $this->db->execute('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$this->taggedPost, (int) $this->db->getConnection()->lastInsertId()]);
    }

    $view = function (int $postId, string $visitor, string $channel, ?string $source, int $seconds) {
        AnalyticsFixture::view($this->db, [
            'blog_id' => $this->blogId,
            'post_id' => $postId,
            'path' => '/blog/b/p'.$postId,
            'visitor' => $visitor,
            'visit' => $visitor,
            'channel' => $channel,
            'referrer_source' => $source,
            'device' => 'mobile',
            'browser' => 'Safari',
            'os' => 'iOS',
            'locale' => 'en',
            'engaged_seconds' => $seconds,
            'scroll_depth' => 60,
        ]);
    };

    $view($this->taggedPost, 'A', 'search', 'Google', 45);
    $view($this->taggedPost, 'B', 'search', 'Google', 5);
    $view($this->plainPost, 'C', 'direct', null, 90);

    $this->today = gmdate('Y-m-d');
    (AnalyticsFixture::rollups($this->db))->rebuildFrom($this->today, 30, 10);
    $this->stats = new AnalyticsStatsModel($this->db);
    $this->blog = AnalyticsScope::blog($this->blogId);
});

test('views of a post count for its category, each of its tags and its author', function () {
    $list = fn (string $dimension): array => array_column($this->stats->breakdown($this->blog, $dimension, $this->today, $this->today, 10), 'views', 'value');

    expect($list('category'))->toBe([(string) $this->categoryId => 2])
        ->and(array_values($list('tag')))->toBe([2, 2])
        ->and($list('author'))->toBe([(string) $this->authorId => 3])
        ->and($this->stats->labels('category', [$this->categoryId]))->toBe([$this->categoryId => 'Field notes']);
});

test('a page narrowed to one source counts the way the daily tables count that source', function () {
    $google = $this->stats->breakdown($this->blog, 'source', $this->today, $this->today, 10)[0];
    $narrowed = (new AnalyticsRawStatsModel($this->db))->filtered(['source' => 'Google'], 30, 10);
    $totals = $narrowed->totals($this->blog, $this->today, $this->today);

    expect([$totals['views'], $totals['visitors'], $totals['read_views']])->toBe([$google['views'], $google['visitors'], $google['read_views']])
        ->and($totals['identified_visitors'])->toBe(0)
        ->and(array_column($narrowed->topPosts($this->blog, $this->today, $this->today, 10), 'post_id'))->toEqual([$this->taggedPost]);
});

test('narrowing to a category keeps only the views of its posts', function () {
    $narrowed = (new AnalyticsRawStatsModel($this->db))->filtered(['category' => (string) $this->categoryId], 30, 10);

    expect($narrowed->totals($this->blog, $this->today, $this->today)['views'])->toBe(2)
        ->and(array_column($narrowed->breakdown($this->blog, 'channel', $this->today, $this->today, 10), 'views', 'value'))->toBe(['search' => 2]);
});
