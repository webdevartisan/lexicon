<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Services\CommentRateLimiter;
use ArrayObject;
use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;
use Mockery;

/**
 * Builds rate limiters over an in-memory cache for tests.
 *
 * Shared by the CommentRateLimiter unit tests and the CommentController
 * throttle tests so both drive the same limiter, over the same double, rather
 * than each keeping its own copy that could drift.
 */
final class ThrottleTestHelper
{
    /**
     * Cache double backed by a plain array, with no TTL expiry.
     *
     * Expiry never fires inside a test run, and the limiter's own decay is what
     * these tests are about, so ignoring TTL keeps the double small and honest.
     */
    public static function fakeCache(): CacheService
    {
        $store = new ArrayObject();

        $cache = Mockery::mock(CacheService::class);

        $cache->shouldReceive('get')->andReturnUsing(
            fn (string $key): ?string => $store[$key] ?? null
        );

        $cache->shouldReceive('set')->andReturnUsing(function (string $key, string $content) use ($store): bool {
            $store[$key] = $content;

            return true;
        });

        $cache->shouldReceive('delete')->andReturnUsing(function (string $key) use ($store): bool {
            unset($store[$key]);

            return true;
        });

        return $cache;
    }

    /**
     * A comment throttle wired to a fresh in-memory cache.
     *
     * @return array{0: CommentRateLimiter, 1: CacheService} Limiter and its cache
     */
    public static function commentThrottle(): array
    {
        $cache = self::fakeCache();

        return [new CommentRateLimiter(new RateLimiter($cache), $cache), $cache];
    }

    /**
     * Age the stored decay state so the limiter sees an idle period.
     *
     * Rewrites the same last-updated stamp the limiter reads back, which is
     * how these tests skip time without mocking it.
     *
     * @param  string  $key  Soft-limit cache key
     * @param  int  $seconds  How far back to push the stamp
     */
    public static function age(CacheService $cache, string $key, int $seconds): void
    {
        $state = json_decode((string) $cache->get($key), true);
        $state['updated'] = (int) $state['updated'] - $seconds;

        $cache->set($key, json_encode($state), 3600);
    }
}
