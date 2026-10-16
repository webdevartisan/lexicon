<?php

declare(strict_types=1);

namespace Framework\Helpers;

use Framework\Cache\CacheService;

final class RateLimiter
{
    private CacheService $cache;

    private int $defaultDecaySeconds;

    public function __construct(CacheService $cache, int $defaultDecaySeconds = 900)
    {
        $this->cache = $cache;
        $this->defaultDecaySeconds = $defaultDecaySeconds;
    }

    /**
     * Increment the rate limit counter for a key.
     *
     * Use this to record an attempt after it has been validated/processed.
     *
     * @param  string  $key  Rate limit key
     * @param  int|null  $decaySeconds  Decay window (defaults to constructor value)
     */
    public function hit(string $key, ?int $decaySeconds = null): void
    {
        $decay = $decaySeconds ?? $this->defaultDecaySeconds;

        // Hold the lock across read and write so parallel hits cannot overwrite each other's increment
        $lock = $this->acquireLock($key);

        try {
            $now = time();
            $state = $this->getState($key, $now);

            $score = $state['score'];
            $updated = $state['updated'];

            $age = max(0, $now - $updated);
            if ($age > 0 && $score > 0.0) {
                $k = \log(2) / $decay;
                $score *= \exp(-$k * $age);
            }

            $score += 1.0;

            $this->storeState($key, $score, $now, $decay);
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts, ?int $decaySeconds = null): bool
    {
        return $this->score($key, $decaySeconds) >= $maxAttempts;
    }

    /**
     * The decayed attempt count for a key right now.
     *
     * @param  string  $key  Rate limit key
     * @param  int|null  $decaySeconds  Half-life (defaults to constructor value)
     */
    public function score(string $key, ?int $decaySeconds = null): float
    {
        $decay = $decaySeconds ?? $this->defaultDecaySeconds;
        $now = time();

        $state = $this->getState($key, $now);

        $score = $state['score'];
        $updated = $state['updated'];

        // Apply exponential decay
        $age = max(0, $now - $updated);
        if ($age > 0 && $score > 0.0) {
            $k = \log(2) / $decay;
            $score *= \exp(-$k * $age);
        }

        return $score;
    }

    public function availableIn(string $key, int $maxAttempts, ?int $decaySeconds = null): int
    {
        $decay = $decaySeconds ?? $this->defaultDecaySeconds;
        $data = $this->cache->get($key);

        if (!$data) {
            return 0;
        }

        $state = json_decode($data, true);
        $score = (float) ($state['score'] ?? 0.0);
        $updated = (int) ($state['updated'] ?? time());
        $now = time();

        if ($score < $maxAttempts) {
            return 0;
        }

        // score * e^(-k * t) = maxAttempts  =>  t = (1/k) * ln(score / maxAttempts)
        $k = \log(2) / $decay;
        $t = (1 / $k) * \log($score / $maxAttempts);
        $elapsed = max(0, $now - $updated);

        // Blocked means at least one second of wait; at exactly the limit the decay math gives 0
        return max(1, (int) ceil($t - $elapsed));
    }

    public function clear(string $key): bool
    {
        return $this->cache->delete($key);
    }

    // --- Hard lockout ---

    public function lock(string $key, int $seconds): void
    {
        $this->cache->set($key, json_encode([
            'locked_at' => time(),
        ]), $seconds);
    }

    public function isLocked(string $key): bool
    {
        return (bool) $this->cache->get($key);
    }

    public function clearLock(string $key): bool
    {
        return $this->cache->delete($key);
    }

    // --- Internal helpers ---

    /**
     * Take an exclusive lock on one of a fixed set of lock files.
     *
     * Striping by key hash keeps the number of lock files bounded instead of one per key.
     *
     * @return resource|null Lock handle, or null when the lock file cannot be opened
     */
    private function acquireLock(string $key)
    {
        $path = $this->cache->getCachePath().'/ratelimit-'.(crc32($key) % 64).'.lock';
        $handle = @fopen($path, 'c');

        if ($handle === false) {
            return null;
        }

        flock($handle, LOCK_EX);

        return $handle;
    }

    /**
     * @param  resource|null  $handle  Handle returned by acquireLock()
     */
    private function releaseLock($handle): void
    {
        if ($handle === null) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @return array{score: float, updated: int} Current decay state for the key
     */
    private function getState(string $key, int $now): array
    {
        $data = $this->cache->get($key);

        if (!$data) {
            return ['score' => 0.0, 'updated' => $now];
        }

        $state = json_decode($data, true);

        return [
            'score' => (float) ($state['score'] ?? 0.0),
            'updated' => (int) ($state['updated'] ?? $now),
        ];
    }

    private function storeState(string $key, float $score, int $updated, int $ttl): void
    {
        $this->cache->set($key, json_encode([
            'score' => $score,
            'updated' => $updated,
        ]), $ttl);
    }
}
