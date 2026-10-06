<?php

declare(strict_types=1);

use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsStatsModel;
use App\ValueObjects\AnalyticsScope;
use Tests\Helpers\AnalyticsFixture;

/**
 * One blog, one day, three visitors whose right answers are known by hand.
 *
 * A: anonymous, reads the landing page briefly, then the post properly.
 * B: analytics cookie, first seen nine days earlier, glances at the post and leaves.
 * C: signed in, first visit, reads the post properly.
 *
 * Each visitor's views on one day are one visit.
 */
beforeEach(function () {
    $this->blogId = 5;
    $this->postId = 11;

    $this->hit = function (
        string $visitor,
        string $kind,
        ?int $postId,
        string $path,
        ?int $seconds,
        ?int $depth,
        string $day,
        string $channel = 'direct',
        ?int $blogId = null,
        ?string $createdAt = null
    ) {
        AnalyticsFixture::view($this->db, [
            'blog_id' => $blogId ?? $this->blogId,
            'post_id' => $postId,
            'page_type' => $postId === null ? 'landing' : 'post',
            'path' => $path,
            'visitor' => $visitor,
            'visit' => $visitor.'@'.$day,
            'visitor_kind' => $kind,
            'channel' => $channel,
            'locale' => 'en',
            'engaged_seconds' => $seconds,
            'scroll_depth' => $depth,
            'local_date' => $day,
            'created_at' => $createdAt ?? $day.' 12:00:00',
        ]);
    };

    ($this->hit)('A', 'daily', null, '/blog/demo', 5, 20, '2026-03-10', 'search');
    ($this->hit)('A', 'daily', $this->postId, '/blog/demo/post', 40, 80, '2026-03-10', 'internal');
    ($this->hit)('B', 'cookie', $this->postId, '/blog/demo/post', 3, 10, '2026-03-10', 'social');
    ($this->hit)('B', 'cookie', $this->postId, '/blog/demo/post', 60, 90, '2026-03-01', 'social');
    ($this->hit)('C', 'account', $this->postId, '/blog/demo/post', 50, 100, '2026-03-10', 'direct');

    $this->rollups = AnalyticsFixture::rollups($this->db);
    $this->stats = new AnalyticsStatsModel($this->db);
});

function rollupSnapshot(Framework\Database $db, string $scope = '%'): array
{
    return [
        $db->query('SELECT scope, scope_id, blog_id, day, views, visitors, identified_visitors, returning_visitors, visits, engaged_visits,
                           read_views, engaged_views, engaged_seconds, scroll_depth_sum
                    FROM analytics_daily WHERE scope LIKE ? ORDER BY scope, scope_id, day', [$scope])->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT scope, scope_id, blog_id, dimension, day, value, views, visitors
                    FROM analytics_daily_dimensions WHERE scope LIKE ?
                    ORDER BY scope, scope_id, dimension, day, value', [$scope])->fetchAll(PDO::FETCH_ASSOC),
    ];
}

test('the blog day adds up the way it was defined', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals(AnalyticsScope::blog($this->blogId), '2026-03-10', '2026-03-10'))->toBe([
        'views' => 4,
        'visitors' => 3,
        'identified_visitors' => 2,
        'returning_visitors' => 1,
        'visits' => 3,
        'engaged_visits' => 2,
        'visit_pages' => 4,
        'visit_seconds' => 0,
        'read_views' => 2,
        'read_to_end' => 1,
        'engaged_views' => 4,
        'engaged_seconds' => 98,
        'scroll_depth_sum' => 210,
        'scroll_25' => 2,
        'scroll_50' => 2,
        'scroll_75' => 2,
        'scroll_100' => 1,
    ]);
});

test('the post day counts only views of that post, and judges each visit by its time on the blog', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $post = $this->stats->totals(AnalyticsScope::post($this->blogId, $this->postId), '2026-03-10', '2026-03-10');

    expect($post['views'])->toBe(3)
        ->and($post['visitors'])->toBe(3)
        ->and($post['returning_visitors'])->toBe(1)
        ->and([$post['visits'], $post['engaged_visits']])->toBe([3, 2])
        ->and($post['read_views'])->toBe(2);
});

test('a post counts a visitor as returning when they were on the blog before, not only on that post', function () {
    ($this->hit)('C', 'account', null, '/blog/demo', 20, 50, '2026-03-05');
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $post = $this->stats->totals(AnalyticsScope::post($this->blogId, $this->postId), '2026-03-10', '2026-03-10');

    expect($post['returning_visitors'])->toBe(2);
});

test('breakdowns and the page list come out of the same views', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $blog = AnalyticsScope::blog($this->blogId);

    $channels = $this->stats->breakdown($blog, 'channel', '2026-03-10', '2026-03-10', 10);
    $pages = $this->stats->breakdown($blog, 'page', '2026-03-10', '2026-03-10', 10);

    expect(array_column($channels, 'views', 'value'))->toEqualCanonicalizing(['search' => 1, 'internal' => 1, 'social' => 1, 'direct' => 1])
        ->and($pages)->toBe([
            ['value' => '/blog/demo', 'views' => 1, 'visitors' => 1, 'read_views' => 0, 'engaged_views' => 1, 'engaged_seconds' => 5],
        ]);
});

