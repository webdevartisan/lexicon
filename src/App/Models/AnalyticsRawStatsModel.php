<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\AnalyticsReader;
use App\ValueObjects\AnalyticsScope;

/**
 * An Insights page narrowed to one source, device, page and so on, read straight
 * from raw views, so it only reaches back as far as raw retention. Counted the
 * way the daily tables count, with one exception: returning visitors need a
 * visitor's whole history, so a narrowed page leaves them out.
 */
class AnalyticsRawStatsModel extends AppModel implements AnalyticsReader
{
    protected ?string $table = 'analytics_events';

    /** @var array<string, string> Breakdown => value */
    private array $filters = [];

    private int $readSeconds = 30;

    private int $engagedSeconds = 10;

    /**
     * @param  array<string, string>  $filters  Breakdown => value, breakdowns from AnalyticsSql::FILTERABLE
     */
    public function filtered(array $filters, int $readSeconds, int $engagedSeconds): self
    {
        $reader = clone $this;
        $reader->filters = $filters;
        $reader->readSeconds = $readSeconds;
        $reader->engagedSeconds = $engagedSeconds;

        return $reader;
    }

    /**
     * @return array<string, int>
     */
    public function totals(AnalyticsScope $scope, string $from, string $to): array
    {
        $s = AnalyticsSql::scopes()[$scope->type];
        [$where, $params] = $this->where($scope, $from, $to);

        $row = $this->database->query(
            "SELECT COALESCE(SUM(v.views), 0) AS views, COUNT(*) AS visitors,
                    0 AS identified_visitors, 0 AS returning_visitors,
                    COALESCE(SUM(v.read_views), 0) AS read_views, COALESCE(SUM(v.read_to_end), 0) AS read_to_end,
                    COALESCE(SUM(v.engaged_views), 0) AS engaged_views,
                    COALESCE(SUM(v.engaged_seconds), 0) AS engaged_seconds, COALESCE(SUM(v.scroll_sum), 0) AS scroll_depth_sum,
                    COALESCE(SUM(v.scroll_25), 0) AS scroll_25, COALESCE(SUM(v.scroll_50), 0) AS scroll_50,
                    COALESCE(SUM(v.scroll_75), 0) AS scroll_75, COALESCE(SUM(v.scroll_100), 0) AS scroll_100
             FROM ({$this->perVisitorDay($s['day'], $where)}) v",
            $params
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        [$segments, $segmentParams] = AnalyticsSql::visitSegments($scope->type, $where, $params, $this->engagedSeconds, $from);
        $visits = $this->database->query(
            "SELECT COALESCE(SUM(visits), 0) AS visits, COALESCE(SUM(engaged_visits), 0) AS engaged_visits,
                    COALESCE(SUM(visit_pages), 0) AS visit_pages, COALESCE(SUM(visit_seconds), 0) AS visit_seconds
             FROM ({$segments}) s",
            $segmentParams
        )->fetch(\PDO::FETCH_ASSOC) ?: [];

        return array_map('intval', $row + $visits);
    }

    /**
     * @return array<string, array{views: int, visitors: int}>
     */
    public function series(AnalyticsScope $scope, string $from, string $to): array
    {
        $s = AnalyticsSql::scopes()[$scope->type];
        [$where, $params] = $this->where($scope, $from, $to);

        $rows = $this->database->query(
            "SELECT v.day, SUM(v.views) AS views, COUNT(*) AS visitors
             FROM ({$this->perVisitorDay($s['day'], $where)}) v
             GROUP BY v.day",
            $params
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

        $s = AnalyticsSql::scopes()[$scope->type];
        $column = AnalyticsSql::column($scope->type, $dimension);
        [$where, $params] = $this->where($scope, $from, $to, $s['ids'][$dimension] ?? null);

        $views = AnalyticsSql::VIEWS.' h '.AnalyticsSql::join($dimension);
        $condition = "{$where} AND ".AnalyticsSql::filter($scope->type, $dimension);

        if ($dimension === 'exit') {
            $views = AnalyticsSql::withExits($where, $s['visit'], AnalyticsRollupModel::VISIT_GAP_MINUTES);
            $condition = AnalyticsSql::filter($scope->type, $dimension);
        }

        $rows = $this->database->query(
            "SELECT d.value, SUM(d.views) AS views, SUM(d.visitors) AS visitors, SUM(d.read_views) AS read_views,
                    SUM(d.engaged_views) AS engaged_views, SUM(d.engaged_seconds) AS engaged_seconds
             FROM (SELECT {$s['day']} AS day, {$column} AS value, COUNT(*) AS views,
                          COUNT(DISTINCT h.visitor_hash) AS visitors,
                          COALESCE(SUM(h.engaged_seconds >= {$this->readSeconds}), 0) AS read_views,
                          SUM(h.engaged_seconds IS NOT NULL) AS engaged_views,
                          COALESCE(SUM(h.engaged_seconds), 0) AS engaged_seconds
                   FROM {$views}
                   WHERE {$condition}
                   GROUP BY day, value) d
             GROUP BY d.value
             ORDER BY views DESC, d.value ASC
             LIMIT ".max(1, $limit),
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return AnalyticsStatsModel::breakdownRows($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topPosts(AnalyticsScope $within, string $from, string $to, int $limit): array
    {
        $s = AnalyticsSql::scopes()[$within->type];
        [$where, $params] = $this->where($within, $from, $to);

        return $this->database->query(
            "SELECT v.post_id, v.blog_id, p.title, p.slug, p.status, p.author_id, b.blog_name, b.blog_slug,
                    SUM(v.views) AS views, SUM(v.visitors) AS visitors, SUM(v.read_views) AS read_views,
                    SUM(v.engaged_views) AS engaged_views, SUM(v.engaged_seconds) AS engaged_seconds
             FROM (SELECT {$s['day']} AS day, h.post_id, h.blog_id, COUNT(*) AS views,
                          COUNT(DISTINCT h.visitor_hash) AS visitors,
                          COALESCE(SUM(h.engaged_seconds >= {$this->readSeconds}), 0) AS read_views,
                          SUM(h.engaged_seconds IS NOT NULL) AS engaged_views,
                          COALESCE(SUM(h.engaged_seconds), 0) AS engaged_seconds
                   FROM ".AnalyticsSql::VIEWS." h
                   WHERE {$where} AND h.post_id IS NOT NULL
                   GROUP BY day, h.post_id, h.blog_id) v
             LEFT JOIN posts p ON p.id = v.post_id
             LEFT JOIN blogs b ON b.id = v.blog_id
             GROUP BY v.post_id, v.blog_id, p.title, p.slug, p.status, p.author_id, b.blog_name, b.blog_slug
             ORDER BY views DESC, v.post_id DESC
             LIMIT ".max(1, $limit),
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function hourly(AnalyticsScope $scope, string $from, string $to): array
    {
        $s = AnalyticsSql::scopes()[$scope->type];
        [$where, $params] = $this->where($scope, $from, $to);
        $hour = AnalyticsSql::column($scope->type, 'hour');

        $rows = $this->database->query(
            "SELECT DAYOFWEEK({$s['day']}) AS weekday, {$hour} AS hour, COUNT(*) AS views
             FROM ".AnalyticsSql::VIEWS." h
             WHERE {$where}
             GROUP BY weekday, hour",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return AnalyticsStatsModel::grid($rows);
    }

    /**
     * @return array{total: int, byDay: array<string, int>}
     */
    public function blogsRead(string $from, string $to): array
    {
        [$where, $params] = $this->where(AnalyticsScope::site(), $from, $to);

        $rows = $this->database->query(
            'SELECT DATE(h.created_at) AS day, COUNT(DISTINCT h.blog_id) AS blogs
             FROM '.AnalyticsSql::VIEWS." h WHERE {$where} AND h.blog_id IS NOT NULL
             GROUP BY day",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        $total = $this->database->query(
            'SELECT COUNT(DISTINCT h.blog_id) FROM '.AnalyticsSql::VIEWS." h WHERE {$where} AND h.blog_id IS NOT NULL",
            $params
        )->fetchColumn();

        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['blogs'];
        }

        return ['total' => (int) $total, 'byDay' => $byDay];
    }

    /**
     * One row per visitor and day, the unit visitors are counted in.
     */
    private function perVisitorDay(string $day, string $where): string
    {
        $steps = '';
        foreach ([25, 50, 75, 100] as $step) {
            $steps .= ", COALESCE(SUM(h.engaged_seconds IS NOT NULL AND h.scroll_depth >= {$step}), 0) AS scroll_{$step}";
        }

        return "SELECT {$day} AS day, h.visitor_hash, COUNT(*) AS views,
                       COALESCE(SUM(h.engaged_seconds >= {$this->readSeconds}), 0) AS read_views,
                       COALESCE(SUM(h.engaged_seconds >= {$this->readSeconds} AND h.scroll_depth >= 100), 0) AS read_to_end,
                       SUM(h.engaged_seconds IS NOT NULL) AS engaged_views,
                       COALESCE(SUM(h.engaged_seconds), 0) AS engaged_seconds,
                       COALESCE(SUM(CASE WHEN h.engaged_seconds IS NOT NULL THEN h.scroll_depth END), 0) AS scroll_sum{$steps}
                FROM ".AnalyticsSql::VIEWS." h
                WHERE {$where}
                GROUP BY day, h.visitor_hash";
    }

    /**
     * The scope's views in the range that match every filter.
     *
     * @param  array{id: string, covers: string}|null  $override  For a breakdown that belongs to another post than the view
     * @return array{0: string, 1: list<int|string>}
     */
    private function where(AnalyticsScope $scope, string $from, string $to, ?array $override = null): array
    {
        $s = AnalyticsSql::scopes()[$scope->type];
        $conditions = [$override['covers'] ?? $s['covers'], $s['range']];
        $params = [$from, $to];

        if ($scope->type === AnalyticsScope::BLOG) {
            $conditions[] = 'h.blog_id = ?';
            $params[] = (int) $scope->blogId;
        }

        if ($scope->type === AnalyticsScope::POST) {
            if ($scope->ids === []) {
                return ['1 = 0', []];
            }

            $id = $override['id'] ?? $s['id'];
            $conditions[] = $id.' IN ('.implode(', ', array_fill(0, count($scope->ids), '?')).') AND h.blog_id = ?';
            array_push($params, ...$scope->ids);
            $params[] = (int) $scope->blogId;
        }

        foreach ($this->filters as $dimension => $value) {
            $conditions[] = AnalyticsSql::match($scope->type, $dimension);
            $params[] = $value;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
