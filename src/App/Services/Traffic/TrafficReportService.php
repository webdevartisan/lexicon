<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Interfaces\TrafficReader;
use App\Models\ActivityLogModel;
use App\Models\TrafficEventModel;
use App\Models\TrafficNotFoundModel;
use App\Models\TrafficRawStatsModel;
use App\Models\TrafficSql;
use App\Models\TrafficStatsModel;
use App\ValueObjects\TrafficRange;
use App\ValueObjects\TrafficScope;

/**
 * Everything one Traffic page shows for one scope and range, cached for five
 * minutes. The cache key names the scope, the range, the comparison and any
 * filters, never the viewer.
 *
 * A page narrowed by filters is read from raw views; the caller checks that the
 * range is inside raw retention first.
 */
class TrafficReportService
{
    private const CACHE_TTL = 300;

    private const LIST_LIMIT = 10;

    private const EXPORT_LIMIT = 10000;

    /** A country with fewer visitors than this joins "Other", so one reader cannot be picked out. */
    public const COUNTRY_MIN_VISITORS = 3;

    public const OTHER = '__other';

    /** Past this many days the chart shows weeks, and past the second, months. */
    private const WEEKS_AFTER_DAYS = 90;

    private const MONTHS_AFTER_DAYS = 180;

    /** A blog is rising from this many views in a period, at half again its previous views. */
    private const RISING_MIN_VIEWS = 50;

    private const RISING_GROWTH = 1.5;

    /** A day is a spike at this many times the usual day, from this many views. */
    public const SPIKE_FACTOR = 3;

    public const SPIKE_MIN_VIEWS = 50;

    /** The usual day is the average over this many days before. */
    public const USUAL_DAYS = 28;

    /** Breakdowns whose values are ids that need a name. */
    private const NAMED = ['category', 'tag', 'author'];

    /**
     * @param  int  $readSeconds  A view with at least this much engaged time is a read
     * @param  int  $bounceSeconds  A visitor with one view and less engaged time than this bounced
     */
    public function __construct(
        private TrafficStatsModel $stats,
        private TrafficRawStatsModel $raw,
        private TrafficEventModel $events,
        private TrafficNotFoundModel $missing,
        private ActivityLogModel $activity,
        private int $readSeconds,
        private int $bounceSeconds,
    ) {}