test('the site counts a person once across blogs and judges a visit by all of it', function () {
    ($this->hit)('B', 'cookie', null, '/blog/other', 20, 40, '2026-03-10', 'internal', 6);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $site = $this->stats->totals(AnalyticsScope::site(), '2026-03-10', '2026-03-10');

    expect($site['views'])->toBe(5)
        ->and($site['visitors'])->toBe(3)
        ->and($site['returning_visitors'])->toBe(1)
        ->and([$site['visits'], $site['engaged_visits']])->toBe([3, 3])
        ->and($this->stats->totals(AnalyticsScope::blog($this->blogId), '2026-03-10', '2026-03-10')['engaged_visits'])->toBe(2)
        ->and($this->stats->blogsRead('2026-03-10', '2026-03-10'))->toBe(['total' => 2, 'byDay' => ['2026-03-10' => 2]]);
});

test('the site days are UTC while a blog east of UTC has already moved on', function () {
    // 22:30 UTC is 00:30 the next day in Athens, which the recorder writes as local_date.
    ($this->hit)('D', 'daily', null, '/blog/demo', 15, 30, '2026-03-11', 'direct', null, '2026-03-10 22:30:00');
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals(AnalyticsScope::site(), '2026-03-10', '2026-03-10')['views'])->toBe(5)
        ->and($this->stats->totals(AnalyticsScope::site(), '2026-03-11', '2026-03-11')['views'])->toBe(0)
        ->and($this->stats->totals(AnalyticsScope::blog($this->blogId), '2026-03-11', '2026-03-11')['views'])->toBe(1);
});

test("the platform's own pages are their own scope, inside the site and outside every blog", function () {
    foreach ([['/', 'home', 4], ['/about', 'static_page', 40]] as [$path, $type, $seconds]) {
        AnalyticsFixture::view($this->db, [
            'page_type' => $type,
            'path' => $path,
            'visitor' => 'E',
            'visit' => 'E',
            'channel' => 'search',
            'device' => 'mobile',
            'locale' => 'en',
            'engaged_seconds' => $seconds,
            'scroll_depth' => 60,
            'created_at' => '2026-03-10 09:00:00',
        ]);
    }
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $platform = $this->stats->totals(AnalyticsScope::platform(), '2026-03-10', '2026-03-10');
    $site = $this->stats->totals(AnalyticsScope::site(), '2026-03-10', '2026-03-10');
    $pages = $this->stats->breakdown(AnalyticsScope::platform(), 'page', '2026-03-10', '2026-03-10', 10);

    expect([$platform['views'], $platform['visitors'], $platform['visits'], $platform['engaged_visits'], $platform['read_views']])->toBe([2, 1, 1, 1, 1])
        ->and([$site['views'], $site['visitors'], $site['visits'], $site['engaged_visits']])->toBe([6, 4, 4, 3])
        ->and($this->stats->totals(AnalyticsScope::blog($this->blogId), '2026-03-10', '2026-03-10')['views'])->toBe(4)
        ->and(array_column($pages, 'value'))->toEqualCanonicalizing(['/', '/about'])
        ->and($this->stats->blogsRead('2026-03-10', '2026-03-10')['total'])->toBe(1);
});

test('readers sent by another part of Lexicon get their own list on a blog and are internal for the site', function () {
    foreach ([['F', 'lexicon', 'discover'], ['G', 'lexicon', 'blog:6'], ['H', 'search', 'Google']] as [$visitor, $channel, $source]) {
        AnalyticsFixture::view($this->db, [
            'blog_id' => $this->blogId,
            'post_id' => $this->postId,
            'path' => '/blog/demo/post',
            'visitor' => $visitor,
            'visit' => $visitor,
            'channel' => $channel,
            'referrer_source' => $source,
            'locale' => 'en',
            'created_at' => '2026-03-10 12:00:00',
        ]);
    }
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $breakdown = fn (AnalyticsScope $scope, string $dimension): array => array_column(
        $this->stats->breakdown($scope, $dimension, '2026-03-10', '2026-03-10', 10),
        'views',
        'value'
    );
    $post = AnalyticsScope::post($this->blogId, $this->postId);

    expect($breakdown(AnalyticsScope::blog($this->blogId), 'lexicon'))->toEqualCanonicalizing(['discover' => 1, 'blog:6' => 1])
        ->and($breakdown($post, 'lexicon'))->toEqualCanonicalizing(['discover' => 1, 'blog:6' => 1])
        ->and($breakdown(AnalyticsScope::blog($this->blogId), 'source'))->toBe(['Google' => 1])
        ->and($breakdown(AnalyticsScope::blog($this->blogId), 'channel')['lexicon'])->toBe(2)
        ->and($breakdown(AnalyticsScope::site(), 'channel'))->not->toHaveKey('lexicon')
        ->and($breakdown(AnalyticsScope::site(), 'channel')['internal'])->toBe(3)
        ->and($breakdown(AnalyticsScope::site(), 'source'))->toBe(['Google' => 1]);
});

