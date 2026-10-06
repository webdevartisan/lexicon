<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Google's bands for each page speed measure at the 75th percentile, the ones
 * readers see in PageSpeed Insights and Search Console (web.dev/articles/vitals).
 */
final class PageSpeed
{
    /** Column => [good up to, poor above]. TTFB's bands are web.dev's guidance, not a Core Web Vital. */
    public const BANDS = [
        'lcp_ms' => [2500, 4000],
        'inp_ms' => [200, 500],
        'cls' => [0.1, 0.25],
        'ttfb_ms' => [800, 1800],
    ];

    /** Days of raw page views the numbers are read from, the window Google's own field data uses. */
    public const WINDOW_DAYS = 28;

    /**
     * @return 'good'|'improve'|'poor'
     */
    public static function band(string $column, float $value): string
    {
        [$good, $poor] = self::BANDS[$column] ?? throw new \InvalidArgumentException("No page speed bands for '{$column}'.");

        return match (true) {
            $value <= $good => 'good',
            $value > $poor => 'poor',
            default => 'improve',
        };
    }
}
