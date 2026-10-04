<?php

declare(strict_types=1);

use App\Models\PlatformTrafficModel;
use App\Models\TrafficRollupModel;

/**
 * Two blogs, one day, two people whose right answers are known by hand.
 *
 * A: signed in, reads post 11 on blog 5 properly after a quick look at its
 *    landing page, then reads post 21 on blog 6.
 * B: anonymous, glances at post 11 on blog 5 and leaves.
 */
beforeEach(function () {
    $hit = function (int $blogId, ?int $postId, string $visitor, string $kind, int $seconds) {
        $path = $postId === null ? '/blog/b'.$blogId : '/blog/b'.$blogId.'/p'.$postId;

        $this->db->execute(
            'INSERT INTO traffic_hits (view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                                       device, browser, os, locale, engaged_seconds, scroll_depth, local_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [random_bytes(16), $blogId, $postId, $postId === null ? 'landing' : 'post', $path,
                substr(hash('sha256', $path, true), 0, 8), md5($visitor, true), $kind, 'direct',
                'desktop', 'Firefox', 'Linux', 'en', $seconds, 50, '2026-03-10', '2026-03-10 12:00:00']
        );
    };

    $hit(5, null, 'A', 'account', 5);
    $hit(5, 11, 'A', 'account', 60);
    $hit(5, 11, 'B', 'daily', 5);
    $hit(6, 21, 'A', 'account', 40);

    (new TrafficRollupModel($this->db))->rebuildFrom('2026-03-01', 30, 10);

    $this->traffic = new PlatformTrafficModel($this->db);
});

test('site visitors count a person once however many blogs they read', function () {
    expect($this->traffic->siteTotals('2026-03-10', '2026-03-10'))->toBe(['views' => 4, 'visitors' => 2])
        ->and($this->traffic->siteSeries('2026-03-01', '2026-03-31'))->toBe([
            '2026-03-10' => ['views' => 4, 'visitors' => 2, 'blogs' => 2],
        ]);
});

test('engagement adds up every blog and counts the blogs that were read', function () {
    $totals = $this->traffic->engagementTotals('2026-03-10', '2026-03-10');

    expect($totals['active_blogs'])->toBe(2)
        ->and($totals['views'])->toBe(4)
        ->and($totals['visitors'])->toBe(3)
        ->and($totals['read_views'])->toBe(2)
        ->and($totals['bounces'])->toBe(1);
});

test('blogs and posts are ranked by views across the platform', function () {
    $blogs = $this->traffic->topBlogs('2026-03-10', '2026-03-10', 10);
    $posts = $this->traffic->topPosts('2026-03-10', '2026-03-10', 10);

    expect(array_map(static fn (array $row): int => (int) $row['blog_id'], $blogs))->toBe([5, 6])
        ->and((int) $blogs[0]['views'])->toBe(3)
        ->and(array_map(static fn (array $row): int => (int) $row['post_id'], $posts))->toBe([11, 21])
        ->and($this->traffic->viewsForBlogs([5, 6, 7], '2026-03-10', '2026-03-10'))->toBe([5 => 3, 6 => 1])
        ->and($this->traffic->viewsForBlogs([], '2026-03-10', '2026-03-10'))->toBe([]);
});

test('breakdowns count visitors per blog, and blog-relative pages are refused', function () {
    expect($this->traffic->breakdown('channel', '2026-03-10', '2026-03-10', 10))->toBe([
        ['value' => 'direct', 'views' => 4, 'visitors' => 3],
    ]);

    $this->traffic->breakdown('page', '2026-03-10', '2026-03-10', 10);
})->throws(InvalidArgumentException::class);

test('a range with nothing in it comes back empty, not missing', function () {
    expect($this->traffic->siteTotals('2026-04-01', '2026-04-30'))->toBe(['views' => 0, 'visitors' => 0])
        ->and($this->traffic->topBlogs('2026-04-01', '2026-04-30', 10))->toBe([])
        ->and($this->traffic->firstDay())->toBe('2026-03-10');
});
