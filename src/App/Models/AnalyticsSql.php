<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Analytics\PlatformPages;
use App\ValueObjects\AnalyticsScope;

/**
 * How page views are read for each scope and breakdown, shared by the daily
 * rollup and by filtered reads of raw views, so both count the same way.
 * Fixed SQL fragments over the alias h, never input.
 */
final class AnalyticsSql
{
    /**
     * Breakdown => the expression it groups by.
     */
    private const COLUMNS = [
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
        'entry' => 'LEFT(h.path, 191)',
        'search_entry' => 'LEFT(h.path, 191)',
        'exit' => 'LEFT(h.path, 191)',
        'next' => 'LEFT(h.path, 191)',
        'related' => 'LEFT(h.path, 191)',
        'hour' => 'h.local_hour',
        'category' => 'p.category_id',
        'tag' => 'pt.tag_id',
        'author' => 'p.author_id',
    ];

    /**
     * Where a scope's days aren't the blog's, its breakdowns follow UTC too.
     */
    private const SCOPE_COLUMNS = [
        AnalyticsScope::SITE => [
            // Going from Discover to a blog is moving around the site.
            'channel' => "IF(h.channel = 'lexicon', 'internal', h.channel)",
            'hour' => 'HOUR(h.created_at)',
        ],
        AnalyticsScope::PLATFORM => ['hour' => 'HOUR(h.created_at)'],
    ];

    /**
     * Which views a breakdown counts, when not every view with a value.
     */
    private const FILTERS = [
        // Top pages lists the blog's other pages. Posts have their own table.
        'page' => "h.page_type <> 'post'",
        'source' => "h.referrer_source IS NOT NULL AND h.channel <> 'lexicon'",
        'lexicon' => "h.channel = 'lexicon'",
        // Arriving from outside the scope starts a visit to it, including from elsewhere on Lexicon.
        'entry' => "h.channel <> 'internal'",
        'search_entry' => "h.channel = 'search'",
        'exit' => 'h.is_exit = 1',
        // Posts opened from another post's related links, which the page script marks.
        'related' => "h.props->>'$.via' = 'related'",
    ];

    /**
     * Breakdowns that need the post a view is of. A view of a post with three
     * tags counts once for each tag.
     */
    private const JOINS = [
        'category' => 'JOIN posts p ON p.id = h.post_id',
        'tag' => 'JOIN post_tags pt ON pt.post_id = h.post_id',
        'author' => 'JOIN posts p ON p.id = h.post_id',
    ];

    /**
     * Narrowing raw views to one breakdown value, as a condition with one placeholder.
     * Post attributes are subqueries so a filter never multiplies rows.
     */
    private const MATCHES = [
        'category' => 'h.post_id IN (SELECT id FROM posts WHERE category_id = ?)',
        'tag' => 'h.post_id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)',
        'author' => 'h.post_id IN (SELECT id FROM posts WHERE author_id = ?)',
    ];

    /** Breakdowns a page can be narrowed to by clicking a row. */
    public const FILTERABLE = [
        'channel', 'source', 'lexicon', 'utm_source', 'utm_medium', 'utm_campaign', 'device', 'browser', 'os',
        'country', 'locale', 'page', 'category', 'tag', 'author',
    ];

    public static function column(string $scope, string $dimension): string
    {
        return self::SCOPE_COLUMNS[$scope][$dimension]
            ?? self::COLUMNS[$dimension]
            ?? throw new \InvalidArgumentException("Unknown analytics breakdown '{$dimension}'.");
    }

    public static function filter(string $scope, string $dimension): string
    {
        return self::FILTERS[$dimension] ?? self::column($scope, $dimension).' IS NOT NULL';
    }

    public static function join(string $dimension): string
    {
        return self::JOINS[$dimension] ?? '';
    }

    /**
     * The condition that keeps only views with $dimension = ?.
     */
    public static function match(string $scope, string $dimension): string
    {
        if (!in_array($dimension, self::FILTERABLE, true)) {
            throw new \InvalidArgumentException("Views can't be filtered by '{$dimension}'.");
        }

        if (isset(self::MATCHES[$dimension])) {
            return self::MATCHES[$dimension];
        }

        $rows = self::FILTERS[$dimension] ?? null;

        return self::column($scope, $dimension).' = ?'.($rows === null ? '' : " AND {$rows}");
    }

