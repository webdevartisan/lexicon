<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\PlatformTrafficModel;
use App\ValueObjects\TrafficRange;

/**
 * Everything the control panel's Traffic page shows for one range, cached for
 * five minutes like the blog pages. Nothing in it depends on who is looking.
 */
class PlatformTrafficReportService
{
    private const CACHE_TTL = 300;

    private const LIST_LIMIT = 10;

    private const EXPORT_LIMIT = 10000;

    public function __construct(private PlatformTrafficModel $traffic) {}

    /**
     * @return array<string, mixed>
     */
    public function report(TrafficRange $range): array
    {
        return fragment()->rememberData(
            "traffic:platform:{$range->fromDate()}:{$range->toDate()}",
            fn (): array => $this->build($range),
            self::CACHE_TTL,
            false
        );
    }

    /**
     * Every blog with views in the range, busiest first, for the CSV.
     *
     * @return list<array<string, mixed>>
     */
    public function allBlogs(TrafficRange $range): array
    {
        return $this->traffic->topBlogs($range->fromDate(), $range->toDate(), self::EXPORT_LIMIT);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allPosts(TrafficRange $range): array
    {
        return $this->traffic->topPosts($range->fromDate(), $range->toDate(), self::EXPORT_LIMIT);
    }

    /**
     * @return list<array{value: string, views: int, visitors: int}>
     */
    public function fullBreakdown(string $dimension, TrafficRange $range): array
    {
        $rows = $this->traffic->breakdown($dimension, $range->fromDate(), $range->toDate(), 1000);

        return $dimension === 'country' ? TrafficReportService::groupSmallCountries($rows) : $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(TrafficRange $range): array
    {
        $from = $range->fromDate();
        $to = $range->toDate();
        $previous = $range->previous();

        $breakdowns = [];
        foreach (PlatformTrafficModel::DIMENSIONS as $dimension) {
            $limit = $dimension === 'country' ? 250 : self::LIST_LIMIT;
            $rows = $this->traffic->breakdown($dimension, $from, $to, $limit);
            $breakdowns[$dimension] = $dimension === 'country'
                ? array_slice(TrafficReportService::groupSmallCountries($rows), 0, self::LIST_LIMIT)
                : $rows;
        }

        return [
            'metrics' => $this->metrics($range, $previous),
            'series' => $this->series($range),
            'topBlogs' => $this->topBlogs($range, $previous),
            'topPosts' => $this->traffic->topPosts($from, $to, self::LIST_LIMIT),
            'breakdowns' => $breakdowns,
            'countingBlogs' => $this->traffic->countingBlogs(),
        ];
    }

    /**
     * Headline numbers and their change against the previous period of the same length.
     *
     * @return array<string, array{value: float|int|null, previous: float|int|null, change: ?float}>
     */
    private function metrics(TrafficRange $range, TrafficRange $previous): array
    {
        $derive = function (TrafficRange $r): array {
            $site = $this->traffic->siteTotals($r->fromDate(), $r->toDate());
            $t = $this->traffic->engagementTotals($r->fromDate(), $r->toDate());

            return [
                'views' => $site['views'],
                'visitors' => $site['visitors'],
                'active_blogs' => $t['active_blogs'],
                'avg_read_seconds' => TrafficReportService::ratio($t['engaged_seconds'], $t['engaged_views']),
                'read_ratio' => TrafficReportService::ratio($t['read_views'], $t['views']),
                'bounce_rate' => TrafficReportService::ratio($t['bounces'], $t['visitors']),
                'returning_share' => TrafficReportService::ratio($t['returning_visitors'], $t['identified_visitors']),
                'avg_scroll' => TrafficReportService::ratio($t['scroll_depth_sum'], $t['engaged_views']),
            ];
        };

        $current = $derive($range);
        $before = $derive($previous);
        $metrics = [];

        foreach ($current as $name => $value) {
            $metrics[$name] = [
                'value' => $value,
                'previous' => $before[$name],
                'change' => TrafficReportService::change($value, $before[$name]),
            ];
        }

        return $metrics;
    }

    /**
     * @return list<array{date: string, views: int, visitors: int, blogs: int}>
     */
    private function series(TrafficRange $range): array
    {
        $byDay = $this->traffic->siteSeries($range->fromDate(), $range->toDate());
        $series = [];

        foreach ($range->dates() as $date) {
            $series[] = ['date' => $date] + ($byDay[$date] ?? ['views' => 0, 'visitors' => 0, 'blogs' => 0]);
        }

        return $series;
    }

    /**
     * The busiest blogs, each with its views in the previous period so the table
     * can show which ones are growing.
     *
     * @return list<array<string, mixed>>
     */
    private function topBlogs(TrafficRange $range, TrafficRange $previous): array
    {
        $rows = $this->traffic->topBlogs($range->fromDate(), $range->toDate(), self::LIST_LIMIT);
        $ids = array_map(static fn (array $row): int => (int) $row['blog_id'], $rows);
        $before = $this->traffic->viewsForBlogs($ids, $previous->fromDate(), $previous->toDate());

        return array_map(static function (array $row) use ($before): array {
            $earlier = $before[(int) $row['blog_id']] ?? 0;
            $row['previous_views'] = $earlier;
            $row['change'] = TrafficReportService::change((int) $row['views'], $earlier);

            return $row;
        }, $rows);
    }
}
