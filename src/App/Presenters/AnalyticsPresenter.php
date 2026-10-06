<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Services\Analytics\AnalyticsReportService;
use App\Services\Analytics\LexiconSource;
use App\Services\LocaleRegistry;

/**
 * Turns Insights report numbers into what the page prints: localised numbers,
 * percentages, read times, change badges and readable breakdown labels.
 */
final class AnalyticsPresenter
{
    private \NumberFormatter $numbers;

    /**
     * @param  callable(string, array<string, mixed>=): string  $t  The view's translator
     * @param  string  $scope  site, platform, blog, post or author, which decides what an internal visit is
     */
    public function __construct(
        private string $locale,
        private $t,
        private LocaleRegistry $locales,
        private string $scope,
    ) {
        $this->numbers = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
    }

    public function number(int|float $value): string
    {
        return (string) $this->numbers->format($value);
    }

    /**
     * A number with one decimal place, e.g. pages per visit.
     */
    public function decimal(float $value): string
    {
        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 1);

        return (string) $formatter->format($value);
    }

    /**
     * A page speed value: layout shift as a plain score, times in milliseconds,
     * or in seconds from one second up.
     */
    public function speed(string $column, float $value): string
    {
        $t = $this->t;
        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::DECIMAL);

        if ($column === 'cls') {
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 2);

            return (string) $formatter->format($value);
        }

        if ($value < 1000) {
            return $t('analytics.speed.ms', ['value' => $this->number((int) round($value))]);
        }

        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 1);

        return $t('analytics.speed.seconds', ['value' => (string) $formatter->format($value / 1000)]);
    }

    public function percent(?float $ratio): ?string
    {
        if ($ratio === null) {
            return null;
        }

        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::PERCENT);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $ratio < 0.1 && $ratio > 0 ? 1 : 0);

        return (string) $formatter->format($ratio);
    }

    /**
     * Seconds as m:ss, with the digits the rest of the page uses in this language.
     */
    public function duration(?float $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $whole = (int) round($seconds);
        $padded = new \NumberFormatter($this->locale, \NumberFormatter::DECIMAL);
        $padded->setAttribute(\NumberFormatter::MIN_INTEGER_DIGITS, 2);

        return $this->number(intdiv($whole, 60)).':'.$padded->format($whole % 60);
    }

    /**
     * A headline value the way its card shows it.
     *
     * @param  string  $format  number, decimal, duration, percent, or scroll (a 0 to 100 depth)
     */
    public function metric(string $format, int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($format) {
            'duration' => $this->duration($value),
            'percent' => $this->percent($value),
            'scroll' => $this->percent($value / 100),
            'decimal' => $this->decimal((float) $value),
            default => $this->number($value),
        };
    }

    /**
     * The badge next to a headline value. The tone says whether the change is good
     * news, which for a metric where lower is better runs opposite to the arrow.
     *
     * @param  string  $previous  The earlier value, formatted like the card
     * @param  string  $period  The earlier dates, e.g. "Aug 3, 2026 to Sep 1, 2026"
     * @return array{direction: string, tone: string, short: string, label: string}
     */
    public function change(?float $change, string $previous, string $period, bool $lowerIsBetter = false): array
    {
        $t = $this->t;

        if ($change === null) {
            return ['direction' => 'none', 'tone' => 'neutral', 'short' => '', 'label' => $t('analytics.metrics.noCompare')];
        }

        $percent = (string) $this->percent(abs($change));
        $values = ['percent' => $percent, 'period' => $period, 'previous' => $previous];

        if (abs($change) < 0.005) {
            return [
                'direction' => 'flat',
                'tone' => 'neutral',
                'short' => '0%',
                'label' => $t('analytics.metrics.flatFrom', $values),
            ];
        }

        $up = $change > 0;

        return [
            'direction' => $up ? 'up' : 'down',
            'tone' => $up !== $lowerIsBetter ? 'good' : 'bad',
            'short' => ($up ? '+' : '−').$percent,
            'label' => $t($up ? 'analytics.metrics.upFrom' : 'analytics.metrics.downFrom', $values),
        ];
    }

    public function heading(string $dimension): string
    {
        $t = $this->t;

        return $t($this->scope === 'platform' && $dimension === 'lexicon'
            ? 'analytics.breakdowns.fromBlogs'
            : 'analytics.breakdowns.'.$dimension);
    }

    /**
     * @param  string|null  $name  The blog's name, on a row for readers who came from a blog
     */
    public function label(string $dimension, string $value, ?string $name = null): string
    {
        $t = $this->t;

        if ($value === AnalyticsReportService::OTHER) {
            return $t('analytics.breakdowns.other');
        }

        return match ($dimension) {
            'channel' => $this->channel($value),
            'lexicon' => $name ?? $this->place($value, 'analytics.lexicon.blog'),
            // Administrators see every blog's name, so an unnamed one is gone.
            'came_from' => $name ?? $this->place($value, 'analytics.lexicon.deletedBlog'),
            'category', 'tag', 'author' => $name ?? $t('analytics.breakdowns.unknown'),
            'device' => $t('analytics.devices.'.$value),
            'network' => $t('analytics.shares.'.$value),
            'country' => $this->country($value),
            'locale' => $this->locales->isSupported($value) ? $this->locales->nativeName($value) : $value,
            default => $value,
        };
    }

    /**
     * The biggest named source, another site or a place on Lexicon, otherwise the
     * largest channel apart from readers moving around inside the blog.
     *
     * @param  array<string, list<array{value: string, views: int, visitors: int, name?: string}>>  $breakdowns
     */
    public function topSource(array $breakdowns): ?string
    {
        $source = $breakdowns['source'][0] ?? null;
        $lexicon = $breakdowns['lexicon'][0] ?? null;

        if ($lexicon !== null && $lexicon['views'] > ($source['views'] ?? 0)) {
            return $this->label('lexicon', $lexicon['value'], $lexicon['name'] ?? null);
        }

        if ($source !== null) {
            return $source['value'];
        }

        foreach ($breakdowns['channel'] ?? [] as $row) {
            if ($row['value'] !== 'internal') {
                return $this->label('channel', $row['value']);
            }
        }

        return null;
    }

    /**
     * The overview's opening line, read off the views card and the top source card.
     *
     * @param  array<string, array{value: int|float|null, previous: int|float|null, change: float|null}>  $metrics
     * @param  array<string, list<array{value: string, views: int, visitors: int, name?: string}>>  $breakdowns
     */
    public function summary(array $metrics, array $breakdowns, string $period): ?string
    {
        $views = $metrics['views'] ?? null;

        if ($views === null || !$views['value']) {
            return null;
        }

        $t = $this->t;
        $change = $views['change'];
        $key = match (true) {
            $change === null => 'analytics.summary.views',
            abs($change) < 0.005 => 'analytics.summary.flat',
            $change > 0 => 'analytics.summary.up',
            default => 'analytics.summary.down',
        };
        $line = $t($key, [
            'views' => $this->number($views['value']),
            'percent' => (string) $this->percent(abs($change ?? 0.0)),
            'period' => $period,
        ]);
        $source = $this->topSource($breakdowns);

        return $source === null ? $line : $line.' '.$t('analytics.summary.source', ['source' => $source]);
    }

    public function date(string $ymd): string
    {
        $formatter = new \IntlDateFormatter($this->locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC');

        return (string) $formatter->format(new \DateTimeImmutable($ymd, new \DateTimeZone('UTC')));
    }

    /**
     * A point on the chart: its day, the week starting on it, or its month.
     */
    public function point(string $ymd, string $interval): string
    {
        $t = $this->t;

        return match ($interval) {
            'week' => $t('analytics.chart.weekOf', ['date' => $this->date($ymd)]),
            'month' => $this->pattern($ymd, 'LLLL y'),
            default => $this->date($ymd),
        };
    }

    /**
     * Weekday names in this language, Monday first, keyed by MySQL's DAYOFWEEK (1 is Sunday).
     *
     * @return array<int, string>
     */
    public function weekdays(): array
    {
        $names = [];
        // 2026-01-05 was a Monday.
        foreach ([2, 3, 4, 5, 6, 7, 1] as $offset => $dayOfWeek) {
            $names[$dayOfWeek] = $this->pattern(date('Y-m-d', strtotime('2026-01-05 +'.$offset.' days')), 'EEE');
        }

        return $names;
    }

    /**
     * An hour of the day as this language writes it, e.g. 14:00, or 14 with the HH pattern.
     */
    public function hour(int $hour, string $pattern = 'HH:mm'): string
    {
        return $this->pattern(sprintf('2026-01-05 %02d:00:00', $hour), $pattern);
    }

    private function pattern(string $moment, string $pattern): string
    {
        $formatter = new \IntlDateFormatter($this->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, $pattern);

        return (string) $formatter->format(new \DateTimeImmutable($moment, new \DateTimeZone('UTC')));
    }

    /**
     * Internal and lexicon mean something different on each page: within the blog
     * and elsewhere on Lexicon for a blog, between its own pages and from blogs for the platform.
     */
    private function channel(string $value): string
    {
        $t = $this->t;
        $key = match ([$this->scope, $value]) {
            ['site', 'internal'] => 'withinSite',
            ['platform', 'internal'] => 'platformPages',
            ['platform', 'lexicon'] => 'blogs',
            default => $value,
        };

        return $t('analytics.channels.'.$key);
    }

    /**
     * A place on Lexicon by its kind, or $blogKey for a blog without a name to show.
     */
    private function place(string $value, string $blogKey): string
    {
        $t = $this->t;

        return $t(LexiconSource::blogId($value) !== null ? $blogKey : 'analytics.lexicon.'.$value);
    }

    private function country(string $code): string
    {
        $name = \Locale::getDisplayRegion('-'.$code, $this->locale);

        return $name !== '' && $name !== $code ? $name : $code;
    }
}
