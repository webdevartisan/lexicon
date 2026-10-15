<?php

declare(strict_types=1);

use App\Services\LoginRateLimiter;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * findByEmail matches case-insensitively, so the limiter has to treat case
 * variants of an address as one identity or each variant gets fresh buckets.
 */
function makeLoginLimiter(): LoginRateLimiter
{
    $cache = ThrottleTestHelper::fakeCache();

    return new LoginRateLimiter(new RateLimiter($cache), $cache);
}

test('case and whitespace variants of an email share one bucket', function () {
    $limiter = makeLoginLimiter();
    $variants = ['Victim@x.com', 'vICTIM@x.com', ' victim@X.com ', 'VICTIM@X.COM', 'victim@x.com'];

    foreach ($variants as $i => $email) {
        // A different IP per attempt isolates the per-email tier
        $limiter->hit('10.0.0.' . $i, $email);
    }

    expect($limiter->tooManyAttempts('10.0.0.99', 'victim@x.com'))->toBeTrue();
    expect($limiter->tooManyAttempts('10.0.0.99', 'VICTIM@X.COM'))->toBeTrue();
});

test('clearing one casing clears the others', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit('10.0.0.' . $i, 'Victim@x.com');
    }
    $limiter->clear('10.0.0.1', 'victim@x.com');

    expect($limiter->tooManyAttempts('10.0.0.99', 'VICTIM@x.com'))->toBeFalse();
});

test('different emails stay in separate buckets', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit('10.0.0.' . $i, 'victim@x.com');
    }

    expect($limiter->tooManyAttempts('10.0.0.99', 'other@x.com'))->toBeFalse();
});
