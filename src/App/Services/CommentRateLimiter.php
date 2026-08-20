<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;

/**
 * Per-IP throttle for reader comment actions.
 *
 * Submission is the one that matters: /comments/create sits outside the auth
 * group, so a guest POST writes a row, busts the post's cached thread and
 * queues mail to the blog team. Without a throttle an unauthenticated script
 * turns one request into outbound email. Voting and reporting are authenticated
 * and idempotent at the data layer, so they get a much looser bucket that only
 * exists to stop a script hammering the endpoints.
 *
 * Both buckets follow CspReportRateLimiter: a soft decaying limit, then a hard
 * lock once a client keeps going after being blocked.
 */
final class CommentRateLimiter
{
    private const SUBMISSION = 'submit';

    private const INTERACTION = 'interact';

    /**
     * Per-bucket tuning: soft limit, decay half-life, soft blocks tolerated in
     * a rolling hour, and how long the hard lock lasts.
     */
    private const BUCKETS = [
        self::SUBMISSION => [
            'max_attempts' => 6,
            'decay_seconds' => 600,
            'max_soft_blocks_per_hour' => 4,
            'hard_lockout_seconds' => 1800,
        ],
        self::INTERACTION => [
            'max_attempts' => 60,
            'decay_seconds' => 300,
            'max_soft_blocks_per_hour' => 6,
            'hard_lockout_seconds' => 900,
        ],
    ];

    private RateLimiter $limiter;

    private CacheService $cache;

    public function __construct(RateLimiter $limiter, CacheService $cache)
    {
        $this->limiter = $limiter;
        $this->cache = $cache;
    }

    /**
     * Record a comment submission from this IP and say whether it is blocked.
     *
     * The hit happens first on purpose: counting blocked attempts too is what
     * stops a flooding client's score decaying back under the limit.
     *
     * @param  string  $ip  Client IP address
     * @return bool True when the attempt should be refused
     */
    public function hitSubmission(string $ip): bool
    {
        return $this->record(self::SUBMISSION, $ip);
    }

    /**
     * Record a vote or report from this IP and say whether it is blocked.
     *
     * @param  string  $ip  Client IP address
     * @return bool True when the attempt should be refused
     */
    public function hitInteraction(string $ip): bool
    {
        return $this->record(self::INTERACTION, $ip);
    }

    /**
     * Seconds until this IP may submit a comment again.
     *
     * @param  string  $ip  Client IP address
     */
    public function submissionAvailableIn(string $ip): int
    {
        return $this->availableIn(self::SUBMISSION, $ip);
    }

    /**
     * Seconds until this IP may vote or report again.
     *
     * @param  string  $ip  Client IP address
     */
    public function interactionAvailableIn(string $ip): int
    {
        return $this->availableIn(self::INTERACTION, $ip);
    }

    /**
     * @param  string  $bucket  One of the BUCKETS keys
     * @param  string  $ip  Client IP address
     * @return bool True when the attempt should be refused
     */
    private function record(string $bucket, string $ip): bool
    {
        $config = self::BUCKETS[$bucket];
        $lockKey = $this->lockKey($bucket, $ip);

        // Already hard locked: no point inflating the score any further.
        if ($this->limiter->isLocked($lockKey)) {
            return true;
        }

        $softKey = $this->softKey($bucket, $ip);
        $this->limiter->hit($softKey, $config['decay_seconds']);

        if (!$this->limiter->tooManyAttempts($softKey, $config['max_attempts'], $config['decay_seconds'])) {
            return false;
        }

        $this->registerSoftBlock($bucket, $ip);

        return true;
    }

    /**
     * @param  string  $bucket  One of the BUCKETS keys
     * @param  string  $ip  Client IP address
     * @return int Seconds to wait, or 0 when not blocked
     */
    private function availableIn(string $bucket, string $ip): int
    {
        $config = self::BUCKETS[$bucket];
        $lockKey = $this->lockKey($bucket, $ip);

        if ($this->limiter->isLocked($lockKey)) {
            $lockData = $this->cache->get($lockKey);

            if ($lockData) {
                $state = json_decode($lockData, true);
                $elapsed = time() - (int) ($state['locked_at'] ?? 0);
                $remaining = max(0, $config['hard_lockout_seconds'] - $elapsed);

                if ($remaining > 0) {
                    return $remaining;
                }
            }
        }

        return $this->limiter->availableIn($this->softKey($bucket, $ip), $config['max_attempts'], $config['decay_seconds']);
    }

    /**
     * Count soft blocks in a rolling hour and hard lock once the threshold hits.
     *
     * @param  string  $bucket  One of the BUCKETS keys
     * @param  string  $ip  Client IP address
     */
    private function registerSoftBlock(string $bucket, string $ip): void
    {
        $config = self::BUCKETS[$bucket];
        $key = "comment:{$bucket}:soft_blocks:{$ip}";
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

        if ($count >= $config['max_soft_blocks_per_hour']) {
            $this->limiter->lock($this->lockKey($bucket, $ip), $config['hard_lockout_seconds']);
        }
    }

    private function softKey(string $bucket, string $ip): string
    {
        return "comment:{$bucket}:{$ip}";
    }

    private function lockKey(string $bucket, string $ip): string
    {
        return "comment:{$bucket}:hardlock:{$ip}";
    }
}
