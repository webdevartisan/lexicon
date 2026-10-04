<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Builds the daily traffic tables from traffic_hits.
 *
 * Each blog day is deleted and re-inserted in one transaction, so a re-run gives
 * identical rows and a day that lost views also loses their breakdown rows.
 */
class TrafficRollupModel extends AppModel
{
    protected ?string $table = 'traffic_daily';

    /**
     * Breakdown => the traffic_hits expression it groups by. Fixed strings, never input.
     */
    private const DIMENSIONS = [
        'channel' => 'channel',
        'source' => 'referrer_source',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
        'device' => 'device',
        'browser' => 'browser',
        'os' => 'os',
        'country' => 'country',
        'locale' => 'locale',
        'page' => 'LEFT(path, 191)',
    ];

    /**
     * Rebuild every blog day from $from on, and the platform days since the same date.
     *
     * @param  string  $from  Y-m-d, blog-local for blog rows and UTC for platform rows
     * @return array{daily: int, dimensions: int, site: int} Rows written
     */
    public function rebuildFrom(string $from, int $readSeconds, int $bounceSeconds): array
    {
        return $this->transaction(function () use ($from, $readSeconds, $bounceSeconds): array {
            $this->database->execute('DELETE FROM traffic_daily WHERE day >= ?', [$from]);
            $this->database->execute('DELETE FROM traffic_daily_dimensions WHERE day >= ?', [$from]);

            $daily = $this->insertDaily($from, false, $readSeconds, $bounceSeconds)
                + $this->insertDaily($from, true, $readSeconds, $bounceSeconds);

            $dimensions = $this->insertDimensions($from, false) + $this->insertDimensions($from, true);

            return ['daily' => $daily, 'dimensions' => $dimensions, 'site' => $this->upsertSite($from)];
        });
    }

    public function deleteByBlogId(int $blogId): void
    {
        $this->database->execute('DELETE FROM traffic_daily WHERE blog_id = ?', [$blogId]);
        $this->database->execute('DELETE FROM traffic_daily_dimensions WHERE blog_id = ?', [$blogId]);
    }

    /**
     * One row per blog (or post) and day. Visitors are counted per day, so a
     * person reading on Monday and Tuesday is a visitor on each day.
     */
    private function insertDaily(string $from, bool $perPost, int $readSeconds, int $bounceSeconds): int
    {
        $post = $perPost ? 'h.post_id' : '0';
        $postGroup = $perPost ? 'h.post_id, ' : '';
        $postFilter = $perPost ? 'AND h.post_id IS NOT NULL' : '';
        // A bounce is a visit to the blog, not to one post, so post rows carry none.
        $bounces = $perPost ? '0' : "SUM(v.views = 1 AND v.best_seconds < {$bounceSeconds})";

        $sql = "INSERT INTO traffic_daily
                    (blog_id, post_id, day, views, visitors, identified_visitors, returning_visitors,
                     bounces, read_views, engaged_views, engaged_seconds, scroll_depth_sum)
                WITH first_seen AS (
                    SELECT h.visitor_hash, h.blog_id, MIN(h.local_date) AS first_day
                    FROM traffic_hits h
                    JOIN (SELECT DISTINCT visitor_hash, blog_id FROM traffic_hits
                          WHERE local_date >= ? AND visitor_kind <> 'daily') w
                      ON w.visitor_hash = h.visitor_hash AND w.blog_id = h.blog_id
                    GROUP BY h.visitor_hash, h.blog_id
                ),
                per_visitor AS (
                    SELECT h.blog_id, {$post} AS post_id, h.local_date, h.visitor_hash,
                           COUNT(*) AS views,
                           MAX(h.visitor_kind <> 'daily') AS identified,
                           MAX(COALESCE(h.engaged_seconds, 0)) AS best_seconds,
                           COALESCE(SUM(h.engaged_seconds >= {$readSeconds}), 0) AS read_views,
                           SUM(h.engaged_seconds IS NOT NULL) AS engaged_views,
                           COALESCE(SUM(h.engaged_seconds), 0) AS engaged_seconds,
                           COALESCE(SUM(CASE WHEN h.engaged_seconds IS NOT NULL THEN h.scroll_depth END), 0)
                               AS scroll_sum
                    FROM traffic_hits h
                    WHERE h.local_date >= ? {$postFilter}
                    GROUP BY h.blog_id, {$postGroup}h.local_date, h.visitor_hash
                )
                SELECT v.blog_id, v.post_id, v.local_date,
                       SUM(v.views), COUNT(*), SUM(v.identified),
                       SUM(v.identified = 1 AND f.first_day < v.local_date),
                       {$bounces},
                       SUM(v.read_views), SUM(v.engaged_views), SUM(v.engaged_seconds), SUM(v.scroll_sum)
                FROM per_visitor v
                LEFT JOIN first_seen f ON f.visitor_hash = v.visitor_hash AND f.blog_id = v.blog_id
                GROUP BY v.blog_id, v.post_id, v.local_date";

        return $this->database->execute($sql, [$from, $from]);
    }

    private function insertDimensions(string $from, bool $perPost): int
    {
        $post = $perPost ? 'post_id' : '0';
        $postGroup = $perPost ? 'post_id, ' : '';
        $selects = [];
        $params = [];

        foreach (self::DIMENSIONS as $dimension => $column) {
            // Top pages lists the blog's other pages. Posts have their own table.
            if ($dimension === 'page' && $perPost) {
                continue;
            }

            $filter = $dimension === 'page' ? "AND page_type <> 'post'" : "AND {$column} IS NOT NULL";
            $filter .= $perPost ? ' AND post_id IS NOT NULL' : '';

            $selects[] = "SELECT blog_id, {$post}, '{$dimension}', local_date, {$column},
                                 COUNT(*), COUNT(DISTINCT visitor_hash)
                          FROM traffic_hits
                          WHERE local_date >= ? {$filter}
                          GROUP BY blog_id, {$postGroup}local_date, {$column}";
            $params[] = $from;
        }

        $sql = 'INSERT INTO traffic_daily_dimensions (blog_id, post_id, dimension, day, value, views, visitors) '
            .implode(' UNION ALL ', $selects);

        return $this->database->execute($sql, $params);
    }

    /**
     * Platform totals by UTC day. GREATEST keeps the totals of a blog whose raw
     * views were deleted with it.
     */
    private function upsertSite(string $from): int
    {
        $sql = 'INSERT INTO traffic_site_daily (day, views, visitors, blogs)
                SELECT DATE(created_at), COUNT(*), COUNT(DISTINCT visitor_hash), COUNT(DISTINCT blog_id)
                FROM traffic_hits
                WHERE created_at >= ?
                GROUP BY DATE(created_at)
                ON DUPLICATE KEY UPDATE
                    views = GREATEST(views, VALUES(views)),
                    visitors = GREATEST(visitors, VALUES(visitors)),
                    blogs = GREATEST(blogs, VALUES(blogs))';

        return $this->database->execute($sql, [$from.' 00:00:00']);
    }
}
