<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reads for the control panel's Traffic page, across every blog.
 *
 * Views and visitors come from traffic_site_daily, whose days are UTC and whose
 * visitors are counted once per day however many blogs they read. Everything
 * else adds up the per-blog rollups, whose days follow each blog's timezone, so
 * those numbers can sit a few hours apart from the totals at the range edges.
 */
class PlatformTrafficModel extends AppModel
{
    protected ?string $table = 'traffic_site_daily';

    /**
     * Breakdowns that mean something across blogs. Page paths are blog-relative,
     * so "/about" on two blogs is two different pages and is left out.
     */
    public const DIMENSIONS = [
        'channel', 'source', 'utm_source', 'utm_medium', 'utm_campaign',
        'device', 'browser', 'os', 'country', 'locale',
    ];

    /**
     * @return array{views: int, visitors: int}
     */
    public function siteTotals(string $from, string $to): array
    {
        $row = $this->database->query(
            'SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(visitors), 0) AS visitors
             FROM traffic_site_daily
             WHERE day BETWEEN ? AND ?',
            [$from, $to]
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return ['views' => (int) ($row['views'] ?? 0), 'visitors' => (int) ($row['visitors'] ?? 0)];
    }

    /**
     * Views, visitors and blogs read for each day that had any. The caller fills the gaps.
     *
     * @return array<string, array{views: int, visitors: int, blogs: int}> Keyed by Y-m-d
     */
    public function siteSeries(string $from, string $to): array
    {
        $rows = $this->database->query(
            'SELECT day, views, visitors, blogs FROM traffic_site_daily WHERE day BETWEEN ? AND ?',
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $series = [];
        foreach ($rows as $row) {
            $series[(string) $row['day']] = [
                'views' => (int) $row['views'],
                'visitors' => (int) $row['visitors'],
                'blogs' => (int) $row['blogs'],
            ];
        }

        return $series;
    }

    /**
     * Reading behaviour summed over every blog, plus how many blogs had a view.
     *
     * @return array<string, int>
     */
    public function engagementTotals(string $from, string $to): array
    {
        $row = $this->database->query(
            'SELECT COUNT(DISTINCT blog_id) AS active_blogs,
                    COALESCE(SUM(views), 0) AS views, COALESCE(SUM(visitors), 0) AS visitors,
                    COALESCE(SUM(identified_visitors), 0) AS identified_visitors,
                    COALESCE(SUM(returning_visitors), 0) AS returning_visitors,
                    COALESCE(SUM(bounces), 0) AS bounces, COALESCE(SUM(read_views), 0) AS read_views,
                    COALESCE(SUM(engaged_views), 0) AS engaged_views,
                    COALESCE(SUM(engaged_seconds), 0) AS engaged_seconds,
                    COALESCE(SUM(scroll_depth_sum), 0) AS scroll_depth_sum
             FROM traffic_daily
             WHERE post_id = 0 AND day BETWEEN ? AND ?',
            [$from, $to]
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return array_map('intval', $row);
    }

    /**
     * Blogs ranked by views, with what the table and the CSV show for each.
     *
     * @return list<array<string, mixed>>
     */
    public function topBlogs(string $from, string $to, int $limit): array
    {
        return $this->database->query(
            'SELECT d.blog_id, b.blog_name, b.blog_slug, b.status,
                    SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.bounces) AS bounces,
                    SUM(d.read_views) AS read_views, SUM(d.engaged_views) AS engaged_views,
                    SUM(d.engaged_seconds) AS engaged_seconds
             FROM traffic_daily d
             LEFT JOIN blogs b ON b.id = d.blog_id
             WHERE d.post_id = 0 AND d.day BETWEEN ? AND ?
             GROUP BY d.blog_id, b.blog_name, b.blog_slug, b.status
             ORDER BY views DESC, d.blog_id DESC
             LIMIT '.max(1, $limit),
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Views for each of the given blogs, for the change against the previous period.
     *
     * @param  list<int>  $blogIds
     * @return array<int, int> Blog id => views
     */
    public function viewsForBlogs(array $blogIds, string $from, string $to): array
    {
        if ($blogIds === []) {
            return [];
        }

        $ids = array_map('intval', $blogIds);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rows = $this->database->query(
            "SELECT blog_id, SUM(views) AS views
             FROM traffic_daily
             WHERE post_id = 0 AND blog_id IN ({$placeholders}) AND day BETWEEN ? AND ?
             GROUP BY blog_id",
            [...$ids, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $views = [];
        foreach ($rows as $row) {
            $views[(int) $row['blog_id']] = (int) $row['views'];
        }

        return $views;
    }

    /**
     * Posts from any blog ranked by views. A post deleted since keeps its numbers
     * and comes back with a null title.
     *
     * @return list<array<string, mixed>>
     */
    public function topPosts(string $from, string $to, int $limit): array
    {
        return $this->database->query(
            'SELECT d.post_id, d.blog_id, p.title, p.slug, p.status, b.blog_name, b.blog_slug,
                    SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.read_views) AS read_views,
                    SUM(d.engaged_views) AS engaged_views, SUM(d.engaged_seconds) AS engaged_seconds
             FROM traffic_daily d
             LEFT JOIN posts p ON p.id = d.post_id
             LEFT JOIN blogs b ON b.id = d.blog_id
             WHERE d.post_id > 0 AND d.day BETWEEN ? AND ?
             GROUP BY d.post_id, d.blog_id, p.title, p.slug, p.status, b.blog_name, b.blog_slug
             ORDER BY views DESC, d.post_id DESC
             LIMIT '.max(1, $limit),
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * One breakdown over every blog. Visitors are summed per blog and day, the
     * same way each blog's own page counts them.
     *
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public function breakdown(string $dimension, string $from, string $to, int $limit): array
    {
        if (!in_array($dimension, self::DIMENSIONS, true)) {
            throw new \InvalidArgumentException("Unknown platform traffic breakdown '{$dimension}'.");
        }

        $rows = $this->database->query(
            'SELECT value, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily_dimensions
             WHERE post_id = 0 AND dimension = ? AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY views DESC, value ASC
             LIMIT '.max(1, $limit),
            [$dimension, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'views' => (int) $row['views'],
            'visitors' => (int) $row['visitors'],
        ], $rows);
    }

    /**
     * Blogs whose owner turned visit counting on, whether or not anyone came yet.
     */
    public function countingBlogs(): int
    {
        return (int) $this->database->query(
            'SELECT COUNT(*) FROM blog_settings s JOIN blogs b ON b.id = s.blog_id WHERE s.traffic_enabled = 1'
        )->fetchColumn();
    }

    /**
     * The first day the platform has any numbers, for "collecting since".
     */
    public function firstDay(): ?string
    {
        $day = $this->database->query('SELECT MIN(day) FROM traffic_site_daily')->fetchColumn();

        return $day ? (string) $day : null;
    }
}