    /**
     * @param  array<string, string>  $filters  Breakdown => value, already checked against TrafficSql::FILTERABLE
     * @return array<string, mixed>
     */
    public function report(TrafficScope $scope, TrafficRange $range, array $filters = []): array
    {
        ksort($filters);
        $filterKey = $filters === [] ? '' : ':'.md5((string) json_encode($filters));

        return fragment()->rememberData(
            "traffic:{$scope->key()}:{$range->key()}{$filterKey}",
            fn (): array => $this->build($scope, $range, $filters),
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
     * @return list<array<string, mixed>>
     */
    public function fullBreakdown(TrafficScope $scope, string $dimension, TrafficRange $range): array
    {
        $rows = $this->stats->breakdown($scope, $dimension, $range->fromDate(), $range->toDate(), 1000);

        return $this->name($dimension, $rows);
    }

    /**
     * How each post did since it was published, and what a usual first week
     * looks like on the blog. Only posts published after counting began get a
     * label, since the first week of an older one was never seen.
     *
     * @param  list<int>  $postIds
     * @return array{posts: array<int, array<string, mixed>>, usualFirstWeek: ?int}
     */
    public function performance(int $blogId, array $postIds, string $today, ?string $countingSince): array
    {
        $usual = null;
        if ($countingSince !== null) {
            $weeks = $this->stats->firstWeeks($blogId, $countingSince, $today);
            $usual = $weeks === [] ? null : self::median($weeks);
        }

        $posts = [];
        foreach ($this->stats->performance($blogId, $postIds, $today) as $postId => $row) {
            $age = (int) (new \DateTimeImmutable($row['published']))->diff(new \DateTimeImmutable($today))->days;
            $seenFromStart = $countingSince !== null && $row['published'] >= $countingSince;
            $posts[$postId] = $row + [
                'age_days' => $age,
                'seen_from_start' => $seenFromStart,
                'label' => $seenFromStart ? self::shape($row, $age) : null,
            ];
        }

        return ['posts' => $posts, 'usualFirstWeek' => $usual];
    }

    /**
     * The site or kind of traffic that sent the most readers, in words an email
     * can use, or null when nobody came.
     */
    public function topSourceName(TrafficScope $scope, string $from, string $to): ?string
    {
        $source = $this->stats->breakdown($scope, 'source', $from, $to, 1)[0] ?? null;
        if ($source !== null) {
            return $source['value'];
        }

        $channels = [
            'search' => 'search engines', 'social' => 'social media', 'email' => 'email', 'referral' => 'other sites',
            'lexicon' => 'elsewhere on Lexicon', 'direct' => 'direct visits',
        ];

        foreach ($this->stats->breakdown($scope, 'channel', $from, $to, 3) as $row) {
            if (isset($channels[$row['value']])) {
                return $channels[$row['value']];
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function build(TrafficScope $scope, TrafficRange $range, array $filters): array
    {
        $reader = $filters === [] ? $this->stats : $this->raw->filtered($filters, $this->readSeconds, $this->bounceSeconds);
        $from = $range->fromDate();
        $to = $range->toDate();

        $report = [
            'metrics' => $this->metrics($reader, $scope, $range),
            'series' => $this->series($reader, $scope, $range),
            'interval' => self::interval($range),
            'breakdowns' => $this->breakdowns($reader, $scope, $range),
            'topPosts' => $scope->ranksPosts() ? $reader->topPosts($scope, $from, $to, self::LIST_LIMIT) : [],
            'hourly' => $reader->hourly($scope, $from, $to),
            'goals' => $this->events->goalCounts($scope, $from, $to),
            'outbound' => $this->events->clickBreakdown('outbound', $scope, $from, $to, self::LIST_LIMIT),
            'downloads' => $this->events->clickBreakdown('download', $scope, $from, $to, self::LIST_LIMIT),
            'missing' => match ($scope->type) {
                TrafficScope::BLOG => $this->missing->topMissing((int) $scope->blogId, $from, $to, self::LIST_LIMIT),
                TrafficScope::PLATFORM => $this->missing->topMissing(null, $from, $to, self::LIST_LIMIT),
                default => [],
            },
            'markers' => $this->markers($scope, $range),
        ];

        if ($scope->type === TrafficScope::SITE) {
            $report['topBlogs'] = $this->topBlogs($range);
        }

        return $report;
    }

    /**
     * Headline numbers and their change against the comparison period.
     *
     * @return array<string, array{value: float|int|null, previous: float|int|null, change: ?float}>
     */
    private function metrics(TrafficReader $reader, TrafficScope $scope, TrafficRange $range): array
    {
        $current = $this->derive($reader, $scope, $range);
        $previous = $this->derive($reader, $scope, $range->previous());
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
    private function derive(TrafficReader $reader, TrafficScope $scope, TrafficRange $range): array
    {
        $t = $reader->totals($scope, $range->fromDate(), $range->toDate());

        $metrics = [
            'views' => $t['views'],
            'visitors' => $t['visitors'],
            'avg_read_seconds' => self::ratio($t['engaged_seconds'], $t['engaged_views']),
            'read_ratio' => self::ratio($t['read_views'], $t['views']),
            'bounce_rate' => self::ratio($t['bounces'], $t['visitors']),
            'returning_share' => self::ratio($t['returning_visitors'], $t['identified_visitors']),
            'avg_scroll' => self::ratio($t['scroll_depth_sum'], $t['engaged_views']),
        ];

        foreach ([25, 50, 75, 100] as $step) {
            $metrics["scroll_{$step}"] = self::ratio($t["scroll_{$step}"], $t['engaged_views']);
        }

        if ($scope->type === TrafficScope::SITE) {
            $metrics['active_blogs'] = $reader->blogsRead($range->fromDate(), $range->toDate())['total'];
        }

        return $metrics;
    }

    /**
     * One point per day, week or month, with the comparison period's numbers for
     * the same stretch. Gaps are zeros. The site adds how many blogs were read.
     *
     * @return list<array<string, int|string>>
     */
    private function series(TrafficReader $reader, TrafficScope $scope, TrafficRange $range): array
    {
        $previous = $range->previous();
        $now = $reader->series($scope, $range->fromDate(), $range->toDate());
        $before = $reader->series($scope, $previous->fromDate(), $previous->toDate());
        $earlierDates = $previous->dates();
        $points = [];

        foreach ($range->dates() as $i => $date) {
            $bucket = self::bucketStart($date, self::interval($range));
            $earlier = $before[$earlierDates[$i] ?? ''] ?? ['views' => 0, 'visitors' => 0];
            $today = $now[$date] ?? ['views' => 0, 'visitors' => 0];

            $points[$bucket] ??= ['date' => $bucket, 'to' => $date, 'views' => 0, 'visitors' => 0, 'previous_views' => 0, 'previous_visitors' => 0];
            $points[$bucket]['to'] = $date;
            $points[$bucket]['views'] += $today['views'];
            $points[$bucket]['visitors'] += $today['visitors'];
            $points[$bucket]['previous_views'] += $earlier['views'];
            $points[$bucket]['previous_visitors'] += $earlier['visitors'];
        }

        if ($scope->type === TrafficScope::SITE) {
            // A week or month needs its own count: a blog read on several days of it counts once.
            $byDay = self::interval($range) === 'day' ? $reader->blogsRead($range->fromDate(), $range->toDate())['byDay'] : null;
            foreach ($points as &$point) {
                $point['blogs'] = $byDay !== null
                    ? $byDay[$point['date']] ?? 0
                    : $reader->blogsRead((string) $point['date'], (string) $point['to'])['total'];
            }
            unset($point);
        }

        return array_values($points);
    }

    public static function interval(TrafficRange $range): string
    {
        return match (true) {
            $range->days() > self::MONTHS_AFTER_DAYS => 'month',
            $range->days() > self::WEEKS_AFTER_DAYS => 'week',
            default => 'day',
        };
    }

    private static function bucketStart(string $date, string $interval): string
    {
        $day = new \DateTimeImmutable($date);

        return match ($interval) {
            'month' => $day->format('Y-m-01'),
            'week' => $day->modify('-'.((int) $day->format('N') - 1).' days')->format('Y-m-d'),
            default => $date,
        };
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function breakdowns(TrafficReader $reader, TrafficScope $scope, TrafficRange $range): array
    {
        $breakdowns = [];

        foreach ($scope->listBreakdowns() as $dimension) {
            $limit = $dimension === 'country' ? 250 : self::LIST_LIMIT;
            $rows = $reader->breakdown($scope, $dimension, $range->fromDate(), $range->toDate(), $limit);
            $breakdowns[$dimension] = $dimension === 'country'
                ? array_slice(self::groupSmallCountries($rows), 0, self::LIST_LIMIT)
                : $this->name($dimension, $rows);
        }

        return $breakdowns;
    }

    /**
     * Adds a name to rows whose value is an id: another blog, a category, a tag or an author.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function name(string $dimension, array $rows): array
    {
        if ($dimension === 'country') {
            return self::groupSmallCountries($rows);
        }

        if ($dimension === 'lexicon') {
            return $this->nameBlogs($rows);
        }

        if (!in_array($dimension, self::NAMED, true)) {
            return $rows;
        }

        $names = $this->stats->labels($dimension, array_map(static fn (array $row): int => (int) $row['value'], $rows));

        return array_map(static function (array $row) use ($names): array {
            $name = $names[(int) $row['value']] ?? null;

            return $name === null ? $row : $row + ['name' => $name];
        }, $rows);
    }

    /**
     * Adds the name to rows for readers who came from another blog that is still published.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function nameBlogs(array $rows): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $row): ?int => LexiconSource::blogId((string) $row['value']),
            $rows
        )));
        $names = $this->stats->publishedBlogNames($ids);

        return array_map(static function (array $row) use ($names): array {
            $name = $names[LexiconSource::blogId((string) $row['value']) ?? 0] ?? null;

            return $name === null ? $row : $row + ['name' => $name];
        }, $rows);
    }

    /**
     * Things that happened on the blog in the range, for markers on the chart:
     * posts going out and theme changes.
     *
     * @return list<array{day: string, kind: string, title: string}>
     */
    private function markers(TrafficScope $scope, TrafficRange $range): array
    {
        if (!in_array($scope->type, [TrafficScope::BLOG, TrafficScope::POST], true)) {
            return [];
        }

        $blogId = (int) $scope->blogId;
        $postIds = $scope->type === TrafficScope::POST ? $scope->ids : null;
        $markers = array_map(
            static fn (array $post): array => ['day' => $post['day'], 'kind' => 'post', 'title' => $post['title']],
            $this->stats->publishedBetween($blogId, $postIds, $range->fromDate(), $range->toDate())
        );

        if ($scope->type === TrafficScope::BLOG) {
            foreach ($this->activity->daysOf('blog.theme_changed', 'blog', $blogId, $range->fromDate(), $range->toDate()) as $day) {
                $markers[] = ['day' => $day, 'kind' => 'theme', 'title' => ''];
            }
        }

        usort($markers, static fn (array $a, array $b): int => $a['day'] <=> $b['day']);

        return $markers;
    }

    /**
     * The busiest blogs, each with its views in the comparison period so the table
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
     * A post's shape over its life: most of it in the first week, or still read
     * months on. Null when it is neither, or too young to tell.
     *
     * @param  array{total: int, first_week: int, first_month: int, last_month: int}  $row
     */
    private static function shape(array $row, int $ageDays): ?string
    {
        if ($ageDays < 7) {
            return 'new';
        }

        if ($ageDays >= 60 && $row['last_month'] >= 10 && $row['first_month'] <= $row['last_month'] * 3) {
            return 'evergreen';
        }

        if ($ageDays >= 30 && $row['total'] >= 20 && $row['first_week'] >= 0.6 * $row['total']) {
            return 'spike';
        }

        return null;
    }

    /**
     * @param  non-empty-list<int>  $values
     */
    private static function median(array $values): int
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : intdiv($values[$middle - 1] + $values[$middle], 2);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function groupSmallCountries(array $rows): array
    {
        $kept = [];
        $other = ['value' => self::OTHER, 'views' => 0, 'visitors' => 0, 'read_views' => 0, 'engaged_views' => 0, 'engaged_seconds' => 0];

        foreach ($rows as $row) {
            if ($row['visitors'] >= self::COUNTRY_MIN_VISITORS) {
                $kept[] = $row;
                continue;
            }

            foreach (['views', 'visitors', 'read_views', 'engaged_views', 'engaged_seconds'] as $count) {
                $other[$count] += (int) ($row[$count] ?? 0);
            }
        }

        if ($other['views'] > 0) {
            $kept[] = $other;
        }

        return $kept;
    }

    /**
     * Whether a filter request names breakdowns this scope can be narrowed by.
     *
     * @param  mixed  $raw  The f query parameter
     * @return array<string, string>
     */
    public static function filtersFrom(mixed $raw, TrafficScope $scope): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $filters = [];
        foreach ($raw as $dimension => $value) {
            if (
                is_string($dimension) && is_string($value) && $value !== '' && $value !== self::OTHER && mb_strlen($value) <= 191
                && in_array($dimension, TrafficSql::FILTERABLE, true) && in_array($dimension, $scope->breakdowns(), true)
            ) {
                $filters[$dimension] = $value;
            }
        }

        return $filters;
    }

    /**
     * Whether a blog read this much now, after that much before, is taking off.
     * One that had no readers before needs twice the minimum.
     */
    public static function isRising(int $now, int $before): bool
    {
        if ($now < self::RISING_MIN_VIEWS) {
            return false;
        }

        return $before === 0 ? $now >= 2 * self::RISING_MIN_VIEWS : $now >= self::RISING_GROWTH * $before;
    }

    /**
     * Whether a day with $today views is far busier than the usual day, and busy
     * enough for that to mean something.
     */
    public static function isSpike(int $today, float $usual, int $minViews = self::SPIKE_MIN_VIEWS): bool
    {
        return $today >= $minViews && $today >= self::SPIKE_FACTOR * max(1.0, $usual);
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
