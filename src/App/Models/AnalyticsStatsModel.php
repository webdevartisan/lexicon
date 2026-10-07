<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\AnalyticsReader;
use App\ValueObjects\AnalyticsScope;

/**
 * Reads for every Insights page, from the daily tables. Each read names the
 * scope it counts; the rows of a multi-post scope are added together.
 */
class AnalyticsStatsModel extends AppModel implements AnalyticsReader
{
    protected ?string $table = 'analytics_daily';

    /**
     * @return array<string, int>
     */
    public function totals(AnalyticsScope $scope, string $from, string $to): array
    {
        [$where, $params] = self::scopeWhere($scope);

        $row = $this->database->query(
            "SELECT COALESCE(SUM(views), 0) AS views, COALESCE(SUM(visitors), 0) AS visitors,
                    COALESCE(SUM(identified_visitors), 0) AS identified_visitors,
                    COALESCE(SUM(returning_visitors), 0) AS returning_visitors,
                    COALESCE(SUM(visits), 0) AS visits, COALESCE(SUM(engaged_visits), 0) AS engaged_visits,
                    COALESCE(SUM(visit_pages), 0) AS visit_pages, COALESCE(SUM(visit_seconds), 0) AS visit_seconds,
                    COALESCE(SUM(read_views), 0) AS read_views, COALESCE(SUM(read_to_end), 0) AS read_to_end,
                    COALESCE(SUM(engaged_views), 0) AS engaged_views,
                    COALESCE(SUM(engaged_seconds), 0) AS engaged_seconds,
                    COALESCE(SUM(scroll_depth_sum), 0) AS scroll_depth_sum,
                    COALESCE(SUM(scroll_25), 0) AS scroll_25, COALESCE(SUM(scroll_50), 0) AS scroll_50,
                    COALESCE(SUM(scroll_75), 0) AS scroll_75, COALESCE(SUM(scroll_100), 0) AS scroll_100
             FROM analytics_daily
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
    public function series(AnalyticsScope $scope, string $from, string $to): array
    {
        [$where, $params] = self::scopeWhere($scope);

        $rows = $this->database->query(
            "SELECT day, SUM(views) AS views, SUM(visitors) AS visitors
             FROM analytics_daily
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
     * @return list<array{value: string, views: int, visitors: int, read_views: int, engaged_views: int, engaged_seconds: int}>
     */
    public function breakdown(AnalyticsScope $scope, string $dimension, string $from, string $to, int $limit): array
    {
        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new \InvalidArgumentException("Unknown analytics breakdown '{$dimension}' for the {$scope->type} scope.");
        }

        [$where, $params] = self::scopeWhere($scope);

        $rows = $this->database->query(
            "SELECT value, SUM(views) AS views, SUM(visitors) AS visitors, SUM(read_views) AS read_views,
                    SUM(engaged_views) AS engaged_views, SUM(engaged_seconds) AS engaged_seconds
             FROM analytics_daily_dimensions
             WHERE {$where} AND dimension = ? AND day BETWEEN ? AND ?
             GROUP BY value
             ORDER BY views DESC, value ASC
             LIMIT ".max(1, $limit),
            [...$params, $dimension, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return self::breakdownRows($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{value: string, views: int, visitors: int, read_views: int, engaged_views: int, engaged_seconds: int}>
     */
    public static function breakdownRows(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'value' => (string) $row['value'],
            'views' => (int) $row['views'],
            'visitors' => (int) $row['visitors'],
            'read_views' => (int) $row['read_views'],
            'engaged_views' => (int) $row['engaged_views'],
            'engaged_seconds' => (int) $row['engaged_seconds'],
        ], $rows);
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function hourly(AnalyticsScope $scope, string $from, string $to): array
    {
        [$where, $params] = self::scopeWhere($scope);

        $rows = $this->database->query(
            "SELECT DAYOFWEEK(day) AS weekday, CAST(value AS UNSIGNED) AS hour, SUM(views) AS views
             FROM analytics_daily_dimensions
             WHERE {$where} AND dimension = 'hour' AND day BETWEEN ? AND ?
             GROUP BY weekday, hour",
            [...$params, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return self::grid($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows  weekday, hour, views
     * @return array<int, array<int, int>>
     */
    public static function grid(array $rows): array
    {
        $grid = [];
        foreach ($rows as $row) {
            $grid[(int) $row['weekday']][(int) $row['hour']] = (int) $row['views'];
        }

        return $grid;
    }

    /**
     * The blog's most read posts in the range that anyone can open now.
     *
     * @return list<array{id: int, title: string, slug: string, views: int}>
     */
    public function popularPosts(int $blogId, string $from, string $to, int $limit): array
    {
        $rows = $this->database->query(
            "SELECT p.id, p.title, p.slug, SUM(d.views) AS views
             FROM analytics_daily d
             JOIN posts p ON p.id = d.scope_id AND p.blog_id = d.blog_id
             WHERE d.scope = 'post' AND d.blog_id = ? AND d.day BETWEEN ? AND ?
               AND p.status = 'published' AND p.visibility = 'public'
             GROUP BY p.id, p.title, p.slug
             ORDER BY views DESC, p.id DESC
             LIMIT ".max(1, $limit),
            [$blogId, $from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'slug' => (string) $row['slug'],
            'views' => (int) $row['views'],
        ], $rows);
    }

    /**
     * Published blogs, grouped by their owner, for the weekly digest.
     *
     * @return array<int, list<array{id: int, name: string, slug: string}>> Owner id => blogs
     */
    public function publishedBlogsByOwner(): array
    {
        $rows = $this->database->query(
            "SELECT id, owner_id, blog_name, blog_slug FROM blogs WHERE status = 'published' ORDER BY owner_id, blog_name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $owners = [];
        foreach ($rows as $row) {
            $owners[(int) $row['owner_id']][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['blog_name'],
                'slug' => (string) $row['blog_slug'],
            ];
        }

        return $owners;
    }

    /**
     * Views on $today against the usual day over the $days before it.
     *
     * @return array{today: int, usual: float}
     */
    public function pace(AnalyticsScope $scope, string $today, int $days): array
    {
        [$where, $params] = self::scopeWhere($scope);

        $row = $this->database->query(
            "SELECT COALESCE(SUM(CASE WHEN day = ? THEN views END), 0) AS today,
                    COALESCE(SUM(CASE WHEN day < ? THEN views END), 0) AS earlier
             FROM analytics_daily
             WHERE {$where} AND day BETWEEN ? - INTERVAL ? DAY AND ?",
            [$today, $today, ...$params, $today, $days, $today]
        )->fetch(\PDO::FETCH_ASSOC) ?: ['today' => 0, 'earlier' => 0];

        return ['today' => (int) $row['today'], 'usual' => (int) $row['earlier'] / max(1, $days)];
    }

    /**
     * Names for category, tag and author ids, for their breakdown rows.
     *
     * @param  list<int>  $ids
     * @return array<int, string> Id => name
     */
    public function labels(string $dimension, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $sql = match ($dimension) {
            'category' => 'SELECT id, name FROM categories WHERE id IN (%s)',
            'tag' => 'SELECT id, name FROM tags WHERE id IN (%s)',
            'author' => "SELECT id, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)), ''), CONCAT('@', handle))
                         FROM users WHERE id IN (%s)",
            default => throw new \InvalidArgumentException("No names for the analytics breakdown '{$dimension}'."),
        };

        $ids = array_map('intval', $ids);
        $rows = $this->database->query(
            sprintf($sql, implode(', ', array_fill(0, count($ids), '?'))),
            $ids
        )->fetchAll(\PDO::FETCH_NUM);

        $labels = [];
        foreach ($rows as [$id, $name]) {
            $labels[(int) $id] = (string) $name;
        }

        return $labels;
    }

    /**
     * How each post did since it was published: all its views, those in its first
     * week and first 30 days, and those in the last 30 days of $today.
     *
     * @param  list<int>  $postIds
     * @return array<int, array{published: string, total: int, first_week: int, first_month: int, last_month: int}>
     */
    public function performance(int $blogId, array $postIds, string $today): array
    {
        if ($postIds === []) {
            return [];
        }

        $ids = array_map('intval', $postIds);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rows = $this->database->query(
            "SELECT d.scope_id AS post_id, DATE(p.published_at) AS published, SUM(d.views) AS total,
                    COALESCE(SUM(CASE WHEN d.day < DATE(p.published_at) + INTERVAL 7 DAY THEN d.views END), 0) AS first_week,
                    COALESCE(SUM(CASE WHEN d.day < DATE(p.published_at) + INTERVAL 30 DAY THEN d.views END), 0) AS first_month,
                    COALESCE(SUM(CASE WHEN d.day > ? - INTERVAL 30 DAY THEN d.views END), 0) AS last_month
             FROM analytics_daily d
             JOIN posts p ON p.id = d.scope_id
             WHERE d.scope = 'post' AND d.blog_id = ? AND d.scope_id IN ({$placeholders}) AND p.published_at IS NOT NULL
             GROUP BY d.scope_id, p.published_at",
            [$today, $blogId, ...$ids]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $performance = [];
        foreach ($rows as $row) {
            $performance[(int) $row['post_id']] = [
                'published' => (string) $row['published'],
                'total' => (int) $row['total'],
                'first_week' => (int) $row['first_week'],
                'first_month' => (int) $row['first_month'],
                'last_month' => (int) $row['last_month'],
            ];
        }

        return $performance;
    }

    /**
     * First-week views of the blog's posts published since $since and at least a
     * week old, for what a usual first week looks like.
     *
     * @return list<int>
     */
    public function firstWeeks(int $blogId, string $since, string $today): array
    {
        $rows = $this->database->query(
            "SELECT COALESCE(SUM(CASE WHEN d.day < DATE(p.published_at) + INTERVAL 7 DAY THEN d.views END), 0)
             FROM posts p
             LEFT JOIN analytics_daily d ON d.scope = 'post' AND d.scope_id = p.id AND d.blog_id = p.blog_id
             WHERE p.blog_id = ? AND p.status = 'published' AND p.published_at >= ? AND p.published_at < ? - INTERVAL 7 DAY
             GROUP BY p.id",
            [$blogId, $since, $today]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    /**
     * Posts that went out in the range, for markers on the chart.
     *
     * @param  list<int>|null  $postIds  Only these posts, or every post of the blog
     * @return list<array{day: string, title: string}>
     */
    public function publishedBetween(int $blogId, ?array $postIds, string $from, string $to): array
    {
        $where = 'blog_id = ? AND status = ? AND published_at >= ? AND published_at < ? + INTERVAL 1 DAY';
        $params = [$blogId, 'published', $from, $to];

        if ($postIds !== null) {
            if ($postIds === []) {
                return [];
            }

            $where .= ' AND id IN ('.implode(', ', array_fill(0, count($postIds), '?')).')';
            array_push($params, ...array_map('intval', $postIds));
        }

        $rows = $this->database->query(
            "SELECT DATE(published_at) AS day, title FROM posts WHERE {$where} ORDER BY published_at",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => ['day' => (string) $row['day'], 'title' => (string) $row['title']], $rows);
    }

    /**
     * Posts ranked by views, inside a blog, an author's posts, or the whole site.
     * A post deleted since keeps its numbers and comes back with a null title.
     *
     * @return list<array<string, mixed>>
     */
    public function topPosts(AnalyticsScope $within, string $from, string $to, int $limit): array
    {
        [$where, $params] = match ($within->type) {
            AnalyticsScope::SITE => ['TRUE', []],
            AnalyticsScope::BLOG => ['d.blog_id = ?', [(int) $within->blogId]],
            AnalyticsScope::POST => self::scopeWhere($within, 'd'),
            default => throw new \InvalidArgumentException("No post ranking for the {$within->type} scope."),
        };

        // Grouping every daily row by title and blog name made the temp table the slow step, so join text after the LIMIT.
        return $this->database->query(
            "SELECT t.post_id, t.blog_id, p.title, p.slug, p.status, p.author_id, b.blog_name, b.blog_slug,
                    t.views, t.visitors, t.read_views, t.engaged_views, t.engaged_seconds
             FROM (SELECT d.scope_id AS post_id, d.blog_id,
                          SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.read_views) AS read_views,
                          SUM(d.engaged_views) AS engaged_views, SUM(d.engaged_seconds) AS engaged_seconds
                   FROM analytics_daily d
                   WHERE d.scope = 'post' AND {$where} AND d.day BETWEEN ? AND ?
                   GROUP BY d.scope_id, d.blog_id
                   ORDER BY views DESC, d.scope_id DESC
                   LIMIT ".max(1, $limit).') t
             LEFT JOIN posts p ON p.id = t.post_id
             LEFT JOIN blogs b ON b.id = t.blog_id
             ORDER BY t.views DESC, t.post_id DESC',
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
                    SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.visits) AS visits, SUM(d.engaged_visits) AS engaged_visits,
                    SUM(d.read_views) AS read_views, SUM(d.engaged_views) AS engaged_views,
                    SUM(d.engaged_seconds) AS engaged_seconds
             FROM analytics_daily d
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
             FROM analytics_daily
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
             FROM analytics_daily_dimensions
             WHERE scope = 'site' AND scope_id = 0 AND dimension = 'blog' AND day BETWEEN ? AND ?
             GROUP BY day",
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $total = $this->database->query(
            "SELECT COUNT(DISTINCT value)
             FROM analytics_daily_dimensions
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
             FROM analytics_daily
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
    public function firstDay(AnalyticsScope $scope): ?string
    {
        [$where, $params] = self::scopeWhere($scope);

        $day = $this->database->query("SELECT MIN(day) FROM analytics_daily WHERE {$where}", $params)->fetchColumn();

        return $day ? (string) $day : null;
    }

    /**
     * The rows a scope adds up. A post scope also checks the blog, so post ids
     * from another blog never count.
     *
     * @return array{0: string, 1: list<int|string>}
     */
    public static function scopeWhere(AnalyticsScope $scope, string $alias = ''): array
    {
        if ($scope->ids === []) {
            return ['1 = 0', []];
        }

        $prefix = $alias === '' ? '' : $alias.'.';
        $placeholders = implode(', ', array_fill(0, count($scope->ids), '?'));

        $where = "{$prefix}scope = ? AND {$prefix}scope_id IN ({$placeholders})";
        $params = [$scope->type, ...$scope->ids];

        if ($scope->type === AnalyticsScope::POST) {
            $where .= " AND {$prefix}blog_id = ?";
            $params[] = (int) $scope->blogId;
        }

        return [$where, $params];
    }
}
