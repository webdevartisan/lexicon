<?php

declare(strict_types=1);

use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\ValueObjects\AnalyticsScope;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\AnalyticsFixture;

/**
 * Two blogs, one day, two people whose right answers are known by hand.
 *
 * A: signed in, reads post 11 on blog 5 properly after a quick look at its
 *    landing page, then reads post 21 on blog 6, all in one visit.
 * B: anonymous, glances at post 11 on blog 5 and leaves.
 */
beforeEach(function () {
    $hit = function (int $blogId, ?int $postId, string $visitor, string $kind, int $seconds) {
        AnalyticsFixture::view($this->db, [
            'blog_id' => $blogId,
            'post_id' => $postId,
            'page_type' => $postId === null ? 'landing' : 'post',
            'path' => $postId === null ? '/blog/b'.$blogId : '/blog/b'.$blogId.'/p'.$postId,
            'visitor' => $visitor,
            'visit' => $visitor,
            'visitor_kind' => $kind,
            'locale' => 'en',
            'engaged_seconds' => $seconds,
            'scroll_depth' => 50,
            'created_at' => '2026-03-10 12:00:00',
        ]);
    };

    $hit(5, null, 'A', 'account', 5);
    $hit(5, 11, 'A', 'account', 60);
    $hit(5, 11, 'B', 'daily', 5);
    $hit(6, 21, 'A', 'account', 40);

    (AnalyticsFixture::rollups($this->db))->rebuildFrom('2026-03-01', 30, 10);

    $this->stats = new AnalyticsStatsModel($this->db);
});

test('the site counts a person once however many blogs they read', function () {
    $site = $this->stats->totals(AnalyticsScope::site(), '2026-03-10', '2026-03-10');

    expect($site['views'])->toBe(4)
        ->and($site['visitors'])->toBe(2)
        ->and($site['identified_visitors'])->toBe(1)
        ->and($site['read_views'])->toBe(2)
        ->and($site['visits'])->toBe(2)
        ->and($site['engaged_visits'])->toBe(1)
        ->and($this->stats->series(AnalyticsScope::site(), '2026-03-01', '2026-03-31'))->toBe([
            '2026-03-10' => ['views' => 4, 'visitors' => 2],
        ])
        ->and($this->stats->blogsRead('2026-03-01', '2026-03-31'))->toBe(['total' => 2, 'byDay' => ['2026-03-10' => 2]]);
});

test('each blog keeps its own numbers', function () {
    $five = $this->stats->totals(AnalyticsScope::blog(5), '2026-03-10', '2026-03-10');
    $six = $this->stats->totals(AnalyticsScope::blog(6), '2026-03-10', '2026-03-10');

    expect([$five['views'], $five['visitors'], $five['visits'], $five['engaged_visits']])->toBe([3, 2, 2, 1])
        ->and([$six['views'], $six['visitors'], $six['visits'], $six['engaged_visits']])->toBe([1, 1, 1, 1]);
});

test("an author's posts are added together, and a post from another blog never counts", function () {
    $mine = AnalyticsScope::authorPosts(5, 99, [11, 21]);

    expect($this->stats->totals($mine, '2026-03-10', '2026-03-10')['views'])->toBe(2)
        ->and(array_column($this->stats->topPosts($mine, '2026-03-10', '2026-03-10', 10), 'post_id'))->toEqual([11])
        ->and($this->stats->totals(AnalyticsScope::authorPosts(5, 99, []), '2026-03-10', '2026-03-10')['views'])->toBe(0);
});

test('blogs and posts are ranked by views', function () {
    $blogs = $this->stats->topBlogs('2026-03-10', '2026-03-10', 10);

    expect(array_map(static fn (array $row): int => (int) $row['blog_id'], $blogs))->toBe([5, 6])
        ->and((int) $blogs[0]['views'])->toBe(3)
        ->and(array_map(
            static fn (array $row): int => (int) $row['post_id'],
            $this->stats->topPosts(AnalyticsScope::site(), '2026-03-10', '2026-03-10', 10)
        ))->toBe([11, 21])
        ->and(array_map(
            static fn (array $row): int => (int) $row['post_id'],
            $this->stats->topPosts(AnalyticsScope::blog(6), '2026-03-10', '2026-03-10', 10)
        ))->toBe([21])
        ->and($this->stats->viewsForBlogs([5, 6, 7], '2026-03-10', '2026-03-10'))->toBe([5 => 3, 6 => 1])
        ->and($this->stats->viewsForBlogs([], '2026-03-10', '2026-03-10'))->toBe([]);
});

test('site breakdowns count a visitor once, and a scope only offers its own lists', function () {
    expect($this->stats->breakdown(AnalyticsScope::site(), 'channel', '2026-03-10', '2026-03-10', 10))->toBe([
        ['value' => 'direct', 'views' => 4, 'visitors' => 2, 'read_views' => 2, 'engaged_views' => 4, 'engaged_seconds' => 110],
    ]);

    $this->stats->breakdown(AnalyticsScope::site(), 'page', '2026-03-10', '2026-03-10', 10);
})->throws(InvalidArgumentException::class);

test('lifetime views per post stay inside their blog', function () {
    expect($this->stats->lifetimeForPosts(5, [11, 21]))->toBe([11 => ['views' => 2, 'visitors' => 2]]);
});

test('a range with nothing in it comes back as zeros, not missing', function () {
    expect($this->stats->totals(AnalyticsScope::site(), '2026-04-01', '2026-04-30')['views'])->toBe(0)
        ->and($this->stats->topBlogs('2026-04-01', '2026-04-30', 10))->toBe([])
        ->and($this->stats->blogsRead('2026-04-01', '2026-04-30'))->toBe(['total' => 0, 'byDay' => []])
        ->and($this->stats->firstDay(AnalyticsScope::site()))->toBe('2026-03-10')
        ->and($this->stats->firstDay(AnalyticsScope::blog(6)))->toBe('2026-03-10')
        ->and($this->stats->firstDay(AnalyticsScope::blog(7)))->toBeNull();
});

test('only a blog that is published now is named as where readers came from', function () {
    $blogs = new BlogModel($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $public = BlogFactory::new($blogs)->published()->withAttributes(['blog_name' => 'Field Notes'])->create($ownerId);
    $hidden = BlogFactory::new($blogs)->draft()->create($ownerId);

    expect($this->stats->publishedBlogNames([$public, $hidden, 999999]))->toBe([$public => 'Field Notes'])
        ->and($this->stats->publishedBlogNames([]))->toBe([]);
});

test('the limit cuts the ranking, and a post that no longer exists keeps its numbers', function () {
    $all = $this->stats->topPosts(AnalyticsScope::site(), '2026-03-10', '2026-03-10', 10);
    $first = $this->stats->topPosts(AnalyticsScope::site(), '2026-03-10', '2026-03-10', 1);

    expect($first)->toBe([$all[0]])
        ->and((int) $all[0]['post_id'])->toBe(11)
        ->and((int) $all[0]['views'])->toBe(2)
        ->and($all[0]['title'])->toBeNull();
});
