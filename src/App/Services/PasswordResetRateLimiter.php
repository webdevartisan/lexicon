<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Helpers\RateLimiter;
use Framework\Cache\CacheService;

/**
 * Multi-tier password reset rate limiting with soft/hard lockouts.
 * 
 * Implements three rate limit tiers:
 * - Per-email: Prevents targeted reset attacks
 * - Per-IP: Prevents distributed reset abuse
 * - Per-IP-email: Prevents combined attacks
 * 
 * Soft blocks are counted; after N soft blocks in an hour, trigger hard lockout.
 */
final class PasswordResetRateLimiter
{
    private RateLimiter $limiter;
    private CacheService $cache;

    // Soft limits (exponential decay)
    private int $ipMaxAttempts      = 10; // Per-IP limit
    private int $emailMaxAttempts   = 3;  // Per-email limit (stricter than login)
    private int $ipEmailMaxAttempts = 5;  // Per-IP+email limit
    private int $decaySeconds       = 900; // 15 minutes half-life

    // Hard lockout (after repeated soft blocks)
    private int $maxSoftBlocksPerHour = 2;      // Trigger hard lock after N soft blocks
    private int $hardLockoutSeconds   = 3600;   // 1 hour hard lockout

    public function __construct(RateLimiter $limiter, CacheService $cache)
    {
        $this->limiter = $limiter;
        $this->cache   = $cache;
    }

    /**
     * Check if the IP/email combination is currently rate-limited.
     * 
     * This method ONLY checks, does NOT increment counters.
     * Call hit() after a password reset attempt to increment.
     * 
     * @param string $ip Client IP address
     * @param string $email Email being reset
     * @return bool True if rate-limited, false otherwise
     */
    public function tooManyAttempts(string $ip, string $email): bool
    {
        $lockKey = "reset:hardlock:{$email}";

        // Check hard lockout first
        if ($this->limiter->isLocked($lockKey)) {
            return true;
        }

        $emailKey   = "reset:email:{$email}";
        $ipKey      = "reset:ip:{$ip}";
        $ipEmailKey = "reset:ip_email:{$ip}:{$email}";

        // Check all three tiers (WITHOUT incrementing)
        return $this->limiter->tooManyAttempts($ipKey, $this->ipMaxAttempts, $this->decaySeconds)
            || $this->limiter->tooManyAttempts($emailKey, $this->emailMaxAttempts, $this->decaySeconds)
            || $this->limiter->tooManyAttempts($ipEmailKey, $this->ipEmailMaxAttempts, $this->decaySeconds);
    }

    /**
     * Record a password reset attempt and check if soft block threshold reached.
     * 
     * Increments all three rate limit counters (IP, email, IP+email).
     * If any tier is exceeded, register a soft block.
     * 
     * @param string $ip Client IP address
     * @param string $email Email that attempted reset
     * @return void
     */
    public function hit(string $ip, string $email): void
    {
        $emailKey   = "reset:email:{$email}";
        $ipKey      = "reset:ip:{$ip}";
        $ipEmailKey = "reset:ip_email:{$ip}:{$email}";

        // Increment all three counters
        $this->limiter->hit($emailKey, $this->decaySeconds);
        $this->limiter->hit($ipKey, $this->decaySeconds);
        $this->limiter->hit($ipEmailKey, $this->decaySeconds);

        // Check if NOW exceeds limits (after incrementing)
        $emailBlocked   = $this->limiter->tooManyAttempts($emailKey, $this->emailMaxAttempts, $this->decaySeconds);
        $ipBlocked      = $this->limiter->tooManyAttempts($ipKey, $this->ipMaxAttempts, $this->decaySeconds);
        $ipEmailBlocked = $this->limiter->tooManyAttempts($ipEmailKey, $this->ipEmailMaxAttempts, $this->decaySeconds);

        // Register soft block if any tier exceeded
        if ($emailBlocked || $ipBlocked || $ipEmailBlocked) {
            $this->registerSoftBlock($email);
        }
    }

    /**
     * Clear all rate limit counters for an IP/email (e.g., after successful reset).
     * 
     * @param string $ip Client IP address
     * @param string $email Email address
     * @return void
     */
    public function clear(string $ip, string $email): void
    {
        $emailKey   = "reset:email:{$email}";
        $ipKey      = "reset:ip:{$ip}";
        $ipEmailKey = "reset:ip_email:{$ip}:{$email}";
        $lockKey    = "reset:hardlock:{$email}";

        $this->limiter->clear($emailKey);
        $this->limiter->clear($ipKey);
        $this->limiter->clear($ipEmailKey);
        $this->limiter->clearLock($lockKey);
        
        // Also clear soft block counter
        $this->cache->delete("reset:soft_blocks:{$email}");
    }

    /**
     * Get seconds until rate limit expires (highest of all three tiers).
     * 
     * Returns the longest wait time from either hard lockout or soft limits.
     * 
     * @param string $ip Client IP address
     * @param string $email Email address
     * @return int Seconds until allowed, or 0 if not rate-limited
     */
    public function availableIn(string $ip, string $email): int
    {
        $lockKey = "reset:hardlock:{$email}";
        
        // Check hard lockout first
        if ($this->limiter->isLocked($lockKey)) {
            $lockData = $this->cache->get($lockKey);
            
            if ($lockData) {
                $state = json_decode($lockData, true);
                $lockedAt = (int) ($state['locked_at'] ?? 0);
                $elapsed = time() - $lockedAt;
                $remaining = max(0, $this->hardLockoutSeconds - $elapsed);
                
                if ($remaining > 0) {
                    return $remaining;
                }
            }
        }

        // No hard lockout - check soft limits
        $emailKey   = "reset:email:{$email}";
        $ipKey      = "reset:ip:{$ip}";
        $ipEmailKey = "reset:ip_email:{$ip}:{$email}";

        $emailWait   = $this->limiter->availableIn($emailKey, $this->emailMaxAttempts, $this->decaySeconds);
        $ipWait      = $this->limiter->availableIn($ipKey, $this->ipMaxAttempts, $this->decaySeconds);
        $ipEmailWait = $this->limiter->availableIn($ipEmailKey, $this->ipEmailMaxAttempts, $this->decaySeconds);

        return max($emailWait, $ipWait, $ipEmailWait);
    }

    /**
     * Track soft blocks and trigger hard lockout after threshold exceeded.
     * 
     * Soft blocks are counted per-email within a rolling 1-hour window.
     * After maxSoftBlocksPerHour, trigger a hard lockout.
     * 
     * @param string $email Email address
     * @return void
     */
    private function registerSoftBlock(string $email): void
    {
        $key = "reset:soft_blocks:{$email}";
        $now = time();

        $data  = $this->cache->get($key);
        $state = $data ? json_decode($data, true) : ['count' => 0, 'window_start' => $now];

        $windowStart = (int) ($state['window_start'] ?? $now);
        $count       = (int) ($state['count'] ?? 0);

        // Reset window if expired
        if ($now - $windowStart > 3600) {
            $windowStart = $now;
            $count = 0;
        }

        $count++;

        $this->cache->set($key, json_encode([
            'count'        => $count,
            'window_start' => $windowStart,
        ]), 3600);

        // Trigger hard lockout after threshold
        if ($count >= $this->maxSoftBlocksPerHour) {
            $lockKey = "reset:hardlock:{$email}";
            $this->limiter->lock($lockKey, $this->hardLockoutSeconds);
        }
    }
}
