<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsDailyEventModel;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\SignupFunnelModel;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;

/**
 * Everything the admin Sign-ups page shows for one range, cached for five minutes:
 * sign-ups against the site's visitors, what new accounts did in their first
 * days, and where the visits that led to sign-ups came from.
 */
class SignupReportService
{
    /** How long a new account has to become an active reader or a writer. */
    public const WINDOW_DAYS = 14;

    private const CACHE_TTL = 300;

    private const LIST_LIMIT = 10;

    public function __construct(
        private SignupFunnelModel $funnel,
        private AnalyticsDailyEventModel $events,
        private AnalyticsStatsModel $stats,
        private BlogModel $blogs,
        private string $sharedHandle,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(AnalyticsRange $range): array
    {
        return fragment()->rememberData(
            "signups:{$range->fromDate()}:{$range->toDate()}",
            fn (): array => $this->build($range),
            self::CACHE_TTL,
            false
        );
    }

    /**
     * One list in full, for the CSV export.
     *
     * @return list<array{value: string, signups: int, name?: string}>
     */
    public function fullBreakdown(string $dimension, AnalyticsRange $range): array
    {
        return $this->breakdown($dimension, $range, 1000);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(AnalyticsRange $range): array
    {
        $current = $this->numbers($range);
        $previous = $this->numbers($range->previous());
        $metrics = [];

        foreach ($current['metrics'] as $name => $value) {
            $metrics[$name] = [
                'value' => $value,
                'previous' => $previous['metrics'][$name],
                'change' => AnalyticsReportService::change($value, $previous['metrics'][$name]),
            ];
        }

        $breakdowns = [];
        foreach (AnalyticsDailyEventModel::breakdowns() as $dimension) {
            $breakdowns[$dimension] = $this->breakdown($dimension, $range, self::LIST_LIMIT);
        }

        return [
            'metrics' => $metrics,
            'funnel' => $current['funnel'],
            'series' => $this->series($range),
            'breakdowns' => $breakdowns,
        ];
    }

    /**
     * @return array{metrics: array<string, int|float|null>, funnel: array<string, int>}
     */
    private function numbers(AnalyticsRange $range): array
    {
        $visitors = $this->stats->totals(AnalyticsScope::site(), $range->fromDate(), $range->toDate())['visitors'];
        $outcomes = $this->funnel->outcomes($range->fromDate(), $range->toDate(), self::WINDOW_DAYS, $this->sharedHandle);
        $judged = $outcomes['judged'];

        return [
            'metrics' => [
                'signups' => $outcomes['accounts'],
                'signup_rate' => AnalyticsReportService::ratio($outcomes['accounts'], $visitors),
                'active_readers' => AnalyticsReportService::ratio($outcomes['active_readers'], $judged),
                'started_blog' => AnalyticsReportService::ratio($outcomes['started_blog'], $judged),
                'published_post' => AnalyticsReportService::ratio($outcomes['published_post'], $judged),
            ],
            'funnel' => ['visitors' => $visitors] + $outcomes,
        ];
    }

    /**
     * One point per day, gaps filled with zeros.
     *
     * @return list<array{date: string, signups: int}>
     */
    private function series(AnalyticsRange $range): array
    {
        $byDay = $this->funnel->accountsByDay($range->fromDate(), $range->toDate(), $this->sharedHandle);

        return array_map(
            static fn (string $date): array => ['date' => $date, 'signups' => $byDay[$date] ?? 0],
            $range->dates()
        );
    }

    /**
     * @return list<array{value: string, signups: int, name?: string}>
     */
    private function breakdown(string $dimension, AnalyticsRange $range, int $limit): array
    {
        $rows = $this->events->signupBreakdown($dimension, $range->fromDate(), $range->toDate(), $limit);

        return $dimension === 'came_from' ? $this->nameBlogs($rows) : $rows;
    }

    /**
     * Names the blogs people signed up from, whatever their status, since only
     * administrators see this page. A deleted blog stays unnamed.
     *
     * @param  list<array{value: string, signups: int}>  $rows
     * @return list<array{value: string, signups: int, name?: string}>
     */
    private function nameBlogs(array $rows): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $row): ?int => LexiconSource::blogId($row['value']),
            $rows
        )));
        $names = array_column($this->blogs->findByIds($ids), 'blog_name', 'id');

        return array_map(static function (array $row) use ($names): array {
            $name = $names[LexiconSource::blogId($row['value']) ?? 0] ?? null;

            return $name === null ? $row : $row + ['name' => (string) $name];
        }, $rows);
    }
}
