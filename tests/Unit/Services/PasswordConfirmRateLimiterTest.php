<?php

declare(strict_types=1);

use App\Services\PasswordConfirmRateLimiter;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * The Preferences email gate and the Security current-password field both
 * verify the account password. Without a limit a stolen session is an unlimited
 * password oracle. This pins the per-user throttle over an in-memory cache.
 */
function makePasswordConfirmLimiter(): PasswordConfirmRateLimiter
{
    return new PasswordConfirmRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));
}

test('allows the first few confirmations then blocks', function () {
    $limiter = makePasswordConfirmLimiter();
    $userId = 42;

    expect($limiter->tooManyAttempts($userId))->toBeFalse();

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit($userId);
    }

    expect($limiter->tooManyAttempts($userId))->toBeTrue();
});

test('a successful confirmation clears the counter', function () {
    $limiter = makePasswordConfirmLimiter();
    $userId = 42;

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit($userId);
    }
    $limiter->clear($userId);

    expect($limiter->tooManyAttempts($userId))->toBeFalse();
});

test('the limit is per user', function () {
    $limiter = makePasswordConfirmLimiter();

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit(1);
    }

    expect($limiter->tooManyAttempts(1))->toBeTrue();
    expect($limiter->tooManyAttempts(2))->toBeFalse();
});
