<?php

declare(strict_types=1);

use App\Services\TestEmailRateLimiter;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * Unit tests for TestEmailRateLimiter: ten test sends an hour per user.
 */
function makeTestEmailThrottle(): TestEmailRateLimiter
{
    return new TestEmailRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));
}

test('ten test sends pass and the eleventh is refused', function () {
    $throttle = makeTestEmailThrottle();

    for ($i = 0; $i < 10; $i++) {
        expect($throttle->tooManyAttempts(5))->toBeFalse();
        $throttle->hit(5);
    }

    expect($throttle->tooManyAttempts(5))->toBeTrue()
        ->and($throttle->availableIn(5))->toBeGreaterThan(0);
});

test('one user using up the budget does not block another', function () {
    $throttle = makeTestEmailThrottle();

    for ($i = 0; $i < 12; $i++) {
        $throttle->hit(5);
    }

    expect($throttle->tooManyAttempts(6))->toBeFalse()
        ->and($throttle->availableIn(6))->toBe(0);
});
