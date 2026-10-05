<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Traffic\PlatformPages;
use App\ValueObjects\TrafficScope;

/**
 * Builds the daily traffic tables from traffic_hits, one scope at a time.
 *
 * Each scope's days are deleted and re-inserted in one transaction, so a re-run
 * gives identical rows and a day that lost views also loses their breakdown rows.
 */
class TrafficRollupModel extends AppModel
{
    protected ?string $table = 'traffic_daily';

    /**
     * Breakdown => the traffic_hits expression it groups by. Fixed strings, never input.
     */
    private const DIMENSION_COLUMNS = [
        'channel' => 'h.channel',
        'source' => 'h.referrer_source',
        'utm_source' => 'h.utm_source',
        'utm_medium' => 'h.utm_medium',
        'utm_campaign' => 'h.utm_campaign',
        'device' => 'h.device',
        'browser' => 'h.browser',
        'os' => 'h.os',
        'country' => 'h.country',
        'locale' => 'h.locale',
        'page' => 'LEFT(h.path, 191)',
        'blog' => 'h.blog_id',
        'lexicon' => 'h.referrer_source',
    ];

    /**
     * Which views a breakdown counts, when not every view with a value.
     */
    private const DIMENSION_FILTERS = [
        // Top pages lists the blog's other pages. Posts have their own table.
        'page' => "h.page_type <> 'post'",
        'source' => "h.referrer_source IS NOT NULL AND h.channel <> 'lexicon'",
        'lexicon' => "h.channel = 'lexicon'",
    ];

    /**
     * How each scope reads traffic_hits. Fixed strings, never input.
     *
     * - id, blog: the row's scope_id and blog_id
     * - day, since: the day a view falls on, and the matching "from this day on" test
     * - covers: which views belong to the scope
     * - returningIn: the scope an earlier visit has to be in for a visitor to count as returning
     * - bounces: whether the scope counts bounces
     * - stored: breakdowns kept beyond the ones the scope shows
     * - columns: breakdowns that group by something other than DIMENSION_COLUMNS
     *
     * @return array<string, array{id: string, blog: string, day: string, since: string, covers: string,
     *     returningIn: string, bounces: bool, stored: list<string>, columns: array<string, string>}>
     */
    private static function scopes(): array
    {
        $platformTypes = "'".implode("', '", PlatformPages::PAGE_TYPES)."'";

        return [
            TrafficScope::SITE => [
                'id' => '0',
                'blog' => 'NULL',
                'day' => 'DATE(h.created_at)',
                'since' => 'h.created_at >= ?',
                'covers' => 'TRUE',
                'returningIn' => TrafficScope::SITE,
                'bounces' => true,
                // Views per blog, for the number of blogs read.
                'stored' => ['blog'],
                // Going from Discover to a blog is moving around the site.
                'columns' => ['channel' => "IF(h.channel = 'lexicon', 'internal', h.channel)"],
            ],
            TrafficScope::PLATFORM => [
                'id' => '0',
                'blog' => 'NULL',
                'day' => 'DATE(h.created_at)',
                'since' => 'h.created_at >= ?',
                'covers' => "h.page_type IN ({$platformTypes})",
                'returningIn' => TrafficScope::PLATFORM,
                // Going on from the home page to a blog isn't leaving, so one view here says nothing.
                'bounces' => false,
                'stored' => [],
                'columns' => [],
            ],
            TrafficScope::BLOG => [
                'id' => 'h.blog_id',
                'blog' => 'h.blog_id',
                'day' => 'h.local_date',
                'since' => 'h.local_date >= ?',
                'covers' => 'h.blog_id IS NOT NULL',
                'returningIn' => TrafficScope::BLOG,
                'bounces' => true,
                'stored' => [],
                'columns' => [],
            ],
            TrafficScope::POST => [
                'id' => 'h.post_id',
                'blog' => 'h.blog_id',
                'day' => 'h.local_date',
                'since' => 'h.local_date >= ?',
                'covers' => 'h.post_id IS NOT NULL',
                // Back on the blog, so a post page and its blog agree on who returned.
                'returningIn' => TrafficScope::BLOG,
                // A bounce is a visit to the blog, not to one post.
                'bounces' => false,
                'stored' => [],
                'columns' => [],
            ],
        ];
    }

