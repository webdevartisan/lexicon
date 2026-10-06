<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Analytics\EventRegistry;
use App\ValueObjects\AnalyticsScope;

/**
 * Builds the daily tables from the raw events, one scope at a time.
 *
 * Each scope's days are deleted and re-inserted in one transaction, so a re-run
 * gives identical rows and a day that lost views also loses their breakdown rows.
 * Views flagged as script-like count nowhere. What each scope and breakdown
 * means lives in AnalyticsSql.
 */
class AnalyticsRollupModel extends AppModel
{
    protected ?string $table = 'analytics_daily';

    /** A visitor's next view more than this many minutes later starts a new visit. */
    public const VISIT_GAP_MINUTES = 30;

    /**
     * Where each kept event's daily rows go, besides the event's own columns.
     * Goals and clicks are kept per blog, per post and for the platform; the
     * site adds those up. Sign-ups belong to the site and to the blog in came_from.
     */
    private const EVENT_SCOPES = [
        AnalyticsScope::PLATFORM => ['id' => '0', 'blog' => 'NULL', 'covers' => "e.blog_id IS NULL AND e.name <> 'signup'"],
        AnalyticsScope::BLOG => ['id' => 'e.blog_id', 'blog' => 'e.blog_id', 'covers' => "e.blog_id IS NOT NULL AND e.name <> 'signup'"],
        AnalyticsScope::POST => ['id' => 'e.post_id', 'blog' => 'e.blog_id', 'covers' => "e.post_id IS NOT NULL AND e.name <> 'signup'"],
        AnalyticsScope::SITE => ['id' => '0', 'blog' => 'NULL', 'covers' => "e.name = 'signup'"],
    ];

    /** The blog a sign-up's reader was last on, read from came_from (blog:{id}). */
    private const SIGNUP_BLOG = "CAST(SUBSTRING(e.props->>'$.came_from', 6) AS UNSIGNED)";

    public function __construct(\Framework\Database $database, private EventRegistry $registry)
    {
        parent::__construct($database);
    }

    /**
     * Rebuild every scope's days from $from on. With $blogIds, blogs and posts are
     * rebuilt only for those blogs; the site and platform are always rebuilt whole.
     *
     * @param  string  $from  Y-m-d, read as UTC for the site and platform, in each blog's timezone for blogs and posts
     * @param  list<int>|null  $blogIds  The blogs whose events changed, or null for all of them
     * @return array{daily: int, dimensions: int, events: int} Rows written
     */
    public function rebuildFrom(string $from, int $readSeconds, int $engagedSeconds, ?array $blogIds = null): array
    {
        return $this->transaction(function () use ($from, $readSeconds, $engagedSeconds, $blogIds): array {
            $written = ['daily' => 0, 'dimensions' => 0, 'events' => 0];

            foreach (array_keys(AnalyticsSql::scopes()) as $scope) {
                $only = in_array($scope, [AnalyticsScope::BLOG, AnalyticsScope::POST], true) ? $blogIds : null;
                if ($only === []) {
                    continue;
                }

                [$blogFilter, $blogParams] = self::blogFilter($only, 'blog_id');
                foreach (['analytics_daily', 'analytics_daily_dimensions', 'analytics_daily_events'] as $table) {
                    $this->database->execute(
                        "DELETE FROM {$table} WHERE scope = ? AND day >= ?{$blogFilter}",
                        [$scope, $from, ...$blogParams]
                    );
                }

                $written['daily'] += $this->insertTotals($scope, $from, $readSeconds, $only);
                $this->addVisits($scope, $from, $engagedSeconds, $only);
                $written['dimensions'] += $this->insertBreakdowns($scope, $from, $readSeconds, $only);
                $written['events'] += $this->insertEvents($scope, $from, $only);
            }

            return $written;
        });
    }

    /**
     * Remove a deleted blog's own numbers: its totals, breakdowns and events. Its
     * views stay in the site's, and so do sign-ups that started on it. Its goals
     * and clicks leave the site's totals, which add up the blogs' rows.
     */
    public function deleteByBlogId(int $blogId): void
    {
        foreach (['analytics_daily', 'analytics_daily_dimensions', 'analytics_daily_events'] as $table) {
            $this->database->execute("DELETE FROM {$table} WHERE scope IN ('blog', 'post') AND blog_id = ?", [$blogId]);
        }
    }

