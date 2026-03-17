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
     * @param string $key Rate limit key
     * @param int|null $decaySeconds Decay window (defaults to constructor value)
     * @return void
     */
    public function hit(string $key, ?int $decaySeconds = null): void
    {
        $decay = $decaySeconds ?? $this->defaultDecaySeconds;
        $now   = time();
        
        $state = $this->getState($key, $now);
        
        $score   = $state['score'];
        $updated = $state['updated'];
        
        // Apply exponential decay
        $age = max(0, $now - $updated);
        if ($age > 0 && $score > 0.0) {
            $k = \log(2) / $decay;
            $score *= \exp(-$k * $age);
        }
        
        // Increment
        $score += 1.0;
        
        $this->storeState($key, $score, $now, $decay);
    }

    public function tooManyAttempts(string $key, int $maxAttempts, ?int $decaySeconds = null): bool
    {
        $decay = $decaySeconds ?? $this->defaultDecaySeconds;
        $now   = time();
        
        $state = $this->getState($key, $now);
        
        $score   = $state['score'];
        $updated = $state['updated'];
        
        // Apply exponential decay
        $age = max(0, $now - $updated);
        if ($age > 0 && $score > 0.0) {
            $k = \log(2) / $decay;
            $score *= \exp(-$k * $age);
        }
        
        return $score >= $maxAttempts;
    }

    public function availableIn(string $key, int $maxAttempts, ?int $decaySeconds = null): int
    {
        $decay = $decaySeconds ?? $this->defaultDecaySeconds;
        $data  = $this->cache->get($key);

        if (!$data) {
            return 0;
        }

        $state   = json_decode($data, true);
        $score   = (float) ($state['score'] ?? 0.0);
        $updated = (int)   ($state['updated'] ?? time());
        $now     = time();

        if ($score <= $maxAttempts) {
            return 0;
        }

        // score * e^(-k * t) = maxAttempts  =>  t = (1/k) * ln(score / maxAttempts)
        $k = \log(2) / $decay;
        $t = (1 / $k) * \log($score / $maxAttempts);
        $elapsed = max(0, $now - $updated);

        return (int) max(0, $t - $elapsed);
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

    private function getState(string $key, int $now): array
    {
        $data = $this->cache->get($key);

        if (!$data) {
            return ['score' => 0.0, 'updated' => $now];
        }

        $state = json_decode($data, true);

        return [
            'score'   => (float) ($state['score'] ?? 0.0),
            'updated' => (int)   ($state['updated'] ?? $now),
        ];
    }

    private function storeState(string $key, float $score, int $updated, int $ttl): void
    {
        $this->cache->set($key, json_encode([
            'score'   => $score,
            'updated' => $updated,
        ]), $ttl);
    }
}
