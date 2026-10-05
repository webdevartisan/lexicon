<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Traffic\PlatformPages;
use App\ValueObjects\TrafficScope;

/**
 * How traffic_hits is read for each scope and breakdown, shared by the daily
 * rollup and by filtered reads of raw views, so both count the same way.
 * Fixed SQL fragments over the alias h, never input.
 */
final class TrafficSql
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
        'exit' => 'LEFT(h.path, 191)',
        'next' => 'LEFT(h.path, 191)',
        'hour' => 'h.local_hour',
        'category' => 'p.category_id',
        'tag' => 'pt.tag_id',
        'author' => 'p.author_id',
    ];

    /**
     * Where a scope's days aren't the blog's, its breakdowns follow UTC too.
     */
    private const SCOPE_COLUMNS = [
        TrafficScope::SITE => [
            // Going from Discover to a blog is moving around the site.
            'channel' => "IF(h.channel = 'lexicon', 'internal', h.channel)",
            'hour' => 'HOUR(h.created_at)',
        ],
        TrafficScope::PLATFORM => ['hour' => 'HOUR(h.created_at)'],
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
        'exit' => 'h.is_exit = 1',
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
            ?? throw new \InvalidArgumentException("Unknown traffic breakdown '{$dimension}'.");
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
            throw new \InvalidArgumentException("Traffic can't be filtered by '{$dimension}'.");
        }

        if (isset(self::MATCHES[$dimension])) {
            return self::MATCHES[$dimension];
        }

        $rows = self::FILTERS[$dimension] ?? null;

        return self::column($scope, $dimension).' = ?'.($rows === null ? '' : " AND {$rows}");
    }

    /**
     * How each scope reads traffic_hits.
     *
     * - id, blog: the row's scope_id and blog_id
     * - day, since, range: the day a view falls on, "from this day on", and "between these days"
     * - covers: which views belong to the scope
     * - visit: what a visit to the scope is partitioned by besides the visitor, for exits
     * - returningIn: the scope an earlier visit has to be in for a visitor to count as returning
     * - bounces: whether the scope counts bounces
     * - stored: breakdowns kept beyond the ones the scope shows
     * - ids: breakdowns whose rows belong to another post than the view, with what decides it
     *
     * @return array<string, array{id: string, blog: string, day: string, since: string, range: string,
     *     covers: string, visit: string, returningIn: string, bounces: bool, stored: list<string>,
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
            TrafficScope::SITE => [
                'id' => '0',
                'blog' => 'NULL',
                ...$utcDays,
                'covers' => 'h.suspect = 0',
                'visit' => '',
                'returningIn' => TrafficScope::SITE,
                'bounces' => true,
                // Views per blog, for the number of blogs read.
                'stored' => ['blog'],
                'ids' => [],
            ],
            TrafficScope::PLATFORM => [
                'id' => '0',
                'blog' => 'NULL',
                ...$utcDays,
                'covers' => "h.suspect = 0 AND h.page_type IN ({$platformTypes})",
                'visit' => '',
                'returningIn' => TrafficScope::PLATFORM,
                // Going on from the home page to a blog isn't leaving, so one view here says nothing.
                'bounces' => false,
                'stored' => [],
                'ids' => [],
            ],
            TrafficScope::BLOG => [
                'id' => 'h.blog_id',
                'blog' => 'h.blog_id',
                ...$blogDays,
                'covers' => 'h.suspect = 0 AND h.blog_id IS NOT NULL',
                'visit' => ', h.blog_id',
                'returningIn' => TrafficScope::BLOG,
                'bounces' => true,
                'stored' => [],
                'ids' => [],
            ],
            TrafficScope::POST => [
                'id' => 'h.post_id',
                'blog' => 'h.blog_id',
                ...$blogDays,
                'covers' => 'h.suspect = 0 AND h.post_id IS NOT NULL',
                'visit' => '',
                // Back on the blog, so a post page and its blog agree on who returned.
                'returningIn' => TrafficScope::BLOG,
                // A bounce is a visit to the blog, not to one post.
                'bounces' => false,
                'stored' => [],
                // Where readers went next belongs to the post they left.
                'ids' => ['next' => ['id' => 'h.from_post_id', 'covers' => 'h.suspect = 0 AND h.from_post_id IS NOT NULL']],
            ],
        ];
    }

    /**
     * The scope's views, each marked is_exit when the visitor's next view in the
     * scope came more than $gapMinutes later, or never. Takes one placeholder per
     * placeholder in $where.
     */
    public static function withExits(string $where, string $visit, int $gapMinutes): string
    {
        $next = "LEAD(h.created_at) OVER (PARTITION BY h.visitor_hash{$visit} ORDER BY h.created_at, h.id)";

        return "(SELECT h.*, {$next} IS NULL OR {$next} > h.created_at + INTERVAL {$gapMinutes} MINUTE AS is_exit
                 FROM traffic_hits h
                 WHERE {$where}) h";
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
