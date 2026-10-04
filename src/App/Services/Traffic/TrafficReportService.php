<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\TrafficStatsModel;
use App\ValueObjects\TrafficRange;

/**
 * Everything one Traffic page shows for one scope and range, cached for five
 * minutes. The cache key names the scope, never the viewer.
 */
class TrafficReportService
{
    private const CACHE_TTL = 300;

    private const LIST_LIMIT = 10;

    private const EXPORT_LIMIT = 10000;

    /** A country with fewer visitors than this joins "Other", so one reader cannot be picked out. */
    public const COUNTRY_MIN_VISITORS = 3;

    public const OTHER = '__other';

    /** Breakdowns every scope shows. "page" is added for the whole blog only. */
    private const BREAKDOWNS = [
        'channel', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'device', 'browser', 'os', 'country', 'locale',
    ];

    public function __construct(private TrafficStatsModel $stats) {}

    /**
     * @param  list<int>|null  $postIds  Null for the whole blog, else the posts to add up
     * @param  string  $scopeKey  Names the scope in the cache key: "blog", "post:12", "author:7"
     * @return array<string, mixed>
     */
    public function report(int $blogId, ?array $postIds, string $scopeKey, TrafficRange $range): array
    {
        $key = "traffic:{$blogId}:{$scopeKey}:{$range->fromDate()}:{$range->toDate()}";

        return fragment()->rememberData(
            $key,
            fn (): array => $this->build($blogId, $postIds, $scopeKey, $range),
            self::CACHE_TTL,
            false
        );
    }

    /**
     * Every post with views in the range, for the Top posts export.
     *
     * @param  list<int>|null  $postIds
     * @return list<array<string, mixed>>
     */
    public function allPosts(int $blogId, ?array $postIds, TrafficRange $range): array
    {
        return $this->stats->topPosts($blogId, $postIds, $range->fromDate(), $range->toDate(), self::EXPORT_LIMIT);
    }

    /**
     * One breakdown in full, for the CSV export.
     *
     * @param  list<int>|null  $postIds
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public function fullBreakdown(int $blogId, ?array $postIds, string $dimension, TrafficRange $range): array
    {
        $rows = $this->stats->breakdown($blogId, $postIds, $dimension, $range->fromDate(), $range->toDate(), 1000);

        return $dimension === 'country' ? self::groupSmallCountries($rows) : $rows;
    }

    /**
     * @param  list<int>|null  $postIds
     * @return array<string, mixed>
     */
    private function build(int $blogId, ?array $postIds, string $scopeKey, TrafficRange $range): array
    {
        $from = $range->fromDate();
        $to = $range->toDate();
        $previous = $range->previous();

        $totals = $this->stats->totals($blogId, $postIds, $from, $to);
        $before = $this->stats->totals($blogId, $postIds, $previous->fromDate(), $previous->toDate());

        $breakdowns = [];
        foreach ($this->breakdownsFor($postIds) as $dimension) {
            $limit = $dimension === 'country' ? 250 : self::LIST_LIMIT;
            $rows = $this->stats->breakdown($blogId, $postIds, $dimension, $from, $to, $limit);
            $breakdowns[$dimension] = $dimension === 'country'
                ? array_slice(self::groupSmallCountries($rows), 0, self::LIST_LIMIT)
                : $rows;
        }

        $single = str_starts_with($scopeKey, 'post:');

        return [
            'metrics' => $this->metrics($totals, $before),
            'series' => $this->series($blogId, $postIds, $range),
            'breakdowns' => $breakdowns,
            'topPosts' => $single ? [] : $this->stats->topPosts($blogId, $postIds, $from, $to, self::LIST_LIMIT),
        ];
    }

    /**
     * @param  list<int>|null  $postIds
     * @return list<string>
     */
    private function breakdownsFor(?array $postIds): array
    {
        return $postIds === null ? [...self::BREAKDOWNS, 'page'] : self::BREAKDOWNS;
    }

    /**
     * Headline numbers and their change against the previous period of the same length.
     *
     * @param  array<string, int>  $now
     * @param  array<string, int>  $before
     * @return array<string, array{value: float|int|null, previous: float|int|null, change: ?float}>
     */
    private function metrics(array $now, array $before): array
    {
        $derive = static fn (array $t): array => [
            'views' => $t['views'],
            'visitors' => $t['visitors'],
            'avg_read_seconds' => self::ratio($t['engaged_seconds'], $t['engaged_views']),
            'read_ratio' => self::ratio($t['read_views'], $t['views']),
            'bounce_rate' => self::ratio($t['bounces'], $t['visitors']),
            'returning_share' => self::ratio($t['returning_visitors'], $t['identified_visitors']),
            'avg_scroll' => self::ratio($t['scroll_depth_sum'], $t['engaged_views']),
        ];

        $current = $derive($now);
        $previous = $derive($before);
        $metrics = [];

        foreach ($current as $name => $value) {
            $metrics[$name] = [
                'value' => $value,
                'previous' => $previous[$name],
                'change' => self::change($value, $previous[$name]),
            ];
        }

        return $metrics;
    }

    /**
     * @param  list<int>|null  $postIds
     * @return list<array{date: string, views: int, visitors: int}>
     */
    private function series(int $blogId, ?array $postIds, TrafficRange $range): array
    {
        $byDay = $this->stats->series($blogId, $postIds, $range->fromDate(), $range->toDate());
        $series = [];

        foreach ($range->dates() as $date) {
            $series[] = ['date' => $date] + ($byDay[$date] ?? ['views' => 0, 'visitors' => 0]);
        }

        return $series;
    }

    /**
     * @param  list<array{value: string, views: int, visitors: int}>  $rows
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public static function groupSmallCountries(array $rows): array
    {
        $kept = [];
        $other = ['value' => self::OTHER, 'views' => 0, 'visitors' => 0];

        foreach ($rows as $row) {
            if ($row['visitors'] >= self::COUNTRY_MIN_VISITORS) {
                $kept[] = $row;
                continue;
            }

            $other['views'] += $row['views'];
            $other['visitors'] += $row['visitors'];
        }

        if ($other['views'] > 0) {
            $kept[] = $other;
        }

        return $kept;
    }

    public static function ratio(int $part, int $whole): ?float
    {
        return $whole > 0 ? $part / $whole : null;
    }

    public static function change(float|int|null $now, float|int|null $before): ?float
    {
        if ($now === null || $before === null || $before == 0) {
            return null;
        }

        return ($now - $before) / $before;
    }
}