    /**
     * One row per scope id and day. Visitors are counted per day, so a person
     * reading on Monday and Tuesday is a visitor on each day.
     *
     * @param  list<int>|null  $blogIds
     */
    private function insertTotals(string $scope, string $from, int $readSeconds, ?array $blogIds): int
    {
        $s = AnalyticsSql::scopes()[$scope];
        $r = AnalyticsSql::scopes()[$s['returningIn']];
        $views = AnalyticsSql::VIEWS;
        $keys = AnalyticsSql::groupKeys([$s['id'], $s['blog']]);
        $returningKeys = AnalyticsSql::groupKeys([$r['id']]);
        [$blogFilter, $blogParams] = self::blogFilter($blogIds, 'h.blog_id');

        $steps = '';
        foreach ([25, 50, 75, 100] as $step) {
            $steps .= ", COALESCE(SUM(h.engaged_seconds IS NOT NULL AND h.scroll_depth >= {$step}), 0) AS scroll_{$step}";
        }

        $sql = "INSERT INTO analytics_daily
                    (scope, scope_id, blog_id, day, views, visitors, identified_visitors, returning_visitors,
                     read_views, read_to_end, engaged_views, engaged_seconds, scroll_depth_sum,
                     scroll_25, scroll_50, scroll_75, scroll_100)
                WITH first_seen AS (
                    SELECT h.visitor_hash, {$r['id']} AS seen_in, MIN({$r['day']}) AS first_day
                    FROM {$views} h
                    JOIN (SELECT DISTINCT h.visitor_hash FROM {$views} h
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
                           COALESCE(SUM(h.engaged_seconds >= {$readSeconds}), 0) AS read_views,
                           COALESCE(SUM(h.engaged_seconds >= {$readSeconds} AND h.scroll_depth >= 100), 0) AS read_to_end,
                           SUM(h.engaged_seconds IS NOT NULL) AS engaged_views,
                           COALESCE(SUM(h.engaged_seconds), 0) AS engaged_seconds,
                           COALESCE(SUM(CASE WHEN h.engaged_seconds IS NOT NULL THEN h.scroll_depth END), 0)
                               AS scroll_sum{$steps}
                    FROM {$views} h
                    WHERE {$s['since']} AND {$s['covers']}{$blogFilter}
                    GROUP BY {$keys}{$s['day']}, h.visitor_hash
                )
                SELECT '{$scope}', v.scope_id, v.blog_id, v.day,
                       SUM(v.views), COUNT(*), SUM(v.identified),
                       SUM(v.identified = 1 AND f.first_day < v.day),
                       SUM(v.read_views), SUM(v.read_to_end), SUM(v.engaged_views), SUM(v.engaged_seconds),
                       SUM(v.scroll_sum), SUM(v.scroll_25), SUM(v.scroll_50), SUM(v.scroll_75), SUM(v.scroll_100)
                FROM per_visitor v
                LEFT JOIN first_seen f ON f.visitor_hash = v.visitor_hash AND f.seen_in = v.seen_in
                GROUP BY v.scope_id, v.blog_id, v.day";

        return $this->database->execute($sql, [$from, ...$blogParams, $from, ...$blogParams]);
    }

    /**
     * Fill in the visit numbers on the rows insertTotals just wrote.
     *
     * @param  list<int>|null  $blogIds
     */
    private function addVisits(string $scope, string $from, int $engagedSeconds, ?array $blogIds): void
    {
        $s = AnalyticsSql::scopes()[$scope];
        [$blogFilter, $blogParams] = self::blogFilter($blogIds, 'h.blog_id');
        [$segments, $params] = AnalyticsSql::visitSegments(
            $scope,
            "{$s['since']} AND {$s['covers']}{$blogFilter}",
            [$from, ...$blogParams],
            $engagedSeconds,
            $from
        );

        $this->database->execute(
            "UPDATE analytics_daily d
             JOIN ({$segments}) v ON v.scope_id = d.scope_id AND v.day = d.day
             SET d.visits = v.visits, d.engaged_visits = v.engaged_visits,
                 d.visit_pages = v.visit_pages, d.visit_seconds = v.visit_seconds
             WHERE d.scope = ?",
            [...$params, $scope]
        );
    }

    /**
     * @param  list<int>|null  $blogIds
     */
    private function insertBreakdowns(string $scope, string $from, int $readSeconds, ?array $blogIds): int
    {
        $s = AnalyticsSql::scopes()[$scope];
        [$blogFilter, $blogParams] = self::blogFilter($blogIds, 'h.blog_id');
        $selects = [];
        $params = [];

        foreach ([...AnalyticsScope::breakdownsFor($scope), ...$s['stored']] as $dimension) {
            $id = $s['ids'][$dimension]['id'] ?? $s['id'];
            $covers = ($s['ids'][$dimension]['covers'] ?? $s['covers']).$blogFilter;
            $keys = AnalyticsSql::groupKeys([$id, $s['blog']]);
            $column = AnalyticsSql::column($scope, $dimension);
            $views = AnalyticsSql::VIEWS.' h';

            if ($dimension === 'exit') {
                $views = AnalyticsSql::withExits("{$s['since']} AND {$covers}", $s['visit'], self::VISIT_GAP_MINUTES);
                array_push($params, $from, ...$blogParams);
            }

            $selects[] = "SELECT '{$scope}', {$id}, {$s['blog']}, '{$dimension}', {$s['day']}, {$column},
                                 COUNT(*), COUNT(DISTINCT h.visitor_hash),
                                 COALESCE(SUM(h.engaged_seconds >= {$readSeconds}), 0),
                                 SUM(h.engaged_seconds IS NOT NULL),
                                 COALESCE(SUM(h.engaged_seconds), 0)
                          FROM {$views} ".AnalyticsSql::join($dimension)."
                          WHERE {$s['since']} AND {$covers} AND ".AnalyticsSql::filter($scope, $dimension)."
                          GROUP BY {$keys}{$s['day']}, {$column}";
            array_push($params, $from, ...$blogParams);
        }

        $sql = 'INSERT INTO analytics_daily_dimensions
                    (scope, scope_id, blog_id, dimension, day, value, views, visitors, read_views, engaged_views, engaged_seconds) '
            .implode(' UNION ALL ', $selects);

        return $this->database->execute($sql, $params);
    }

    /**
     * Daily counts of the kept events (goals, clicks, sign-ups): a total per event,
     * plus one row per value of each breakdown the event lists.
     *
     * @param  list<int>|null  $blogIds
     */
    private function insertEvents(string $scope, string $from, ?array $blogIds): int
    {
        $s = self::EVENT_SCOPES[$scope];
        [$blogFilter, $blogParams] = self::blogFilter($blogIds, 'e.blog_id');
        $selects = [];
        $params = [];

        foreach ($this->eventBreakdowns($this->registry->kept()) as [$breakdown, $value, $names]) {
            $selects[] = $this->eventSelect($scope, $s['id'], $s['blog'], $breakdown, $value, $names, "{$s['covers']}{$blogFilter}");
            array_push($params, $from, ...$names, ...$blogParams);
        }

        if ($scope === AnalyticsScope::BLOG) {
            // A sign-up also counts for the blog its reader was last on.
            [$signupFilter, $signupParams] = self::blogFilter($blogIds, self::SIGNUP_BLOG);
            foreach ($this->eventBreakdowns(['signup']) as [$breakdown, $value, $names]) {
                $selects[] = $this->eventSelect(
                    $scope,
                    self::SIGNUP_BLOG,
                    self::SIGNUP_BLOG,
                    $breakdown,
                    $value,
                    $names,
                    "e.props->>'$.came_from' LIKE 'blog:%' AND EXISTS (SELECT 1 FROM blogs b WHERE b.id = ".self::SIGNUP_BLOG."){$signupFilter}"
                );
                array_push($params, $from, ...$names, ...$signupParams);
            }
        }

        if ($selects === []) {
            return 0;
        }

        return $this->database->execute(
            'INSERT INTO analytics_daily_events (scope, scope_id, blog_id, day, name, breakdown, value, events, visits) '
            .implode(' UNION ALL ', $selects),
            $params
        );
    }

    /**
     * One SELECT of daily event counts. Takes the day, then one placeholder per
     * name, then whatever $covers takes.
     *
     * @param  list<string>  $names
     */
    private function eventSelect(string $scope, string $id, string $blog, string $breakdown, string $value, array $names, string $covers): string
    {
        $placeholders = implode(', ', array_fill(0, count($names), '?'));
        $keys = AnalyticsSql::groupKeys([$id, $blog]);

        return "SELECT '{$scope}', {$id}, {$blog}, e.local_date, e.name, '{$breakdown}', {$value},
                       COUNT(*), IF(COUNT(e.visit_id) = 0, NULL, COUNT(DISTINCT e.visit_id))
                FROM analytics_events e
                WHERE e.local_date >= ? AND e.name IN ({$placeholders}) AND {$covers} AND {$value} IS NOT NULL
                GROUP BY {$keys}e.local_date, e.name, {$value}";
    }

    /**
     * The total and every breakdown of the given events: the breakdown name, the
     * SQL that reads its value, and the events that list it.
     *
     * @param  list<string>  $events
     * @return list<array{0: string, 1: string, 2: list<string>}>
     */
    private function eventBreakdowns(array $events): array
    {
        if ($events === []) {
            return [];
        }

        $byBreakdown = [];
        foreach ($events as $event) {
            foreach ($this->registry->breakdowns($event) as $breakdown) {
                if (!preg_match('/^[a-z_]+$/', $breakdown)) {
                    throw new \LogicException("Breakdown '{$breakdown}' in config/analytics.php is not a plain name.");
                }
                $byBreakdown[$breakdown][] = $event;
            }
        }

        $rows = [['', "''", $events]];
        foreach ($byBreakdown as $breakdown => $names) {
            $value = isset(EventRegistry::COLUMN_BREAKDOWNS[$breakdown])
                ? 'e.'.EventRegistry::COLUMN_BREAKDOWNS[$breakdown]
                : "e.props->>'$.{$breakdown}'";
            $rows[] = [$breakdown, $value, $names];
        }

        return $rows;
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
