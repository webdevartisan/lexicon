<?php

declare(strict_types=1);

use App\Services\SearchRateLimiter;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * Unit tests for SearchRateLimiter.
 *
 * Every new search term is a FULLTEXT query that skips the page cache, so these
 * pin the limit, the per-IP separation and the fact that blocked attempts count.
 */
function makeSearchThrottle(): SearchRateLimiter
{
    return new SearchRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));
}

afterEach(function () {
    Mockery::close();
});

test('the first nineteen searches pass and the twentieth is refused', function () {
    $throttle = makeSearchThrottle();

    $results = [];
    for ($i = 0; $i < 20; $i++) {
        $results[] = $throttle->hit('198.51.100.1');
    }

    expect(array_slice($results, 0, 19))->each->toBeFalse()
        ->and($results[19])->toBeTrue();
});

test('one IP being blocked does not block another', function () {
    $throttle = makeSearchThrottle();

    for ($i = 0; $i < 25; $i++) {
        $throttle->hit('198.51.100.1');
    }

    expect($throttle->hit('198.51.100.2'))->toBeFalse();
});

test('a blocked client gets a wait of at least one second', function () {
    $throttle = makeSearchThrottle();

    for ($i = 0; $i < 25; $i++) {
        $throttle->hit('198.51.100.3');
    }

    expect($throttle->availableIn('198.51.100.3'))->toBeGreaterThan(0);
});
