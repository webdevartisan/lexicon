<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\TrafficScope;

/**
 * Builds the daily traffic tables from traffic_hits, one scope at a time.
 *
 * Each scope's days are deleted and re-inserted in one transaction, so a re-run
 * gives identical rows and a day that lost views also loses their breakdown rows.
 * Views flagged as script-like count nowhere. What each scope and breakdown
 * means lives in TrafficSql.
 */
class TrafficRollupModel extends AppModel
{
    protected ?string $table = 'traffic_daily';

    /** A visitor's next view more than this many minutes later starts a new visit. */
    public const VISIT_GAP_MINUTES = 30;

    /**
     * Rebuild every scope's days from $from on. With $blogIds, blogs and posts are
     * rebuilt only for those blogs; the site and platform are always rebuilt whole.
     *
     * @param  string  $from  Y-m-d, read as UTC for the site and platform, in each blog's timezone for blogs and posts
     * @param  list<int>|null  $blogIds  The blogs whose views changed, or null for all of them
     * @return array{daily: int, dimensions: int} Rows written
     */
    public function rebuildFrom(string $from, int $readSeconds, int $bounceSeconds, ?array $blogIds = null): array
    {
        return $this->transaction(function () use ($from, $readSeconds, $bounceSeconds, $blogIds): array {
            $written = ['daily' => 0, 'dimensions' => 0];

            foreach (array_keys(TrafficSql::scopes()) as $scope) {
                $only = in_array($scope, [TrafficScope::BLOG, TrafficScope::POST], true) ? $blogIds : null;
                if ($only === []) {
                    continue;
                }

                [$blogFilter, $blogParams] = self::blogFilter($only, 'blog_id');
                $this->database->execute(
                    "DELETE FROM traffic_daily WHERE scope = ? AND day >= ?{$blogFilter}",
                    [$scope, $from, ...$blogParams]
                );
                $this->database->execute(
                    "DELETE FROM traffic_daily_dimensions WHERE scope = ? AND day >= ?{$blogFilter}",
                    [$scope, $from, ...$blogParams]
                );

                $written['daily'] += $this->insertTotals($scope, $from, $readSeconds, $bounceSeconds, $only);
                $written['dimensions'] += $this->insertBreakdowns($scope, $from, $readSeconds, $only);
            }

            return $written;
        });
    }

    /**
     * Remove a deleted blog's own numbers: its totals, goals and clicks, missing
     * pages and spike notices. Its views stay in the site's, and so do sign-ups
     * that started on it.
     */
    public function deleteByBlogId(int $blogId): void
    {
        $this->database->execute("DELETE FROM traffic_daily WHERE scope IN ('blog', 'post') AND blog_id = ?", [$blogId]);
        $this->database->execute('DELETE FROM traffic_daily_dimensions WHERE blog_id = ?', [$blogId]);
        $this->database->execute("DELETE FROM traffic_events WHERE blog_id = ? AND event <> 'signup'", [$blogId]);
        $this->database->execute('DELETE FROM traffic_not_found WHERE blog_id = ?', [$blogId]);
        $this->database->execute('DELETE FROM traffic_spike_notices WHERE blog_id = ?', [$blogId]);
    }

