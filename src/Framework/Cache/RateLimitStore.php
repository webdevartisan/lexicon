<?php

declare(strict_types=1);

namespace Framework\Cache;

/**
 * Cache storage for rate limiter state, kept apart from the page cache.
 *
 * Lockouts must outlive a cache clear, file-limit eviction and CACHE_ENABLED=false,
 * so this store lives in its own directory, is always on and has no file cap.
 * Entries still expire through their TTL and are removed by the normal GC.
 */
final class RateLimitStore extends CacheService
{
    /**
     * @param  string  $path  Absolute path to the limiter directory
     */
    public function __construct(string $path)
    {
        parent::__construct(cachePath: $path, enabled: true, maxFiles: 0);
    }
}
