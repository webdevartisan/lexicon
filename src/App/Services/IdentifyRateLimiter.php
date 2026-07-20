<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;

/**
 * Per-IP throttle for the email-existence lookup behind the auth modal.
 *
 * Mirrors LoginRateLimiter: a soft decaying limit, then a hard lockout once a
 * client keeps hammering the endpoint after being blocked.
 *
 * The soft limit alone is not enough here. RateLimiter recomputes the decayed
 * score on every check against the timestamp of the last recorded attempt, so
 * a caller that returns early without recording blocked attempts lets that
 * score slide back under the threshold roughly once a minute, forever. Every
 * attempt is recorded here, blocked or not, which keeps the score climbing
 * while abuse continues.
 *
 * Keyed by IP only. The email is deliberately not part of the key, otherwise
 * an attacker would get a fresh budget for every address they try.
 */
final class IdentifyRateLimiter
{
    private RateLimiter $limiter;

    private CacheService $cache;

    private int $maxAttempts = 20;

    private int $decaySeconds = 900; // 15 minute half-life

    // Escalation. Deliberately looser than LoginRateLimiter's 3 blocks per
    // hour: this endpoint sits on public blog pages, so a busy NAT or office
    // proxy can reach the soft limit without anyone doing anything wrong.
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
     * Drop every counter for an IP. Used by tests and admin tooling.
     *
     * @param  string  $ip  Client IP address
     */
    public function clear(string $ip): void
    {
        $this->limiter->clear($this->softKey($ip));
        $this->limiter->clearLock($this->lockKey($ip));
        $this->cache->delete("identify:soft_blocks:{$ip}");
    }

    /**
     * Count soft blocks in a rolling hour and hard lock once the threshold hits.
     *
     * @param  string  $ip  Client IP address
     */
    private function registerSoftBlock(string $ip): void
    {
        $key = "identify:soft_blocks:{$ip}";
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
        return "identify:{$ip}";
    }

    private function lockKey(string $ip): string
    {
        return "identify:hardlock:{$ip}";
    }
}
