<?php

declare(strict_types=1);

use App\Services\LoginRateLimiter;
use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;
use Tests\Helpers\ThrottleTestHelper;

/**
 * findByEmail matches case-insensitively, so the limiter has to treat case
 * variants of an address as one identity or each variant gets fresh buckets.
 * Nothing keyed on the email alone may refuse the owner: the email tier puts
 * the account behind the sign-in check instead.
 */
function makeLoginLimiter(?CacheService $cache = null): LoginRateLimiter
{
    $cache ??= ThrottleTestHelper::fakeCache();

    return new LoginRateLimiter(new RateLimiter($cache), $cache);
}

test('case and whitespace variants of an email share one bucket', function () {
    $limiter = makeLoginLimiter();
    $variants = ['Victim@x.com', 'vICTIM@x.com', ' victim@X.com '];

    foreach ($variants as $i => $email) {
        // A different IP per attempt isolates the per-email tier
        $limiter->hit('10.0.0.'.$i, $email);
    }

    expect($limiter->requiresChallenge('10.0.0.99', 'victim@x.com'))->toBeTrue();
    expect($limiter->requiresChallenge('10.0.0.99', 'VICTIM@X.COM'))->toBeTrue();
});

test('clearing one casing clears the others', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit('10.0.0.'.$i, 'Victim@x.com');
    }
    $limiter->clear('10.0.0.1', 'victim@x.com');

    expect($limiter->requiresChallenge('10.0.0.99', 'VICTIM@x.com'))->toBeFalse();
});

test('different emails stay in separate buckets', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 5; $i++) {
        $limiter->hit('10.0.0.'.$i, 'victim@x.com');
    }

    expect($limiter->requiresChallenge('10.0.0.99', 'other@x.com'))->toBeFalse();
});

test('two failures ask for nothing, the third starts the check', function () {
    $limiter = makeLoginLimiter();

    $limiter->hit('10.0.0.1', 'owner@x.com');
    $limiter->hit('10.0.0.2', 'owner@x.com');
    expect($limiter->requiresChallenge('10.0.0.9', 'owner@x.com'))->toBeFalse();

    $limiter->hit('10.0.0.3', 'owner@x.com');
    expect($limiter->requiresChallenge('10.0.0.9', 'owner@x.com'))->toBeTrue();
});

test('ten failures from one IP put every email from that IP behind the check', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 10; $i++) {
        $limiter->hit('10.0.0.1', "user{$i}@x.com");
    }

    expect($limiter->requiresChallenge('10.0.0.1', 'fresh@x.com'))->toBeTrue()
        ->and($limiter->requiresChallenge('10.0.0.2', 'fresh@x.com'))->toBeFalse();
});

test('failures from many IPs never refuse the owner from another IP', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 30; $i++) {
        $limiter->hit('10.0.'.$i.'.1', 'owner@x.com', true);
    }

    expect($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeFalse()
        ->and($limiter->requiresChallenge('192.0.2.1', 'owner@x.com'))->toBeTrue();
});

test('repeated soft blocks put the account behind the check, not a lock', function () {
    $cache = ThrottleTestHelper::fakeCache();
    $limiter = makeLoginLimiter($cache);

    for ($i = 0; $i < 7; $i++) {
        $limiter->hit('10.0.0.'.$i, 'owner@x.com', true);
    }

    expect($cache->get('login:challenge:owner@x.com'))->not->toBeNull()
        ->and($cache->get('login:hardlock:owner@x.com'))->toBeNull()
        ->and($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeFalse()
        ->and($limiter->challengeLevel('owner@x.com'))->toBe(3);
});

test('attempts refused at the check count against the IP only', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 25; $i++) {
        $limiter->hitIp('10.0.0.1');
    }

    expect($limiter->requiresChallenge('192.0.2.1', 'owner@x.com'))->toBeFalse()
        ->and($limiter->tooManyAttempts('10.0.0.1', 'owner@x.com'))->toBeTrue()
        ->and($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeFalse();
});

test('the IP tiers still refuse the IP doing the guessing', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 8; $i++) {
        $limiter->hit('10.0.0.1', 'owner@x.com', true);
    }

    expect($limiter->tooManyAttempts('10.0.0.1', 'owner@x.com'))->toBeTrue()
        ->and($limiter->availableIn('10.0.0.1', 'owner@x.com'))->toBeGreaterThan(0);
});

test('100 failures that passed the check lock the account for the ceiling window', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 99; $i++) {
        $limiter->hit('10.'.intdiv($i, 250).'.'.($i % 250).'.1', 'owner@x.com', true);
    }
    expect($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeFalse();

    $limiter->hit('10.9.9.9', 'owner@x.com', true);

    expect($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeTrue()
        ->and($limiter->availableIn('192.0.2.1', 'owner@x.com'))->toBeGreaterThan(86000);
});

test('failures without the check do not count toward the ceiling', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 120; $i++) {
        $limiter->hit('10.'.intdiv($i, 250).'.'.($i % 250).'.1', 'owner@x.com');
    }

    expect($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeFalse();
});

test('a password reset lifts the check and the ceiling lock', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 100; $i++) {
        $limiter->hit('10.'.intdiv($i, 250).'.'.($i % 250).'.1', 'owner@x.com', true);
    }
    $limiter->clearAccount('Owner@X.com');

    expect($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeFalse()
        ->and($limiter->requiresChallenge('192.0.2.1', 'owner@x.com'))->toBeFalse()
        ->and($limiter->challengeLevel('owner@x.com'))->toBe(0);
});

test('a password reset leaves an admin block in place', function () {
    $cache = ThrottleTestHelper::fakeCache();
    $limiter = makeLoginLimiter($cache);
    $cache->set('login:hardlock:owner@x.com', (string) json_encode(['locked_at' => time(), 'manual' => true]), 3600);

    $limiter->clearAccount('owner@x.com');

    expect($limiter->tooManyAttempts('192.0.2.1', 'owner@x.com'))->toBeTrue();
});

test('a successful login clears the check state', function () {
    $limiter = makeLoginLimiter();

    for ($i = 0; $i < 7; $i++) {
        $limiter->hit('10.0.0.'.$i, 'owner@x.com', true);
    }
    $limiter->clear('192.0.2.1', 'owner@x.com');

    expect($limiter->requiresChallenge('192.0.2.1', 'owner@x.com'))->toBeFalse();
});
