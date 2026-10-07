<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Analytics\PlatformPages;
use App\ValueObjects\AnalyticsScope;

/**
 * Reads for the Technical pages, straight from the raw events: page speed at the
 * 75th percentile and server errors. A percentile can't be added up from daily
 * totals, so page speed is always read over a fixed window of recent days.
 */
class AnalyticsTechnicalModel extends AppModel
{
    protected ?string $table = 'analytics_events';

    /** Page speed columns, each read on its own because each page measures a different set. */
    public const SPEED_COLUMNS = ['lcp_ms', 'inp_ms', 'cls', 'ttfb_ms'];

    /**
     * The 75th percentile of each page speed measure per kind of page, over the last $days days.
     *
     * @return array<string, array<string, array{p75: float, samples: int}>> Page type => column => value
     */
    public function speedByPageType(AnalyticsScope $scope, int $days): array
    {
        $speed = [];

        foreach (self::SPEED_COLUMNS as $column) {
            foreach ($this->p75($scope, $column, 'page_type', $days, 1) as $row) {
                $speed[(string) $row['grouped']][$column] = ['p75' => (float) $row['p75'], 'samples' => (int) $row['samples']];
            }
        }

        ksort($speed);

        return $speed;
    }

    /**
     * Pages whose largest paint is slowest at the 75th percentile, among pages
     * measured at least $minSamples times, so one slow phone doesn't top the list.
     *
     * @return list<array{path: string, lcp: int, samples: int}>
     */
    public function slowestPages(AnalyticsScope $scope, int $days, int $minSamples, int $limit): array
    {
        $rows = $this->p75($scope, 'lcp_ms', 'path', $days, $minSamples);
        usort($rows, static fn (array $a, array $b): int => (float) $b['p75'] <=> (float) $a['p75']);

        return array_map(static fn (array $row): array => [
            'path' => (string) $row['grouped'],
            'lcp' => (int) $row['p75'],
            'samples' => (int) $row['samples'],
        ], array_slice($rows, 0, max(1, $limit)));
    }

    /**
     * Pages that answered with a server error, most often first, over UTC days.
     *
     * @return list<array{path: string, status: int, errors: int, last_seen: string}>
     */
    public function serverErrors(string $from, string $to, int $limit): array
    {
        $rows = $this->database->query(
            "SELECT path, CAST(JSON_UNQUOTE(JSON_EXTRACT(props, '$.status')) AS UNSIGNED) AS status, COUNT(*) AS errors, MAX(created_at) AS last_seen
             FROM analytics_events
             WHERE name = 'server_error' AND local_date BETWEEN ? AND ?
             GROUP BY path, status
             ORDER BY errors DESC, last_seen DESC
             LIMIT ".max(1, $limit),
            [$from, $to]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'path' => (string) $row['path'],
            'status' => (int) $row['status'],
            'errors' => (int) $row['errors'],
            'last_seen' => (string) $row['last_seen'],
        ], $rows);
    }

    /**
     * The nearest-rank 75th percentile of $column per value of $groupBy: the
     * smallest value at or past three quarters of the sorted measurements.
     *
     * @return list<array{grouped: string, p75: string, samples: string}>
     */
    private function p75(AnalyticsScope $scope, string $column, string $groupBy, int $days, int $minSamples): array
    {
        if (!in_array($column, self::SPEED_COLUMNS, true) || !in_array($groupBy, ['page_type', 'path'], true)) {
            throw new \InvalidArgumentException("Page speed can't be read as {$column} by {$groupBy}.");
        }

        [$where, $params] = $this->scopeWhere($scope);
        $since = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify("-{$days} days");

        // local_date is the partition key; a day of slack covers every blog timezone.
        return $this->database->query(
            "SELECT grouped, MIN(val) AS p75, MAX(n) AS samples
             FROM (SELECT {$groupBy} AS grouped, {$column} AS val,
                          ROW_NUMBER() OVER (PARTITION BY {$groupBy} ORDER BY {$column}) AS rn,
                          COUNT(*) OVER (PARTITION BY {$groupBy}) AS n
                   FROM analytics_events
                   WHERE name = 'page_view' AND suspect = 0 AND {$column} IS NOT NULL AND {$groupBy} IS NOT NULL
                     AND local_date >= ? AND created_at >= ? AND {$where}) measured
             WHERE rn >= CEIL(0.75 * n) AND n >= ?
             GROUP BY grouped",
            [$since->modify('-1 day')->format('Y-m-d'), $since->format('Y-m-d H:i:s'), ...$params, $minSamples]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array{0: string, 1: list<int>}
     */
    private function scopeWhere(AnalyticsScope $scope): array
    {
        $platformTypes = "'".implode("', '", PlatformPages::PAGE_TYPES)."'";

        return match ($scope->type) {
            AnalyticsScope::SITE => ['1 = 1', []],
            AnalyticsScope::PLATFORM => ["blog_id IS NULL AND page_type IN ({$platformTypes})", []],
            AnalyticsScope::BLOG => ['blog_id = ?', [(int) $scope->blogId]],
            AnalyticsScope::POST => $scope->ids === []
                ? ['1 = 0', []]
                : ['post_id IN ('.implode(', ', array_fill(0, count($scope->ids), '?')).')', array_map('intval', $scope->ids)],
            default => throw new \InvalidArgumentException("Page speed isn't read for the {$scope->type} scope."),
        };
    }
}