    /**
     * One row per scope id and day. Visitors are counted per day, so a person
     * reading on Monday and Tuesday is a visitor on each day.
     *
     * @param  list<int>|null  $blogIds
     */
    private function insertTotals(string $scope, string $from, int $readSeconds, int $bounceSeconds, ?array $blogIds): int
    {
        $s = TrafficSql::scopes()[$scope];
        $r = TrafficSql::scopes()[$s['returningIn']];
        $keys = TrafficSql::groupKeys([$s['id'], $s['blog']]);
        $returningKeys = TrafficSql::groupKeys([$r['id']]);
        $bounces = $s['bounces'] ? "SUM(v.views = 1 AND v.best_seconds < {$bounceSeconds})" : '0';
        [$blogFilter, $blogParams] = self::blogFilter($blogIds, 'h.blog_id');

        $steps = '';
        foreach ([25, 50, 75, 100] as $step) {
            $steps .= ", COALESCE(SUM(h.engaged_seconds IS NOT NULL AND h.scroll_depth >= {$step}), 0) AS scroll_{$step}";
        }

        $sql = "INSERT INTO traffic_daily
                    (scope, scope_id, blog_id, day, views, visitors, identified_visitors, returning_visitors,
                     bounces, read_views, engaged_views, engaged_seconds, scroll_depth_sum,
                     scroll_25, scroll_50, scroll_75, scroll_100)
                WITH first_seen AS (
                    SELECT h.visitor_hash, {$r['id']} AS seen_in, MIN({$r['day']}) AS first_day
                    FROM traffic_hits h
                    JOIN (SELECT DISTINCT h.visitor_hash FROM traffic_hits h
                          WHERE {$r['since']} AND {$r['covers']}{$blogFilter} AND h.visitor_kind <> 'daily') w
                      ON w.visitor_hash = h.visitor_hash
                    WHERE {$r['covers']}
                    GROUP BY {$returningKeys}h.visitor_hash
                ),
                per_visitor AS (
                    SELECT {$s['id']} AS scope_id, {$s['blog']} AS blog_id, {$s['day']} AS day,
                           {$r['id']} AS seen_in, h.visitor_hash,
                           COUNT(*) AS views,
                           MAX(h.visitor_kind <> 'daily') AS identified,
                           MAX(COALESCE(h.engaged_seconds, 0)) AS best_seconds,
                           COALESCE(SUM(h.engaged_seconds >= {$readSeconds}), 0) AS read_views,
                           SUM(h.engaged_seconds IS NOT NULL) AS engaged_views,
                           COALESCE(SUM(h.engaged_seconds), 0) AS engaged_seconds,
                           COALESCE(SUM(CASE WHEN h.engaged_seconds IS NOT NULL THEN h.scroll_depth END), 0)
                               AS scroll_sum{$steps}
                    FROM traffic_hits h
                    WHERE {$s['since']} AND {$s['covers']}{$blogFilter}
                    GROUP BY {$keys}{$s['day']}, h.visitor_hash
                )
                SELECT '{$scope}', v.scope_id, v.blog_id, v.day,
                       SUM(v.views), COUNT(*), SUM(v.identified),
                       SUM(v.identified = 1 AND f.first_day < v.day),
                       {$bounces},
                       SUM(v.read_views), SUM(v.engaged_views), SUM(v.engaged_seconds), SUM(v.scroll_sum),
                       SUM(v.scroll_25), SUM(v.scroll_50), SUM(v.scroll_75), SUM(v.scroll_100)
                FROM per_visitor v
                LEFT JOIN first_seen f ON f.visitor_hash = v.visitor_hash AND f.seen_in = v.seen_in
                GROUP BY v.scope_id, v.blog_id, v.day";

        return $this->database->execute($sql, [$from, ...$blogParams, $from, ...$blogParams]);
    }

    /**
     * @param  list<int>|null  $blogIds
     */
    private function insertBreakdowns(string $scope, string $from, int $readSeconds, ?array $blogIds): int
    {
        $s = TrafficSql::scopes()[$scope];
        [$blogFilter, $blogParams] = self::blogFilter($blogIds, 'h.blog_id');
        $selects = [];
        $params = [];

        foreach ([...TrafficScope::breakdownsFor($scope), ...$s['stored']] as $dimension) {
            $id = $s['ids'][$dimension]['id'] ?? $s['id'];
            $covers = ($s['ids'][$dimension]['covers'] ?? $s['covers']).$blogFilter;
            $keys = TrafficSql::groupKeys([$id, $s['blog']]);
            $column = TrafficSql::column($scope, $dimension);
            $views = 'traffic_hits h';

            if ($dimension === 'exit') {
                $views = TrafficSql::withExits("{$s['since']} AND {$covers}", $s['visit'], self::VISIT_GAP_MINUTES);
                array_push($params, $from, ...$blogParams);
            }

            $selects[] = "SELECT '{$scope}', {$id}, {$s['blog']}, '{$dimension}', {$s['day']}, {$column},
                                 COUNT(*), COUNT(DISTINCT h.visitor_hash),
                                 COALESCE(SUM(h.engaged_seconds >= {$readSeconds}), 0),
                                 SUM(h.engaged_seconds IS NOT NULL),
                                 COALESCE(SUM(h.engaged_seconds), 0)
                          FROM {$views} ".TrafficSql::join($dimension)."
                          WHERE {$s['since']} AND {$covers} AND ".TrafficSql::filter($scope, $dimension)."
                          GROUP BY {$keys}{$s['day']}, {$column}";
            array_push($params, $from, ...$blogParams);
        }

        $sql = 'INSERT INTO traffic_daily_dimensions
                    (scope, scope_id, blog_id, dimension, day, value, views, visitors, read_views, engaged_views, engaged_seconds) '
            .implode(' UNION ALL ', $selects);

        return $this->database->execute($sql, $params);
    }

    /**
     * @param  list<int>|null  $blogIds
     * @return array{0: string, 1: list<int>} SQL to append, starting with AND, and its parameters
     */
    private static function blogFilter(?array $blogIds, string $column): array
    {
        if ($blogIds === null) {
            return ['', []];
        }

        $ids = array_map('intval', $blogIds);

        return [' AND '.$column.' IN ('.implode(', ', array_fill(0, count($ids), '?')).')', $ids];
    }
}
