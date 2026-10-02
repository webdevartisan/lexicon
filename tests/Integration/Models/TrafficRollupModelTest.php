<?php

declare(strict_types=1);

use App\Models\TrafficRollupModel;
use App\Models\TrafficStatsModel;

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

    $hit = function (string $visitor, string $kind, ?int $postId, string $path, ?int $seconds, ?int $depth, string $day, string $channel = 'direct') {
        $this->db->execute(
            'INSERT INTO traffic_hits (view_id, blog_id, post_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel,
                                       device, browser, os, locale, engaged_seconds, scroll_depth, local_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [random_bytes(16), $this->blogId, $postId, $postId === null ? 'landing' : 'post', $path,
                substr(hash('sha256', $path, true), 0, 8), md5($visitor, true), $kind, $channel,
                'desktop', 'Firefox', 'Linux', 'en', $seconds, $depth, $day, $day.' 12:00:00']
        );
    };

    $hit('A', 'daily', null, '/blog/demo', 5, 20, '2026-03-10', 'search');
    $hit('A', 'daily', $this->postId, '/blog/demo/post', 40, 80, '2026-03-10', 'internal');
    $hit('B', 'cookie', $this->postId, '/blog/demo/post', 3, 10, '2026-03-10', 'social');
    $hit('B', 'cookie', $this->postId, '/blog/demo/post', 60, 90, '2026-03-01', 'social');
    $hit('C', 'account', $this->postId, '/blog/demo/post', 50, 100, '2026-03-10', 'direct');

    $this->rollups = new TrafficRollupModel($this->db);
    $this->stats = new TrafficStatsModel($this->db);
});

function rollupSnapshot(Framework\Database $db): array
{
    return [
        $db->query('SELECT blog_id, post_id, day, views, visitors, identified_visitors, returning_visitors, bounces,
                           read_views, engaged_views, engaged_seconds, scroll_depth_sum
                    FROM traffic_daily ORDER BY blog_id, post_id, day')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT blog_id, post_id, dimension, day, value, views, visitors
                    FROM traffic_daily_dimensions ORDER BY blog_id, post_id, dimension, day, value')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT day, views, visitors, blogs FROM traffic_site_daily ORDER BY day')->fetchAll(PDO::FETCH_ASSOC),
    ];
}

test('the blog day adds up the way it was defined', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect($this->stats->totals($this->blogId, null, '2026-03-10', '2026-03-10'))->toBe([
        'views' => 4,
        'visitors' => 3,
        'identified_visitors' => 2,
        'returning_visitors' => 1,
        'bounces' => 1,
        'read_views' => 2,
        'engaged_views' => 4,
        'engaged_seconds' => 98,
        'scroll_depth_sum' => 210,
    ]);
});

test('the post day counts only views of that post, with no bounces', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $post = $this->stats->totals($this->blogId, [$this->postId], '2026-03-10', '2026-03-10');

    expect($post['views'])->toBe(3)
        ->and($post['visitors'])->toBe(3)
        ->and($post['returning_visitors'])->toBe(1)
        ->and($post['bounces'])->toBe(0)
        ->and($post['read_views'])->toBe(2);
});

test('breakdowns and the page list come out of the same views', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    $channels = $this->stats->breakdown($this->blogId, null, 'channel', '2026-03-10', '2026-03-10', 10);
    $pages = $this->stats->breakdown($this->blogId, null, 'page', '2026-03-10', '2026-03-10', 10);

    expect(array_column($channels, 'views', 'value'))->toEqualCanonicalizing(['search' => 1, 'internal' => 1, 'social' => 1, 'direct' => 1])
        ->and($pages)->toBe([['value' => '/blog/demo', 'views' => 1, 'visitors' => 1]]);
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
    $channels = array_column($this->stats->breakdown($this->blogId, null, 'channel', '2026-03-10', '2026-03-10', 10), 'value');

    expect($channels)->not->toContain('search');
});

test('platform totals keep the views of a deleted blog', function () {
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);
    $before = rollupSnapshot($this->db)[2];

    $this->rollups->deleteByBlogId($this->blogId);
    $this->db->execute('DELETE FROM traffic_hits WHERE blog_id = ?', [$this->blogId]);
    $this->rollups->rebuildFrom('2026-03-01', 30, 10);

    expect(rollupSnapshot($this->db)[2])->toBe($before)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM traffic_daily')->fetchColumn())->toBe(0);
});
