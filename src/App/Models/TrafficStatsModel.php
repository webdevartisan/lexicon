<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\TrafficScope;

/**
 * Reads for every Traffic page, from the daily tables. Each read names the
 * scope it counts; the rows of a multi-post scope are added together.
 */
class TrafficStatsModel extends AppModel
{
    protected ?string $table = 'traffic_daily';

    /**
     * @return array<string, int>
     */
    public function totals(TrafficScope $scope, string $from, string $to): array
    {
        [$where, $params] = $this->scopeClause($scope);

        $row = $this->database->query(
            "SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(visitors), 0) AS visitors,
                    COALESCE(SUM(identified_visitors), 0) AS identified_visitors,
                    COALESCE(SUM(returning_visitors), 0) AS returning_visitors,
                    COALESCE(SUM(bounces), 0) AS bounces, COALESCE(SUM(read_views), 0) AS read_views,
                    COALESCE(SUM(engaged_views), 0) AS engaged_views,
                    COALESCE(SUM(engaged_seconds), 0) AS engaged_seconds,
                    COALESCE(SUM(scroll_depth_sum), 0) AS scroll_depth_sum
             FROM traffic_daily
             WHERE {$where} AND day BETWEEN ? AND ?",
            [...$params, $from, $to]
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return array_map('intval', $row);
    }

    /**
     * Views and visitors for each day that had any. The caller fills the gaps.
     *
     * @return array<string, array{views: int, visitors: int}> Keyed by Y-m-d
     */
    public function series(TrafficScope $scope, string $from, string $to): array
    {
        [$where, $params] = $this->scopeClause($scope);

        $rows = $this->database->query(
            "SELECT day, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily
             WHERE {$where} AND day BETWEEN ? AND ?
             GROUP BY day",
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $series = [];
        foreach ($rows as $row) {
            $series[(string) $row['day']] = ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
        }

        return $series;
    }

    /**
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public function breakdown(TrafficScope $scope, string $dimension, string $from, string $to, int $limit): array
    {
        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new \InvalidArgumentException("Unknown traffic breakdown '{$dimension}' for the {$scope->type} scope.");
        }

        [$where, $params] = $this->scopeClause($scope);

        $rows = $this->database->query(
            "SELECT value, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily_dimensions
             WHERE {$where} AND dimension = ? AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY views DESC, value ASC
             LIMIT ".max(1, $limit),
            [...$params, $dimension, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'views' => (int) $row['views'],
            'visitors' => (int) $row['visitors'],
        ], $rows);
    }

    /**
     * Posts ranked by views, inside a blog, an author's posts, or the whole site.
     * A post deleted since keeps its numbers and comes back with a null title.
     *
     * @return list<array<string, mixed>>
     */
    public function topPosts(TrafficScope $within, string $from, string $to, int $limit): array
    {
        [$where, $params] = match ($within->type) {
            TrafficScope::SITE => ['TRUE', []],
            TrafficScope::BLOG => ['d.blog_id = ?', [(int) $within->blogId]],
            TrafficScope::POST => $this->scopeClause($within, 'd'),
            default => throw new \InvalidArgumentException("No post ranking for the {$within->type} scope."),
        };

        return $this->database->query(
            "SELECT d.scope_id AS post_id, d.blog_id, p.title, p.slug, p.status, p.author_id, b.blog_name, b.blog_slug,
                    SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.read_views) AS read_views,
                    SUM(d.engaged_views) AS engaged_views, SUM(d.engaged_seconds) AS engaged_seconds
             FROM traffic_daily d
             LEFT JOIN posts p ON p.id = d.scope_id
             LEFT JOIN blogs b ON b.id = d.blog_id
             WHERE d.scope = 'post' AND {$where} AND d.day BETWEEN ? AND ?
             GROUP BY d.scope_id, d.blog_id, p.title, p.slug, p.status, p.author_id, b.blog_name, b.blog_slug
             ORDER BY views DESC, d.scope_id DESC
             LIMIT ".max(1, $limit),
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Blogs ranked by views, each with its own numbers in its own timezone.
     * A blog deleted since keeps its numbers and comes back with a null name.
     *
     * @return list<array<string, mixed>>
     */
    public function topBlogs(string $from, string $to, int $limit): array
    {
        return $this->database->query(
            "SELECT d.scope_id AS blog_id, b.blog_name, b.blog_slug, b.status,
                    SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.bounces) AS bounces,
                    SUM(d.read_views) AS read_views, SUM(d.engaged_views) AS engaged_views,
                    SUM(d.engaged_seconds) AS engaged_seconds
             FROM traffic_daily d
             LEFT JOIN blogs b ON b.id = d.scope_id
             WHERE d.scope = 'blog' AND d.day BETWEEN ? AND ?
             GROUP BY d.scope_id, b.blog_name, b.blog_slug, b.status
             ORDER BY views DESC, d.scope_id DESC
             LIMIT ".max(1, $limit),
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
            "SELECT scope_id, SUM(views) AS views
             FROM traffic_daily
             WHERE scope = 'blog' AND scope_id IN ({$placeholders}) AND day BETWEEN ? AND ?
             GROUP BY scope_id",
            [...$ids, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $views = [];
        foreach ($rows as $row) {
            $views[(int) $row['scope_id']] = (int) $row['views'];
        }

        return $views;
    }

    /**
     * Names of the given blogs that are published now. One that is gone or hidden
     * since is left out, so no other blog's dashboard shows it.
     *
     * @param  list<int>  $blogIds
     * @return array<int, string> Blog id => name
     */
    public function publishedBlogNames(array $blogIds): array
    {
        if ($blogIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($blogIds), '?'));

        $rows = $this->database->query(
            "SELECT id, blog_name FROM blogs WHERE id IN ({$placeholders}) AND status = 'published'",
            $blogIds
        )->fetchAll(\PDO::FETCH_ASSOC);

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row['id']] = (string) $row['blog_name'];
        }

        return $names;
    }

    /**
     * How many blogs had a view, over the range and on each day, in the site's UTC days.
     *
     * @return array{total: int, byDay: array<string, int>}
     */
    public function blogsRead(string $from, string $to): array
    {
        $rows = $this->database->query(
            "SELECT day, COUNT(*) AS blogs
             FROM traffic_daily_dimensions
             WHERE scope = 'site' AND scope_id = 0 AND dimension = 'blog' AND day BETWEEN ? AND ?
             GROUP BY day",
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $total = $this->database->query(
            "SELECT COUNT(DISTINCT value)
             FROM traffic_daily_dimensions
             WHERE scope = 'site' AND scope_id = 0 AND dimension = 'blog' AND day BETWEEN ? AND ?",
            [$from, $to]
        )->fetchColumn();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['blogs'];
        }

        return ['total' => (int) $total, 'byDay' => $byDay];
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

        $ids = array_map('intval', $postIds);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rows = $this->database->query(
            "SELECT scope_id, SUM(views) AS views, SUM(visitors) AS visitors
             FROM traffic_daily
             WHERE scope = 'post' AND scope_id IN ({$placeholders}) AND blog_id = ?
             GROUP BY scope_id",
            [...$ids, $blogId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['scope_id']] = ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
        }

        return $totals;
    }

    /**
     * The first day the scope has any numbers, for "collecting since".
     */
    public function firstDay(TrafficScope $scope): ?string
    {
        [$where, $params] = $this->scopeClause($scope);

        $day = $this->database->query("SELECT MIN(day) FROM traffic_daily WHERE {$where}", $params)->fetchColumn();

        return $day ? (string) $day : null;
    }

    /**
     * The rows a scope adds up. A post scope also checks the blog, so post ids
     * from another blog never count.
     *
     * @return array{0: string, 1: list<int|string>}
     */
    private function scopeClause(TrafficScope $scope, string $alias = ''): array
    {
        if ($scope->ids === []) {
            return ['1 = 0', []];
        }

        $prefix = $alias === '' ? '' : $alias.'.';
        $placeholders = implode(', ', array_fill(0, count($scope->ids), '?'));

        $where = "{$prefix}scope = ? AND {$prefix}scope_id IN ({$placeholders})";
        $params = [$scope->type, ...$scope->ids];

        if ($scope->type === TrafficScope::POST) {
            $where .= " AND {$prefix}blog_id = ?";
            $params[] = (int) $scope->blogId;
        }

        return [$where, $params];
    }
}
