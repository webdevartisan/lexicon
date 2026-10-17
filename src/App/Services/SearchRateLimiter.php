<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Helpers\RateLimiter;

/**
 * Per-IP throttle for public search terms.
 *
 * A new search term is a cache miss and a full FULLTEXT query, and any guest can
 * send one, so a script varying the term would keep the database busy. Soft limit
 * only: search is a read, so a short pause is all a blocked client needs.
 */
final class SearchRateLimiter
{
    private const MAX_ATTEMPTS = 20;

    private const DECAY_SECONDS = 60;

    public function __construct(private RateLimiter $limiter) {}

    /**
     * Record a search from this IP and say whether it is blocked.
     *
     * The hit happens first so blocked attempts keep counting and a flooding
     * client's score does not decay back under the limit.
     *
     * @param  string  $ip  Client IP address
     * @return bool True when the search should be refused
     */
    public function hit(string $ip): bool
    {
        $key = $this->key($ip);
        $this->limiter->hit($key, self::DECAY_SECONDS);

        return $this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS, self::DECAY_SECONDS);
    }

    /**
     * Seconds until this IP may search again.
     *
     * @param  string  $ip  Client IP address
     */
    public function availableIn(string $ip): int
    {
        return $this->limiter->availableIn($this->key($ip), self::MAX_ATTEMPTS, self::DECAY_SECONDS);
    }

    private function key(string $ip): string
    {
        return "search:{$ip}";
    }
}
