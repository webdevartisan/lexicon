<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Services\LocaleRegistry;
use App\Services\Traffic\TrafficReportService;

/**
 * Turns Traffic report numbers into what the page prints: localised numbers,
 * percentages, read times, change badges and readable breakdown labels.
 */
final class TrafficPresenter
{
    private \NumberFormatter $numbers;

    /**
     * @param  callable(string, array<string, mixed>=): string  $t  The view's translator
     */
    public function __construct(
        private string $locale,
        private $t,
        private LocaleRegistry $locales,
    ) {
        $this->numbers = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
    }

    public function number(int|float $value): string
    {
        return (string) $this->numbers->format($value);
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
     * @param  string  $format  number, duration, percent, or scroll (a 0 to 100 depth)
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
            return ['direction' => 'none', 'tone' => 'neutral', 'short' => '', 'label' => $t('traffic.metrics.noCompare')];
        }

        $percent = (string) $this->percent(abs($change));
        $values = ['percent' => $percent, 'period' => $period, 'previous' => $previous];

        if (abs($change) < 0.005) {
            return [
                'direction' => 'flat',
                'tone' => 'neutral',
                'short' => '0%',
                'label' => $t('traffic.metrics.flatFrom', $values),
            ];
        }

        $up = $change > 0;

        return [
            'direction' => $up ? 'up' : 'down',
            'tone' => $up !== $lowerIsBetter ? 'good' : 'bad',
            'short' => ($up ? '+' : '−').$percent,
            'label' => $t($up ? 'traffic.metrics.upFrom' : 'traffic.metrics.downFrom', $values),
        ];
    }

    public function label(string $dimension, string $value): string
    {
        $t = $this->t;

        if ($value === TrafficReportService::OTHER) {
            return $t('traffic.breakdowns.other');
        }

        return match ($dimension) {
            'channel' => $t('traffic.channels.'.$value),
            'device' => $t('traffic.devices.'.$value),
            'country' => $this->country($value),
            'locale' => $this->locales->isSupported($value) ? $this->locales->nativeName($value) : $value,
            default => $value,
        };
    }

    /**
     * The biggest outside source: a named referrer if there is one, otherwise the
     * largest channel apart from readers moving around inside the blog.
     *
     * @param  array<string, list<array{value: string, views: int, visitors: int}>>  $breakdowns
     */
    public function topSource(array $breakdowns): ?string
    {
        $source = $breakdowns['source'][0] ?? null;

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

    public function date(string $ymd): string
    {
        $formatter = new \IntlDateFormatter($this->locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, 'UTC');

        return (string) $formatter->format(new \DateTimeImmutable($ymd, new \DateTimeZone('UTC')));
    }

    private function country(string $code): string
    {
        $name = \Locale::getDisplayRegion('-'.$code, $this->locale);

        return $name !== '' && $name !== $code ? $name : $code;
    }
}
