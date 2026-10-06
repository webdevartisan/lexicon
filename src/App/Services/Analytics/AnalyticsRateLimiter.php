<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Framework\Helpers\RateLimiter;

/**
 * Per-IP throttle for the page view beacon. The limit is high because every page
 * sends two requests, and one office address can be many readers.
 */
final class AnalyticsRateLimiter
{
    private const MAX_ATTEMPTS = 300;

    private const DECAY_SECONDS = 600;

    public function __construct(private RateLimiter $limiter) {}

    /**
     * Record the attempt, then say whether this IP is over the limit. Blocked
     * attempts are recorded too, so a client that never stops stays blocked.
     */
    public function hitAndCheck(string $ip): bool
    {
        $key = 'analytics_beacon:'.$ip;

        $this->limiter->hit($key, self::DECAY_SECONDS);

        return $this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS, self::DECAY_SECONDS);
    }
}
