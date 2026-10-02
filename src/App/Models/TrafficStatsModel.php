<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reads for the Traffic dashboard, from the daily rollups.
 *
 * $postIds is null for the whole blog (the post_id = 0 rows), or a list of posts to add up.
 */
class TrafficStatsModel extends AppModel
{
    protected ?string $table = 'traffic_daily';

    /** Breakdowns the dashboard may ask for. */
    public const DIMENSIONS = [
        'channel', 'source', 'utm_source', 'utm_medium', 'utm_campaign',
        'device', 'browser', 'os', 'country', 'locale', 'page',
    ];

    /**
     * @param  list<int>|null  $postIds
     * @return array<string, int>
     */
    public function totals(int $blogId, ?array $postIds, string $from, string $to): array
    {
        [$posts, $params] = $this->postClause($postIds);

        $row = $this->database->query(
            "SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(visitors), 0) AS visitors,
                    COALESCE(SUM(identified_visitors), 0) AS identified_visitors,
                    COALESCE(SUM(returning_visitors), 0) AS returning_visitors,
                    COALESCE(SUM(bounces), 0) AS bounces, COALESCE(SUM(read_views), 0) AS read_views,
                    COALESCE(SUM(engaged_views), 0) AS engaged_views,
                    COALESCE(SUM(engaged_seconds), 0) AS engaged_seconds,
                    COALESCE(SUM(scroll_depth_sum), 0) AS scroll_depth_sum
             FROM traffic_daily
             WHERE blog_id = ? AND {$posts} AND day BETWEEN ? AND ?",
            [$blogId, ...$params, $from, $to]
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return array_map('intval', $row);
    }

    /**
     * Views and visitors for each day that had any. The caller fills the gaps.
     *
     * @param  list<int>|null  $postIds
     * @return array<string, array{views: int, visitors: int}> Keyed by Y-m-d
     */
    public function series(int $blogId, ?array $postIds, string $from, string $to): array
    {
        [$posts, $params] = $this->postClause($postIds);

        $rows = $this->database->query(
            "SELECT day, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily
             WHERE blog_id = ? AND {$posts} AND day BETWEEN ? AND ?
             GROUP BY day",
            [$blogId, ...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $series = [];
        foreach ($rows as $row) {
            $series[(string) $row['day']] = ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
        }

        return $series;
    }

    /**
     * @param  list<int>|null  $postIds
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public function breakdown(
        int $blogId,
        ?array $postIds,
        string $dimension,
        string $from,
        string $to,
        int $limit
    ): array {
        if (!in_array($dimension, self::DIMENSIONS, true)) {
            throw new \InvalidArgumentException("Unknown traffic breakdown '{$dimension}'.");
        }

        [$posts, $params] = $this->postClause($postIds);

        $rows = $this->database->query(
            "SELECT value, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily_dimensions
             WHERE blog_id = ? AND {$posts} AND dimension = ? AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY views DESC, value ASC
             LIMIT ".max(1, $limit),
            [$blogId, ...$params, $dimension, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'views' => (int) $row['views'],
            'visitors' => (int) $row['visitors'],
        ], $rows);
    }

    /**
     * Posts ranked by views. A post deleted since keeps its numbers and comes
     * back with a null title.
     *
     * @param  list<int>|null  $postIds  Only these posts, or null for every post in the blog
     * @return list<array<string, mixed>>
     */
    public function topPosts(int $blogId, ?array $postIds, string $from, string $to, int $limit): array
    {
        [$posts, $params] = $postIds === null ? ['d.post_id > 0', []] : $this->postClause($postIds, 'd.post_id');

        return $this->database->query(
            "SELECT d.post_id, p.title, p.slug, p.status, p.author_id,
                    SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.read_views) AS read_views,
                    SUM(d.engaged_views) AS engaged_views, SUM(d.engaged_seconds) AS engaged_seconds
             FROM traffic_daily d
             LEFT JOIN posts p ON p.id = d.post_id
             WHERE d.blog_id = ? AND {$posts} AND d.day BETWEEN ? AND ?
             GROUP BY d.post_id, p.title, p.slug, p.status, p.author_id
             ORDER BY views DESC, d.post_id DESC
             LIMIT ".max(1, $limit),
            [$blogId, ...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * All-time views and visitors for each of the given posts, for the posts
     * list and the editor.
     *
     * @param  list<int>  $postIds
     * @return array<int, array{views: int, visitors: int}>
     */
    public function lifetimeForPosts(int $blogId, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        [$posts, $params] = $this->postClause($postIds);

        $rows = $this->database->query(
            "SELECT post_id, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily
             WHERE blog_id = ? AND {$posts}
             GROUP BY post_id",
            [$blogId, ...$params]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['post_id']] = ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
        }

        return $totals;
    }

    /**
     * The first day the blog has any numbers, for "collecting since".
     */
    public function firstDay(int $blogId): ?string
    {
        $day = $this->database->query(
            'SELECT MIN(day) FROM traffic_daily WHERE blog_id = ? AND post_id = 0',
            [$blogId]
        )->fetchColumn();

        return $day ? (string) $day : null;
    }

    /**
     * @param  list<int>|null  $postIds
     * @return array{0: string, 1: list<int>}
     */
    private function postClause(?array $postIds, string $column = 'post_id'): array
    {
        if ($postIds === null) {
            return ["{$column} = 0", []];
        }

        if ($postIds === []) {
            return ['1 = 0', []];
        }

        $ids = array_map('intval', $postIds);

        return [$column.' IN ('.implode(', ', array_fill(0, count($ids), '?')).')', $ids];
    }
}
