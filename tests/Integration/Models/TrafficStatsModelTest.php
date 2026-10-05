<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\TrafficRollupModel;
use App\Models\TrafficStatsModel;
use App\Models\UserModel;
use App\ValueObjects\TrafficScope;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

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

    $this->stats = new TrafficStatsModel($this->db);
});

test('the site counts a person once however many blogs they read', function () {
    $site = $this->stats->totals(TrafficScope::site(), '2026-03-10', '2026-03-10');

    expect($site['views'])->toBe(4)
        ->and($site['visitors'])->toBe(2)
        ->and($site['identified_visitors'])->toBe(1)
        ->and($site['read_views'])->toBe(2)
        ->and($site['bounces'])->toBe(1)
        ->and($this->stats->series(TrafficScope::site(), '2026-03-01', '2026-03-31'))->toBe([
            '2026-03-10' => ['views' => 4, 'visitors' => 2],
        ])
        ->and($this->stats->blogsRead('2026-03-01', '2026-03-31'))->toBe(['total' => 2, 'byDay' => ['2026-03-10' => 2]]);
});

test('each blog keeps its own numbers', function () {
    $five = $this->stats->totals(TrafficScope::blog(5), '2026-03-10', '2026-03-10');
    $six = $this->stats->totals(TrafficScope::blog(6), '2026-03-10', '2026-03-10');

    expect([$five['views'], $five['visitors'], $five['bounces']])->toBe([3, 2, 1])
        ->and([$six['views'], $six['visitors'], $six['bounces']])->toBe([1, 1, 0]);
});

test("an author's posts are added together, and a post from another blog never counts", function () {
    $mine = TrafficScope::authorPosts(5, 99, [11, 21]);

    expect($this->stats->totals($mine, '2026-03-10', '2026-03-10')['views'])->toBe(2)
        ->and(array_column($this->stats->topPosts($mine, '2026-03-10', '2026-03-10', 10), 'post_id'))->toEqual([11])
        ->and($this->stats->totals(TrafficScope::authorPosts(5, 99, []), '2026-03-10', '2026-03-10')['views'])->toBe(0);
});

test('blogs and posts are ranked by views', function () {
    $blogs = $this->stats->topBlogs('2026-03-10', '2026-03-10', 10);

    expect(array_map(static fn (array $row): int => (int) $row['blog_id'], $blogs))->toBe([5, 6])
        ->and((int) $blogs[0]['views'])->toBe(3)
        ->and(array_map(
            static fn (array $row): int => (int) $row['post_id'],
            $this->stats->topPosts(TrafficScope::site(), '2026-03-10', '2026-03-10', 10)
        ))->toBe([11, 21])
        ->and(array_map(
            static fn (array $row): int => (int) $row['post_id'],
            $this->stats->topPosts(TrafficScope::blog(6), '2026-03-10', '2026-03-10', 10)
        ))->toBe([21])
        ->and($this->stats->viewsForBlogs([5, 6, 7], '2026-03-10', '2026-03-10'))->toBe([5 => 3, 6 => 1])
        ->and($this->stats->viewsForBlogs([], '2026-03-10', '2026-03-10'))->toBe([]);
});

test('site breakdowns count a visitor once, and a scope only offers its own lists', function () {
    expect($this->stats->breakdown(TrafficScope::site(), 'channel', '2026-03-10', '2026-03-10', 10))->toBe([
        ['value' => 'direct', 'views' => 4, 'visitors' => 2],
    ]);

    $this->stats->breakdown(TrafficScope::site(), 'page', '2026-03-10', '2026-03-10', 10);
})->throws(InvalidArgumentException::class);

test('lifetime views per post stay inside their blog', function () {
    expect($this->stats->lifetimeForPosts(5, [11, 21]))->toBe([11 => ['views' => 2, 'visitors' => 2]]);
});

test('a range with nothing in it comes back as zeros, not missing', function () {
    expect($this->stats->totals(TrafficScope::site(), '2026-04-01', '2026-04-30')['views'])->toBe(0)
        ->and($this->stats->topBlogs('2026-04-01', '2026-04-30', 10))->toBe([])
        ->and($this->stats->blogsRead('2026-04-01', '2026-04-30'))->toBe(['total' => 0, 'byDay' => []])
        ->and($this->stats->firstDay(TrafficScope::site()))->toBe('2026-03-10')
        ->and($this->stats->firstDay(TrafficScope::blog(6)))->toBe('2026-03-10')
        ->and($this->stats->firstDay(TrafficScope::blog(7)))->toBeNull();
});

test('only a blog that is published now is named as where readers came from', function () {
    $blogs = new BlogModel($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $public = BlogFactory::new($blogs)->published()->withAttributes(['blog_name' => 'Field Notes'])->create($ownerId);
    $hidden = BlogFactory::new($blogs)->draft()->create($ownerId);

    expect($this->stats->publishedBlogNames([$public, $hidden, 999999]))->toBe([$public => 'Field Notes'])
        ->and($this->stats->publishedBlogNames([]))->toBe([]);
});
