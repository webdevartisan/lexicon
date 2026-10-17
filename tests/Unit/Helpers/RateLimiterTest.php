<?php

declare(strict_types=1);

use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * The score decays every second, so N attempts made a few seconds apart add up
 * to just under N. These pin that a limit of N still refuses the attempt after
 * the Nth, and that availableIn() agrees with tooManyAttempts().
 */
function seedRateLimitScore(CacheService $cache, string $key, float $score, int $secondsAgo): void
{
    $cache->set($key, json_encode(['score' => $score, 'updated' => time() - $secondsAgo]), 900);
}

test('five attempts spread over a few seconds still hit a limit of five', function () {
    $cache = ThrottleTestHelper::fakeCache();
    $limiter = new RateLimiter($cache);
    seedRateLimitScore($cache, 'k', 5.0, 5);

    expect($limiter->score('k', 900))->toBeLessThan(5.0)
        ->and($limiter->tooManyAttempts('k', 5, 900))->toBeTrue()
        ->and($limiter->availableIn('k', 5, 900))->toBeGreaterThan(0);
});

test('four attempts stay under a limit of five', function () {
    $cache = ThrottleTestHelper::fakeCache();
    $limiter = new RateLimiter($cache);
    seedRateLimitScore($cache, 'k', 4.0, 0);

    expect($limiter->tooManyAttempts('k', 5, 900))->toBeFalse()
        ->and($limiter->availableIn('k', 5, 900))->toBe(0);
});

test('the block lifts once the score has decayed well below the limit', function () {
    $cache = ThrottleTestHelper::fakeCache();
    $limiter = new RateLimiter($cache);
    seedRateLimitScore($cache, 'k', 5.0, 900);

    expect($limiter->tooManyAttempts('k', 5, 900))->toBeFalse()
        ->and($limiter->availableIn('k', 5, 900))->toBe(0);
});

test('the wait it reports ends when the block does', function () {
    $cache = ThrottleTestHelper::fakeCache();
    $limiter = new RateLimiter($cache);
    seedRateLimitScore($cache, 'k', 5.0, 0);

    $wait = $limiter->availableIn('k', 5, 900);
    seedRateLimitScore($cache, 'k', 5.0, $wait);

    expect($limiter->tooManyAttempts('k', 5, 900))->toBeFalse();
});