test('running the aggregation again gives exactly the same rows', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $first = rollupSnapshot($this->db);

    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect(rollupSnapshot($this->db))->toBe($first);
});

test('a day that loses views loses its breakdown rows too', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $this->db->execute("DELETE FROM analytics_events WHERE channel = 'search'");

    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $blogChannels = $this->stats->breakdown(AnalyticsScope::blog($this->blogId), 'channel', '2026-03-10', '2026-03-10', 10);
    $siteChannels = $this->stats->breakdown(AnalyticsScope::site(), 'channel', '2026-03-10', '2026-03-10', 10);

    expect(array_column($blogChannels, 'value'))->not->toContain('search')
        ->and(array_column($siteChannels, 'value'))->not->toContain('search');
});

test('a deleted blog keeps its views in the site and leaves no blog or post rows', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $siteTotals = rollupSnapshot($this->db, 'site')[0];
    $siteChannels = $this->stats->breakdown(AnalyticsScope::site(), 'channel', '2026-03-01', '2026-03-31', 10);

    (new AnalyticsEventModel($this->db))->detachBlog($this->blogId);
    $this->rollups->deleteByBlogId($this->blogId);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect(rollupSnapshot($this->db, 'site')[0])->toBe($siteTotals)
        ->and($this->stats->breakdown(AnalyticsScope::site(), 'channel', '2026-03-01', '2026-03-31', 10))->toBe($siteChannels)
        ->and((int) $this->db->query("SELECT COUNT(*) FROM analytics_daily WHERE scope IN ('blog', 'post')")->fetchColumn())->toBe(0)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM analytics_daily_dimensions WHERE blog_id IS NOT NULL')->fetchColumn())->toBe(0);
});

test('each visit to the blog has one entry page and one exit page', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $blog = AnalyticsScope::blog($this->blogId);
    $list = fn (string $dimension): array => array_column($this->stats->breakdown($blog, $dimension, '2026-03-10', '2026-03-10', 10), 'views', 'value');

    expect($list('entry'))->toEqualCanonicalizing(['/blog/demo' => 1, '/blog/demo/post' => 2])
        ->and($list('exit'))->toBe(['/blog/demo/post' => 3]);
});

test('where readers went next belongs to the post they left', function () {
    AnalyticsFixture::view($this->db, [
        'blog_id' => $this->blogId,
        'post_id' => 12,
        'from_post_id' => $this->postId,
        'path' => '/blog/demo/other',
        'visitor' => 'A',
        'visit' => 'A@2026-03-10',
        'channel' => 'internal',
        'locale' => 'en',
        'local_hour' => 9,
        'created_at' => '2026-03-10 12:05:00',
    ]);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $next = $this->stats->breakdown(AnalyticsScope::post($this->blogId, $this->postId), 'next', '2026-03-10', '2026-03-10', 10);
    $hourly = $this->stats->hourly(AnalyticsScope::blog($this->blogId), '2026-03-10', '2026-03-10');

    // 2026-03-10 was a Tuesday, which MySQL's DAYOFWEEK numbers 3.
    expect(array_column($next, 'views', 'value'))->toBe(['/blog/demo/other' => 1])
        ->and($hourly)->toEqual([3 => [0 => 4, 9 => 1]])
        ->and($this->stats->hourly(AnalyticsScope::site(), '2026-03-10', '2026-03-10'))->toEqual([3 => [12 => 5]]);
});

test('a visitor that opens page after page without ever leaving one counts nowhere', function () {
    for ($i = 0; $i < 20; $i++) {
        ($this->hit)('S', 'daily', $this->postId, '/blog/demo/post', null, null, '2026-03-10');
    }

    expect((new AnalyticsEventModel($this->db))->markSuspects('2026-03-01', 20))->toBe(20);

    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals(AnalyticsScope::blog($this->blogId), '2026-03-10', '2026-03-10')['views'])->toBe(4)
        ->and($this->stats->totals(AnalyticsScope::site(), '2026-03-10', '2026-03-10')['views'])->toBe(4);
});

test('a rebuild of the blogs that changed leaves the others as they were', function () {
    ($this->hit)('Z', 'daily', null, '/blog/elsewhere', 20, 40, '2026-03-10', 'direct', 6);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $this->db->execute('DELETE FROM analytics_events WHERE blog_id = 6');

    $this->rollups->rebuildFrom('2026-03-01', 30, 10, [$this->blogId]);
    $six = $this->stats->totals(AnalyticsScope::blog(6), '2026-03-10', '2026-03-10')['views'];

    $this->rollups->rebuildFrom('2026-03-01', 30, 10, [6]);

    expect($six)->toBe(1)
        ->and($this->stats->totals(AnalyticsScope::blog(6), '2026-03-10', '2026-03-10')['views'])->toBe(0)
        ->and($this->stats->totals(AnalyticsScope::site(), '2026-03-10', '2026-03-10')['views'])->toBe(4);
});
