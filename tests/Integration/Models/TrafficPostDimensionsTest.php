<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\TrafficRawStatsModel;
use App\Models\TrafficRollupModel;
use App\Models\TrafficStatsModel;
use App\Models\UserModel;
use App\ValueObjects\TrafficScope;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

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
        $path = '/blog/b/p'.$postId;
        $this->db->execute(
            "INSERT INTO traffic_hits (view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                                       referrer_source, device, browser, os, locale, engaged_seconds, scroll_depth, local_date, created_at)
             VALUES (?, ?, ?, 'post', ?, ?, ?, 'daily', ?, ?, 'mobile', 'Safari', 'iOS', 'en', ?, 60, UTC_DATE(), UTC_TIMESTAMP())",
            [random_bytes(16), $this->blogId, $postId, $path, substr(hash('sha256', $path, true), 0, 8), md5($visitor, true), $channel, $source, $seconds]
        );
    };

    $view($this->taggedPost, 'A', 'search', 'Google', 45);
    $view($this->taggedPost, 'B', 'search', 'Google', 5);
    $view($this->plainPost, 'C', 'direct', null, 90);

    $this->today = gmdate('Y-m-d');
    (new TrafficRollupModel($this->db))->rebuildFrom($this->today, 30, 10);
    $this->stats = new TrafficStatsModel($this->db);
    $this->blog = TrafficScope::blog($this->blogId);
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
    $narrowed = (new TrafficRawStatsModel($this->db))->filtered(['source' => 'Google'], 30, 10);
    $totals = $narrowed->totals($this->blog, $this->today, $this->today);

    expect([$totals['views'], $totals['visitors'], $totals['read_views']])->toBe([$google['views'], $google['visitors'], $google['read_views']])
        ->and($totals['identified_visitors'])->toBe(0)
        ->and(array_column($narrowed->topPosts($this->blog, $this->today, $this->today, 10), 'post_id'))->toEqual([$this->taggedPost]);
});

test('narrowing to a category keeps only the views of its posts', function () {
    $narrowed = (new TrafficRawStatsModel($this->db))->filtered(['category' => (string) $this->categoryId], 30, 10);

    expect($narrowed->totals($this->blog, $this->today, $this->today)['views'])->toBe(2)
        ->and(array_column($narrowed->breakdown($this->blog, 'channel', $this->today, $this->today, 10), 'views', 'value'))->toBe(['search' => 2]);
});
