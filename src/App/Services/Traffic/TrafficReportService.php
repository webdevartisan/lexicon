<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\TrafficStatsModel;
use App\ValueObjects\TrafficRange;
use App\ValueObjects\TrafficScope;

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

    public function __construct(private TrafficStatsModel $stats) {}

    /**
     * @return array<string, mixed>
     */
    public function report(TrafficScope $scope, TrafficRange $range): array
    {
        return fragment()->rememberData(
            "traffic:{$scope->key()}:{$range->fromDate()}:{$range->toDate()}",
            fn (): array => $this->build($scope, $range),
            self::CACHE_TTL,
            false
        );
    }

    /**
     * Every post in the scope that had views in the range, for the Top posts export.
     *
     * @return list<array<string, mixed>>
     */
    public function allPosts(TrafficScope $within, TrafficRange $range): array
    {
        return $this->stats->topPosts($within, $range->fromDate(), $range->toDate(), self::EXPORT_LIMIT);
    }

    /**
     * Every blog that had views in the range, for the Top blogs export.
     *
     * @return list<array<string, mixed>>
     */
    public function allBlogs(TrafficRange $range): array
    {
        return $this->stats->topBlogs($range->fromDate(), $range->toDate(), self::EXPORT_LIMIT);
    }

    /**
     * One breakdown in full, for the CSV export.
     *
     * @return list<array{value: string, views: int, visitors: int, name?: string}>
     */
    public function fullBreakdown(TrafficScope $scope, string $dimension, TrafficRange $range): array
    {
        $rows = $this->stats->breakdown($scope, $dimension, $range->fromDate(), $range->toDate(), 1000);

        return match ($dimension) {
            'country' => self::groupSmallCountries($rows),
            'lexicon' => $this->nameBlogs($rows),
            default => $rows,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function build(TrafficScope $scope, TrafficRange $range): array
    {
        $report = [
            'metrics' => $this->metrics($scope, $range),
            'series' => $this->series($scope, $range),
            'breakdowns' => $this->breakdowns($scope, $range),
            'topPosts' => $scope->ranksPosts()
                ? $this->stats->topPosts($scope, $range->fromDate(), $range->toDate(), self::LIST_LIMIT)
                : [],
        ];

        if ($scope->type === TrafficScope::SITE) {
            $report['topBlogs'] = $this->topBlogs($range);
        }

        return $report;
    }

    /**
     * Headline numbers and their change against the previous period of the same length.
     *
     * @return array<string, array{value: float|int|null, previous: float|int|null, change: ?float}>
     */
    private function metrics(TrafficScope $scope, TrafficRange $range): array
    {
        $current = $this->derive($scope, $range);
        $previous = $this->derive($scope, $range->previous());
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
     * @return array<string, float|int|null>
     */
    private function derive(TrafficScope $scope, TrafficRange $range): array
    {
        $t = $this->stats->totals($scope, $range->fromDate(), $range->toDate());

        $metrics = [
            'views' => $t['views'],
            'visitors' => $t['visitors'],
            'avg_read_seconds' => self::ratio($t['engaged_seconds'], $t['engaged_views']),
            'read_ratio' => self::ratio($t['read_views'], $t['views']),
            'bounce_rate' => self::ratio($t['bounces'], $t['visitors']),
            'returning_share' => self::ratio($t['returning_visitors'], $t['identified_visitors']),
            'avg_scroll' => self::ratio($t['scroll_depth_sum'], $t['engaged_views']),
        ];

        if ($scope->type === TrafficScope::SITE) {
            $metrics['active_blogs'] = $this->stats->blogsRead($range->fromDate(), $range->toDate())['total'];
        }

        return $metrics;
    }

    /**
     * One point per day, gaps filled with zeros. The site adds how many blogs were read.
     *
     * @return list<array<string, int|string>>
     */
    private function series(TrafficScope $scope, TrafficRange $range): array
    {
        $byDay = $this->stats->series($scope, $range->fromDate(), $range->toDate());
        $blogs = $scope->type === TrafficScope::SITE
            ? $this->stats->blogsRead($range->fromDate(), $range->toDate())['byDay']
            : null;
        $series = [];

        foreach ($range->dates() as $date) {
            $point = ['date' => $date] + ($byDay[$date] ?? ['views' => 0, 'visitors' => 0]);

            if ($blogs !== null) {
                $point['blogs'] = $blogs[$date] ?? 0;
            }

            $series[] = $point;
        }

        return $series;
    }

    /**
     * @return array<string, list<array{value: string, views: int, visitors: int, name?: string}>>
     */
    private function breakdowns(TrafficScope $scope, TrafficRange $range): array
    {
        $breakdowns = [];

        foreach ($scope->breakdowns() as $dimension) {
            $limit = $dimension === 'country' ? 250 : self::LIST_LIMIT;
            $rows = $this->stats->breakdown($scope, $dimension, $range->fromDate(), $range->toDate(), $limit);
            $breakdowns[$dimension] = match ($dimension) {
                'country' => array_slice(self::groupSmallCountries($rows), 0, self::LIST_LIMIT),
                'lexicon' => $this->nameBlogs($rows),
                default => $rows,
            };
        }

        return $breakdowns;
    }

    /**
     * Adds the name to rows for readers who came from another blog that is still published.
     *
     * @param  list<array{value: string, views: int, visitors: int}>  $rows
     * @return list<array{value: string, views: int, visitors: int, name?: string}>
     */
    private function nameBlogs(array $rows): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $row): ?int => LexiconSource::blogId($row['value']),
            $rows
        )));
        $names = $this->stats->publishedBlogNames($ids);

        return array_map(static function (array $row) use ($names): array {
            $name = $names[LexiconSource::blogId($row['value']) ?? 0] ?? null;

            return $name === null ? $row : $row + ['name' => $name];
        }, $rows);
    }

    /**
     * The busiest blogs, each with its views in the previous period so the table
     * can show which ones are growing.
     *
     * @return list<array<string, mixed>>
     */
    private function topBlogs(TrafficRange $range): array
    {
        $previous = $range->previous();
        $rows = $this->stats->topBlogs($range->fromDate(), $range->toDate(), self::LIST_LIMIT);
        $ids = array_map(static fn (array $row): int => (int) $row['blog_id'], $rows);
        $before = $this->stats->viewsForBlogs($ids, $previous->fromDate(), $previous->toDate());

        return array_map(static function (array $row) use ($before): array {
            $earlier = $before[(int) $row['blog_id']] ?? 0;
            $row['previous_views'] = $earlier;
            $row['change'] = self::change((int) $row['views'], $earlier);

            return $row;
        }, $rows);
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
