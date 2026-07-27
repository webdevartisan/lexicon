<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;

/**
 * Per-IP throttle for the unauthenticated /csp-report endpoint.
 *
 * Mirrors IdentifyRateLimiter: a soft decaying limit, then a hard lockout once
 * a client keeps hammering the endpoint after being blocked. Needed because
 * report-uri is a public, CSRF-exempt POST route - anyone can flood it with
 * fake reports to bloat storage/logs/csp-violations.log or waste CPU on
 * every request.
 *
 * Keyed by IP only, same as IdentifyRateLimiter.
 */
final class CspReportRateLimiter
{
    private RateLimiter $limiter;

    private CacheService $cache;

    private int $maxAttempts = 20;

    private int $decaySeconds = 900; // 15 minute half-life

    private int $maxSoftBlocksPerHour = 10;

    private int $hardLockoutSeconds = 900;

    public function __construct(RateLimiter $limiter, CacheService $cache)
    {
        $this->limiter = $limiter;
        $this->cache = $cache;
    }

    /**
     * Whether this IP is currently blocked.
     *
     * Checks only. Call hit() to record the attempt.
     *
     * @param  string  $ip  Client IP address
     */
    public function tooManyAttempts(string $ip): bool
    {
        if ($this->limiter->isLocked($this->lockKey($ip))) {
            return true;
        }

        return $this->limiter->tooManyAttempts($this->softKey($ip), $this->maxAttempts, $this->decaySeconds);
    }

    /**
     * Record an attempt from this IP.
     *
     * Must be called for blocked attempts too, otherwise the score decays back
     * under the limit and the throttle leaks. Skipped once a hard lock is in
     * place so a flood cannot inflate the score without bound.
     *
     * @param  string  $ip  Client IP address
     */
    public function hit(string $ip): void
    {
        if ($this->limiter->isLocked($this->lockKey($ip))) {
            return;
        }

        $softKey = $this->softKey($ip);
        $this->limiter->hit($softKey, $this->decaySeconds);

        if ($this->limiter->tooManyAttempts($softKey, $this->maxAttempts, $this->decaySeconds)) {
            $this->registerSoftBlock($ip);
        }
    }

    /**
     * Seconds until this IP may try again.
     *
     * @param  string  $ip  Client IP address
     * @return int Seconds to wait, or 0 when not blocked
     */
    public function availableIn(string $ip): int
    {
        $lockKey = $this->lockKey($ip);

        if ($this->limiter->isLocked($lockKey)) {
            $lockData = $this->cache->get($lockKey);

            if ($lockData) {
                $state = json_decode($lockData, true);
                $elapsed = time() - (int) ($state['locked_at'] ?? 0);
                $remaining = max(0, $this->hardLockoutSeconds - $elapsed);

                if ($remaining > 0) {
                    return $remaining;
                }
            }
        }

        return $this->limiter->availableIn($this->softKey($ip), $this->maxAttempts, $this->decaySeconds);
    }

    /**
     * Count soft blocks in a rolling hour and hard lock once the threshold hits.
     *
     * @param  string  $ip  Client IP address
     */
    private function registerSoftBlock(string $ip): void
    {
        $key = "csp_report:soft_blocks:{$ip}";
        $now = time();

        $data = $this->cache->get($key);
        $state = $data ? json_decode($data, true) : ['count' => 0, 'window_start' => $now];

        $windowStart = (int) ($state['window_start'] ?? $now);
        $count = (int) ($state['count'] ?? 0);

        if ($now - $windowStart > 3600) {
            $windowStart = $now;
            $count = 0;
        }

        $count++;

        $this->cache->set($key, json_encode([
            'count' => $count,
            'window_start' => $windowStart,
        ]), 3600);

        if ($count >= $this->maxSoftBlocksPerHour) {
            $this->limiter->lock($this->lockKey($ip), $this->hardLockoutSeconds);
        }
    }

    private function softKey(string $ip): string
    {
        return "csp_report:{$ip}";
    }

    private function lockKey(string $ip): string
    {
        return "csp_report:hardlock:{$ip}";
    }
}
