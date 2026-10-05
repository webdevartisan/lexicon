<?php

declare(strict_types=1);

use App\Models\TrafficHitModel;
use App\Models\TrafficRollupModel;
use App\Models\TrafficStatsModel;
use App\ValueObjects\TrafficScope;

/**
 * One blog, one day, three visitors whose right answers are known by hand.
 *
 * A: anonymous, reads the landing page briefly, then the post properly.
 * B: analytics cookie, first seen nine days earlier, glances at the post and leaves.
 * C: signed in, first visit, reads the post properly.
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
        $this->db->execute(
            'INSERT INTO traffic_hits (view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                                       device, browser, os, locale, engaged_seconds, scroll_depth, local_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [random_bytes(16), $blogId ?? $this->blogId, $postId, $postId === null ? 'landing' : 'post', $path,
                substr(hash('sha256', $path, true), 0, 8), md5($visitor, true), $kind, $channel,
                'desktop', 'Firefox', 'Linux', 'en', $seconds, $depth, $day, $createdAt ?? $day.' 12:00:00']
        );
    };

    ($this->hit)('A', 'daily', null, '/blog/demo', 5, 20, '2026-03-10', 'search');
    ($this->hit)('A', 'daily', $this->postId, '/blog/demo/post', 40, 80, '2026-03-10', 'internal');
    ($this->hit)('B', 'cookie', $this->postId, '/blog/demo/post', 3, 10, '2026-03-10', 'social');
    ($this->hit)('B', 'cookie', $this->postId, '/blog/demo/post', 60, 90, '2026-03-01', 'social');
    ($this->hit)('C', 'account', $this->postId, '/blog/demo/post', 50, 100, '2026-03-10', 'direct');

    $this->rollups = new TrafficRollupModel($this->db);
    $this->stats = new TrafficStatsModel($this->db);
});

function rollupSnapshot(Framework\Database $db, string $scope = '%'): array
{
    return [
        $db->query('SELECT scope, scope_id, blog_id, day, views, visitors, identified_visitors, returning_visitors, bounces,
                           read_views, engaged_views, engaged_seconds, scroll_depth_sum
                    FROM traffic_daily WHERE scope LIKE ? ORDER BY scope, scope_id, day', [$scope])->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT scope, scope_id, blog_id, dimension, day, value, views, visitors
                    FROM traffic_daily_dimensions WHERE scope LIKE ?
                    ORDER BY scope, scope_id, dimension, day, value', [$scope])->fetchAll(PDO::FETCH_ASSOC),
    ];
}

test('the blog day adds up the way it was defined', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals(TrafficScope::blog($this->blogId), '2026-03-10', '2026-03-10'))->toBe([
        'views' => 4,
        'visitors' => 3,
        'identified_visitors' => 2,
        'returning_visitors' => 1,
        'bounces' => 1,
        'read_views' => 2,
        'engaged_views' => 4,
        'engaged_seconds' => 98,
        'scroll_depth_sum' => 210,
        'scroll_25' => 2,
        'scroll_50' => 2,
        'scroll_75' => 2,
        'scroll_100' => 1,
    ]);
});

test('the post day counts only views of that post, with no bounces', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $post = $this->stats->totals(TrafficScope::post($this->blogId, $this->postId), '2026-03-10', '2026-03-10');

    expect($post['views'])->toBe(3)
        ->and($post['visitors'])->toBe(3)
        ->and($post['returning_visitors'])->toBe(1)
        ->and($post['bounces'])->toBe(0)
        ->and($post['read_views'])->toBe(2);
});

test('a post counts a visitor as returning when they were on the blog before, not only on that post', function () {
    ($this->hit)('C', 'account', null, '/blog/demo', 20, 50, '2026-03-05');
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $post = $this->stats->totals(TrafficScope::post($this->blogId, $this->postId), '2026-03-10', '2026-03-10');

    expect($post['returning_visitors'])->toBe(2);
});

test('breakdowns and the page list come out of the same views', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $blog = TrafficScope::blog($this->blogId);

    $channels = $this->stats->breakdown($blog, 'channel', '2026-03-10', '2026-03-10', 10);
    $pages = $this->stats->breakdown($blog, 'page', '2026-03-10', '2026-03-10', 10);

    expect(array_column($channels, 'views', 'value'))->toEqualCanonicalizing(['search' => 1, 'internal' => 1, 'social' => 1, 'direct' => 1])
        ->and($pages)->toBe([
            ['value' => '/blog/demo', 'views' => 1, 'visitors' => 1, 'read_views' => 0, 'engaged_views' => 1, 'engaged_seconds' => 5],
        ]);
});

test('the site counts a person once across blogs and a bounce as leaving the whole site', function () {
    ($this->hit)('B', 'cookie', null, '/blog/other', 20, 40, '2026-03-10', 'internal', 6);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $site = $this->stats->totals(TrafficScope::site(), '2026-03-10', '2026-03-10');

    expect($site['views'])->toBe(5)
        ->and($site['visitors'])->toBe(3)
        ->and($site['returning_visitors'])->toBe(1)
        ->and($site['bounces'])->toBe(0)
        ->and($this->stats->totals(TrafficScope::blog($this->blogId), '2026-03-10', '2026-03-10')['bounces'])->toBe(1)
        ->and($this->stats->blogsRead('2026-03-10', '2026-03-10'))->toBe(['total' => 2, 'byDay' => ['2026-03-10' => 2]]);
});

test('the site days are UTC while a blog east of UTC has already moved on', function () {
    // 22:30 UTC is 00:30 the next day in Athens, which the recorder writes as local_date.
    ($this->hit)('D', 'daily', null, '/blog/demo', 15, 30, '2026-03-11', 'direct', null, '2026-03-10 22:30:00');
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals(TrafficScope::site(), '2026-03-10', '2026-03-10')['views'])->toBe(5)
        ->and($this->stats->totals(TrafficScope::site(), '2026-03-11', '2026-03-11')['views'])->toBe(0)
        ->and($this->stats->totals(TrafficScope::blog($this->blogId), '2026-03-11', '2026-03-11')['views'])->toBe(1);
});

test("the platform's own pages are their own scope, inside the site and outside every blog", function () {
    foreach ([['/', 'home', 4], ['/about', 'static_page', 40]] as [$path, $type, $seconds]) {
        $this->db->execute(
            "INSERT INTO traffic_hits (view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                                       device, browser, os, locale, engaged_seconds, scroll_depth, local_date, created_at)
             VALUES (?, NULL, NULL, ?, ?, ?, ?, 'daily', 'search', 'mobile', 'Safari', 'iOS', 'en', ?, 60, '2026-03-10', '2026-03-10 09:00:00')",
            [random_bytes(16), $type, $path, substr(hash('sha256', $path, true), 0, 8), md5('E', true), $seconds]
        );
    }
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $platform = $this->stats->totals(TrafficScope::platform(), '2026-03-10', '2026-03-10');
    $site = $this->stats->totals(TrafficScope::site(), '2026-03-10', '2026-03-10');
    $pages = $this->stats->breakdown(TrafficScope::platform(), 'page', '2026-03-10', '2026-03-10', 10);

    expect([$platform['views'], $platform['visitors'], $platform['bounces'], $platform['read_views']])->toBe([2, 1, 0, 1])
        ->and([$site['views'], $site['visitors'], $site['bounces']])->toBe([6, 4, 1])
        ->and($this->stats->totals(TrafficScope::blog($this->blogId), '2026-03-10', '2026-03-10')['views'])->toBe(4)
        ->and(array_column($pages, 'value'))->toEqualCanonicalizing(['/', '/about'])
        ->and($this->stats->blogsRead('2026-03-10', '2026-03-10')['total'])->toBe(1);
});

test('readers sent by another part of Lexicon get their own list on a blog and are internal for the site', function () {
    foreach ([['F', 'lexicon', 'discover'], ['G', 'lexicon', 'blog:6'], ['H', 'search', 'Google']] as [$visitor, $channel, $source]) {
        $this->db->execute(
            "INSERT INTO traffic_hits (view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                                       referrer_source, device, browser, os, locale, local_date, created_at)
             VALUES (?, ?, ?, 'post', '/blog/demo/post', ?, ?, 'daily', ?, ?, 'desktop', 'Firefox', 'Linux', 'en', '2026-03-10', '2026-03-10 12:00:00')",
            [random_bytes(16), $this->blogId, $this->postId, substr(hash('sha256', '/blog/demo/post', true), 0, 8), md5($visitor, true), $channel, $source]
        );
    }
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $breakdown = fn (TrafficScope $scope, string $dimension): array => array_column(
        $this->stats->breakdown($scope, $dimension, '2026-03-10', '2026-03-10', 10),
        'views',
        'value'
    );
    $post = TrafficScope::post($this->blogId, $this->postId);

    expect($breakdown(TrafficScope::blog($this->blogId), 'lexicon'))->toEqualCanonicalizing(['discover' => 1, 'blog:6' => 1])
        ->and($breakdown($post, 'lexicon'))->toEqualCanonicalizing(['discover' => 1, 'blog:6' => 1])
        ->and($breakdown(TrafficScope::blog($this->blogId), 'source'))->toBe(['Google' => 1])
        ->and($breakdown(TrafficScope::blog($this->blogId), 'channel')['lexicon'])->toBe(2)
        ->and($breakdown(TrafficScope::site(), 'channel'))->not->toHaveKey('lexicon')
        ->and($breakdown(TrafficScope::site(), 'channel')['internal'])->toBe(3)
        ->and($breakdown(TrafficScope::site(), 'source'))->toBe(['Google' => 1]);
});

test('running the aggregation again gives exactly the same rows', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $first = rollupSnapshot($this->db);

    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect(rollupSnapshot($this->db))->toBe($first);
});

test('a day that loses views loses its breakdown rows too', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $this->db->execute("DELETE FROM traffic_hits WHERE channel = 'search'");

    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $blogChannels = $this->stats->breakdown(TrafficScope::blog($this->blogId), 'channel', '2026-03-10', '2026-03-10', 10);
    $siteChannels = $this->stats->breakdown(TrafficScope::site(), 'channel', '2026-03-10', '2026-03-10', 10);

    expect(array_column($blogChannels, 'value'))->not->toContain('search')
        ->and(array_column($siteChannels, 'value'))->not->toContain('search');
});

test('a deleted blog keeps its views in the site and leaves no blog or post rows', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $siteTotals = rollupSnapshot($this->db, 'site')[0];
    $siteChannels = $this->stats->breakdown(TrafficScope::site(), 'channel', '2026-03-01', '2026-03-31', 10);

    (new TrafficHitModel($this->db))->detachBlog($this->blogId);
    $this->rollups->deleteByBlogId($this->blogId);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect(rollupSnapshot($this->db, 'site')[0])->toBe($siteTotals)
        ->and($this->stats->breakdown(TrafficScope::site(), 'channel', '2026-03-01', '2026-03-31', 10))->toBe($siteChannels)
        ->and((int) $this->db->query("SELECT COUNT(*) FROM traffic_daily WHERE scope IN ('blog', 'post')")->fetchColumn())->toBe(0)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM traffic_daily_dimensions WHERE blog_id IS NOT NULL')->fetchColumn())->toBe(0);
});

test('each visit to the blog has one entry page and one exit page', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $blog = TrafficScope::blog($this->blogId);
    $list = fn (string $dimension): array => array_column($this->stats->breakdown($blog, $dimension, '2026-03-10', '2026-03-10', 10), 'views', 'value');

    expect($list('entry'))->toEqualCanonicalizing(['/blog/demo' => 1, '/blog/demo/post' => 2])
        ->and($list('exit'))->toBe(['/blog/demo/post' => 3]);
});

test('where readers went next belongs to the post they left', function () {
    $this->db->execute(
        "INSERT INTO traffic_hits (view_id, blog_id, post_id, from_post_id, page_type, path, path_hash, visitor_hash, visitor_kind,
                                   channel, device, browser, os, locale, local_date, local_hour, created_at)
         VALUES (?, ?, 12, ?, 'post', '/blog/demo/other', ?, ?, 'daily', 'internal', 'desktop', 'Firefox', 'Linux', 'en',
                 '2026-03-10', 9, '2026-03-10 12:05:00')",
        [random_bytes(16), $this->blogId, $this->postId, substr(hash('sha256', '/blog/demo/other', true), 0, 8), md5('A', true)]
    );
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $next = $this->stats->breakdown(TrafficScope::post($this->blogId, $this->postId), 'next', '2026-03-10', '2026-03-10', 10);
    $hourly = $this->stats->hourly(TrafficScope::blog($this->blogId), '2026-03-10', '2026-03-10');

    // 2026-03-10 was a Tuesday, which MySQL's DAYOFWEEK numbers 3.
    expect(array_column($next, 'views', 'value'))->toBe(['/blog/demo/other' => 1])
        ->and($hourly)->toEqual([3 => [0 => 4, 9 => 1]])
        ->and($this->stats->hourly(TrafficScope::site(), '2026-03-10', '2026-03-10'))->toEqual([3 => [12 => 5]]);
});

test('a visitor that opens page after page without ever leaving one counts nowhere', function () {
    for ($i = 0; $i < 20; $i++) {
        ($this->hit)('S', 'daily', $this->postId, '/blog/demo/post', null, null, '2026-03-10');
    }

    expect((new TrafficHitModel($this->db))->markSuspects('2026-03-01', 20))->toBe(20);

    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals(TrafficScope::blog($this->blogId), '2026-03-10', '2026-03-10')['views'])->toBe(4)
        ->and($this->stats->totals(TrafficScope::site(), '2026-03-10', '2026-03-10')['views'])->toBe(4);
});

test('a rebuild of the blogs that changed leaves the others as they were', function () {
    ($this->hit)('Z', 'daily', null, '/blog/elsewhere', 20, 40, '2026-03-10', 'direct', 6);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $this->db->execute('DELETE FROM traffic_hits WHERE blog_id = 6');

    $this->rollups->rebuildFrom('2026-03-01', 30, 10, [$this->blogId]);
    $six = $this->stats->totals(TrafficScope::blog(6), '2026-03-10', '2026-03-10')['views'];

    $this->rollups->rebuildFrom('2026-03-01', 30, 10, [6]);

    expect($six)->toBe(1)
        ->and($this->stats->totals(TrafficScope::blog(6), '2026-03-10', '2026-03-10')['views'])->toBe(0)
        ->and($this->stats->totals(TrafficScope::site(), '2026-03-10', '2026-03-10')['views'])->toBe(4);
});