    /**
     * Every page view with the visit fields it is broken down by. MySQL merges this
     * into the outer query, so conditions on h still use the event table's indexes.
     */
    public const VIEWS = "(SELECT e.*, v.device, v.browser, v.os, v.country
                           FROM analytics_events e
                           LEFT JOIN analytics_visits v ON v.id = e.visit_id
                           WHERE e.name = 'page_view')";

    /**
     * Events that make a visit engaged on their own, the way a key event does in GA4.
     */
    public const ENGAGING_EVENTS = ['subscribe', 'comment', 'like', 'save', 'share', 'signup'];

    /**
     * How each scope reads page views.
     *
     * - id, blog: the row's scope_id and blog_id
     * - day, since, range: the day a view falls on, "from this day on", and "between these days"
     * - covers: which views belong to the scope
     * - visit: what a visit to the scope is split by besides the visit itself, for exits
     * - returningIn: the scope an earlier visit has to be in for a visitor to count as returning
     * - engagedBy: which views decide whether a visit counted here was engaged:
     *   its own (scope), the whole visit (visit), or the visit's time on the blog (blog)
     * - stored: breakdowns kept beyond the ones the scope shows
     * - ids: breakdowns whose rows belong to another post than the view, with what decides it
     *
     * @return array<string, array{id: string, blog: string, day: string, since: string, range: string,
     *     covers: string, visit: string, returningIn: string, engagedBy: 'scope'|'visit'|'blog', stored: list<string>,
     *     ids: array<string, array{id: string, covers: string}>}>
     */
    public static function scopes(): array
    {
        $platformTypes = "'".implode("', '", PlatformPages::PAGE_TYPES)."'";
        $utcDays = [
            'day' => 'DATE(h.created_at)',
            'since' => 'h.created_at >= ?',
            'range' => 'h.created_at >= ? AND h.created_at < ? + INTERVAL 1 DAY',
        ];
        $blogDays = [
            'day' => 'h.local_date',
            'since' => 'h.local_date >= ?',
            'range' => 'h.local_date BETWEEN ? AND ?',
        ];

        return [
            AnalyticsScope::SITE => [
                'id' => '0',
                'blog' => 'NULL',
                ...$utcDays,
                'covers' => 'h.suspect = 0',
                'visit' => '',
                'returningIn' => AnalyticsScope::SITE,
                'engagedBy' => 'scope',
                // Views per blog, for the number of blogs read.
                'stored' => ['blog'],
                'ids' => [],
            ],
            AnalyticsScope::PLATFORM => [
                'id' => '0',
                'blog' => 'NULL',
                ...$utcDays,
                'covers' => "h.suspect = 0 AND h.page_type IN ({$platformTypes})",
                'visit' => '',
                'returningIn' => AnalyticsScope::PLATFORM,
                // Going on from the home page to a blog isn't leaving, so the whole visit decides.
                'engagedBy' => 'visit',
                'stored' => [],
                'ids' => [],
            ],
            AnalyticsScope::BLOG => [
                'id' => 'h.blog_id',
                'blog' => 'h.blog_id',
                ...$blogDays,
                'covers' => 'h.suspect = 0 AND h.blog_id IS NOT NULL',
                'visit' => ', h.blog_id',
                'returningIn' => AnalyticsScope::BLOG,
                'engagedBy' => 'scope',
                'stored' => [],
                'ids' => [],
            ],
            AnalyticsScope::POST => [
                'id' => 'h.post_id',
                'blog' => 'h.blog_id',
                ...$blogDays,
                'covers' => 'h.suspect = 0 AND h.post_id IS NOT NULL',
                'visit' => '',
                // Back on the blog, so a post page and its blog agree on who returned.
                'returningIn' => AnalyticsScope::BLOG,
                // An engaged visit is a visit to the blog, not to one post.
                'engagedBy' => 'blog',
                'stored' => [],
                // Where readers went next, and which related link they took, belongs to the post they left.
                'ids' => [
                    'next' => ['id' => 'h.from_post_id', 'covers' => 'h.suspect = 0 AND h.from_post_id IS NOT NULL'],
                    'related' => ['id' => 'h.from_post_id', 'covers' => 'h.suspect = 0 AND h.from_post_id IS NOT NULL'],
                ],
            ],
        ];
    }

    /**
     * The scope's views, each marked is_exit when the visit's next view in the
     * scope came more than $gapMinutes later, or never. Takes one placeholder per
     * placeholder in $where.
     */
    public static function withExits(string $where, string $visit, int $gapMinutes): string
    {
        $next = "LEAD(h.created_at) OVER (PARTITION BY h.visit_id{$visit} ORDER BY h.created_at, h.id)";

        return "(SELECT h.*, {$next} IS NULL OR {$next} > h.created_at + INTERVAL {$gapMinutes} MINUTE AS is_exit
                 FROM ".self::VIEWS." h
                 WHERE {$where}) h";
    }

    /**
     * Visits per scope id and day: how many reached the scope (counted on the day
     * they first did), how many were engaged, their page views and their length.
     * A visit is engaged with 2+ page views, $engagedSeconds or more engaged time,
     * or one of ENGAGING_EVENTS, judged over the views engagedBy names.
     *
     * $where selects the scope's views and takes $params; $widerSince bounds the
     * wider read for platform and post scopes (UTC, Y-m-d).
     *
     * @param  list<int|string>  $params
     * @return array{0: string, 1: list<int|string>} SQL selecting scope_id, blog_id, day, visits,
     *                                               engaged_visits, visit_pages, visit_seconds; and its parameters
     */
    public static function visitSegments(string $scope, string $where, array $params, int $engagedSeconds, string $widerSince): array
    {
        $s = self::scopes()[$scope];
        $keys = self::groupKeys([$s['id'], $s['blog']]);
        $goals = "'".implode("', '", self::ENGAGING_EVENTS)."'";

        $segments = "SELECT {$s['id']} AS scope_id, {$s['blog']} AS blog_id, h.visit_id, MIN({$s['day']}) AS day,
                            COUNT(*) AS pages, COALESCE(SUM(h.engaged_seconds), 0) AS seconds,
                            TIMESTAMPDIFF(SECOND, MIN(h.created_at), MAX(COALESCE(h.engaged_at, h.created_at))) AS length
                     FROM ".self::VIEWS." h
                     WHERE {$where} AND h.visit_id IS NOT NULL
                     GROUP BY {$keys}h.visit_id";

        // The views that judge each visit: the segment itself, or a wider read joined as w.
        [$judge, $judgeParams, $join] = match ($s['engagedBy']) {
            'scope' => ['seg', [], ''],
            'visit' => ['w', [$widerSince], 'JOIN (SELECT h.visit_id, NULL AS blog_id, COUNT(*) AS pages,
                                                         COALESCE(SUM(h.engaged_seconds), 0) AS seconds
                                                  FROM '.self::VIEWS.' h
                                                  WHERE h.created_at >= ? - INTERVAL 1 DAY AND h.suspect = 0
                                                    AND h.visit_id IS NOT NULL
                                                  GROUP BY h.visit_id) w ON w.visit_id = seg.visit_id'],
            'blog' => ['w', [$widerSince], 'JOIN (SELECT h.visit_id, h.blog_id, COUNT(*) AS pages,
                                                        COALESCE(SUM(h.engaged_seconds), 0) AS seconds
                                                 FROM '.self::VIEWS.' h
                                                 WHERE h.created_at >= ? - INTERVAL 1 DAY AND h.suspect = 0
                                                   AND h.blog_id IS NOT NULL AND h.visit_id IS NOT NULL
                                                 GROUP BY h.visit_id, h.blog_id) w
                                             ON w.visit_id = seg.visit_id AND w.blog_id = seg.blog_id'],
        };
        $sameBlog = in_array($scope, [AnalyticsScope::BLOG, AnalyticsScope::POST], true) ? " AND g.blog_id = {$judge}.blog_id" : '';
        $engaged = "({$judge}.pages >= 2 OR {$judge}.seconds >= {$engagedSeconds}
                     OR EXISTS (SELECT 1 FROM analytics_events g
                                WHERE g.visit_id = {$judge}.visit_id AND g.name IN ({$goals}){$sameBlog}))";

        $sql = "SELECT seg.scope_id, seg.blog_id, seg.day, COUNT(*) AS visits, SUM({$engaged}) AS engaged_visits,
                       SUM(seg.pages) AS visit_pages, SUM(seg.length) AS visit_seconds
                FROM ({$segments}) seg
                {$join}
                GROUP BY seg.scope_id, seg.blog_id, seg.day";

        return [$sql, [...$params, ...$judgeParams]];
    }

    /**
     * The non-constant keys to group by, each followed by a comma. Constants are
     * left out because MySQL reads a bare number in GROUP BY as a column position.
     *
     * @param  list<string>  $expressions
     */
    public static function groupKeys(array $expressions): string
    {
        $keys = array_filter(
            array_unique($expressions),
            static fn (string $expression): bool => !in_array($expression, ['0', 'NULL'], true)
        );

        return $keys === [] ? '' : implode(', ', $keys).', ';
    }
}