    /**
     * Rebuild every scope's days from $from on.
     *
     * @param  string  $from  Y-m-d, read as UTC for the site and platform, in each blog's timezone for blogs and posts
     * @return array{daily: int, dimensions: int} Rows written
     */
    public function rebuildFrom(string $from, int $readSeconds, int $bounceSeconds): array
    {
        return $this->transaction(function () use ($from, $readSeconds, $bounceSeconds): array {
            $written = ['daily' => 0, 'dimensions' => 0];

            foreach (array_keys(self::scopes()) as $scope) {
                $this->database->execute('DELETE FROM traffic_daily WHERE scope = ? AND day >= ?', [$scope, $from]);
                $this->database->execute(
                    'DELETE FROM traffic_daily_dimensions WHERE scope = ? AND day >= ?',
                    [$scope, $from]
                );

                $written['daily'] += $this->insertTotals($scope, $from, $readSeconds, $bounceSeconds);
                $written['dimensions'] += $this->insertBreakdowns($scope, $from);
            }

            return $written;
        });
    }

    /**
     * Remove a deleted blog's own numbers. Its views stay in the site's.
     */
    public function deleteByBlogId(int $blogId): void
    {
        $this->database->execute("DELETE FROM traffic_daily WHERE scope IN ('blog', 'post') AND blog_id = ?", [$blogId]);
        $this->database->execute('DELETE FROM traffic_daily_dimensions WHERE blog_id = ?', [$blogId]);
    }

    /**
     * One row per scope id and day. Visitors are counted per day, so a person
     * reading on Monday and Tuesday is a visitor on each day.
     */
    private function insertTotals(string $scope, string $from, int $readSeconds, int $bounceSeconds): int
    {
        $s = self::scopes()[$scope];
        $r = self::scopes()[$s['returningIn']];
        $keys = self::groupKeys([$s['id'], $s['blog']]);
        $returningKeys = self::groupKeys([$r['id']]);
        $bounces = $s['bounces'] ? "SUM(v.views = 1 AND v.best_seconds < {$bounceSeconds})" : '0';

        $sql = "INSERT INTO traffic_daily
                    (scope, scope_id, blog_id, day, views, visitors, identified_visitors, returning_visitors,
                     bounces, read_views, engaged_views, engaged_seconds, scroll_depth_sum)
                WITH first_seen AS (
                    SELECT h.visitor_hash, {$r['id']} AS seen_in, MIN({$r['day']}) AS first_day
                    FROM traffic_hits h
                    JOIN (SELECT DISTINCT h.visitor_hash FROM traffic_hits h
                          WHERE {$r['since']} AND {$r['covers']} AND h.visitor_kind <> 'daily') w
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
                               AS scroll_sum
                    FROM traffic_hits h
                    WHERE {$s['since']} AND {$s['covers']}
                    GROUP BY {$keys}{$s['day']}, h.visitor_hash
                )
                SELECT '{$scope}', v.scope_id, v.blog_id, v.day,
                       SUM(v.views), COUNT(*), SUM(v.identified),
                       SUM(v.identified = 1 AND f.first_day < v.day),
                       {$bounces},
                       SUM(v.read_views), SUM(v.engaged_views), SUM(v.engaged_seconds), SUM(v.scroll_sum)
                FROM per_visitor v
                LEFT JOIN first_seen f ON f.visitor_hash = v.visitor_hash AND f.seen_in = v.seen_in
                GROUP BY v.scope_id, v.blog_id, v.day";

        return $this->database->execute($sql, [$from, $from]);
    }

    private function insertBreakdowns(string $scope, string $from): int
    {
        $s = self::scopes()[$scope];
        $keys = self::groupKeys([$s['id'], $s['blog']]);
        $selects = [];
        $params = [];

        foreach ([...TrafficScope::breakdownsFor($scope), ...$s['stored']] as $dimension) {
            $column = $s['columns'][$dimension] ?? self::DIMENSION_COLUMNS[$dimension];
            $filter = self::DIMENSION_FILTERS[$dimension] ?? "{$column} IS NOT NULL";

            $selects[] = "SELECT '{$scope}', {$s['id']}, {$s['blog']}, '{$dimension}', {$s['day']}, {$column},
                                 COUNT(*), COUNT(DISTINCT h.visitor_hash)
                          FROM traffic_hits h
                          WHERE {$s['since']} AND {$s['covers']} AND {$filter}
                          GROUP BY {$keys}{$s['day']}, {$column}";
            $params[] = $from;
        }

        $sql = 'INSERT INTO traffic_daily_dimensions (scope, scope_id, blog_id, dimension, day, value, views, visitors) '
            .implode(' UNION ALL ', $selects);

        return $this->database->execute($sql, $params);
    }

    /**
     * The non-constant keys to group by, each followed by a comma. Constants are
     * left out because MySQL reads a bare number in GROUP BY as a column position.
     *
     * @param  list<string>  $expressions
     */
    private static function groupKeys(array $expressions): string
    {
        $keys = array_filter(
            array_unique($expressions),
            static fn (string $expression): bool => !in_array($expression, ['0', 'NULL'], true)
        );

        return $keys === [] ? '' : implode(', ', $keys).', ';
    }
}
