<?php

declare(strict_types=1);

use App\Services\RateLimitAdminService;
use Framework\Cache\CacheService;
use Framework\Cache\RateLimitStore;
use Framework\Helpers\RateLimiter;

/**
 * Lockouts live in their own store so a page-cache clear or eviction cannot
 * lift them, and an admin can list and lift them by email or IP.
 */
beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/limiter_test_'.uniqid();
    $this->store = new RateLimitStore($this->dir);
    $this->limiter = new RateLimiter($this->store);
    $this->service = new RateLimitAdminService($this->store);
});

afterEach(function () {
    foreach (glob($this->dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->dir);
});

test('a lockout survives a page cache flood and clear', function () {
    $this->limiter->lock('login:hardlock:a@example.com', 3600);

    $pageCache = new CacheService($this->dir.'_page', true, 1, 100, 5);
    for ($i = 0; $i < 50; $i++) {
        $pageCache->set("page{$i}", 'x', 60);
    }
    $pageCache->clear();

    expect($this->limiter->isLocked('login:hardlock:a@example.com'))->toBeTrue();

    $pageCache->clear();
    @rmdir($this->dir.'_page');
});

test('lists hard locks with limiter label, subject kind and time left', function () {
    $this->limiter->lock('login:hardlock:a@example.com', 3600);
    $this->limiter->lock('comment:submit:hardlock:203.0.113.7', 900);
    $this->limiter->hit('login:ip:203.0.113.7');

    $rows = $this->service->blocked();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('Login')
        ->and($rows[0]['kind'])->toBe('email')
        ->and($rows[0]['subject'])->toBe('a@example.com')
        ->and($rows[0]['seconds_left'])->toBeGreaterThan(3500)
        ->and($rows[1]['label'])->toBe('Comment submissions')
        ->and($rows[1]['kind'])->toBe('ip');
});

test('forgetting an email clears its locks and counters whatever the case', function () {
    $this->limiter->lock('login:hardlock:Victim@Example.com', 3600);
    $this->limiter->hit('login:ip_email:203.0.113.7:Victim@Example.com');
    $this->limiter->lock('login:hardlock:other@example.com', 3600);

    expect($this->store->forgetSubject('victim@example.com'))->toBe(2)
        ->and($this->limiter->isLocked('login:hardlock:other@example.com'))->toBeTrue();
});

test('forgetting an IP leaves look-alike addresses alone', function () {
    $this->store->set('login:ip:203.0.113.7', 'a', 60);
    $this->store->set('login:ip:203.0.113.77', 'b', 60);
    $this->store->set('login:ip:13.0.113.7', 'c', 60);

    $this->store->forgetSubject('203.0.113.7');

    expect($this->store->get('login:ip:203.0.113.7'))->toBeNull()
        ->and($this->store->get('login:ip:203.0.113.77'))->toBe('b')
        ->and($this->store->get('login:ip:13.0.113.7'))->toBe('c');
});

test('IPv6 lock subjects are listed and cleared', function () {
    $this->limiter->lock('identify:hardlock:2001:db8::1', 900);

    expect($this->service->blocked()[0]['subject'])->toBe('2001:db8::1')
        ->and($this->store->forgetSubject('2001:db8::1'))->toBe(1)
        ->and($this->service->blocked())->toBe([]);
});

test('unlock rejects input that is neither an email nor an IP', function (string $subject) {
    $this->limiter->lock('login:hardlock:a@example.com', 3600);

    expect(fn () => $this->service->unlock($subject, '127.0.0.1'))
        ->toThrow(InvalidArgumentException::class)
        ->and($this->limiter->isLocked('login:hardlock:a@example.com'))->toBeTrue();
})->with(['', 'login:hardlock', '*', 'not an email']);

test('block refuses an admin blocking their own IP', function () {
    expect(fn () => $this->service->block('203.0.113.9', '1h', '203.0.113.9'))
        ->toThrow(InvalidArgumentException::class, 'your own IP')
        ->and($this->store->get('manual:hardlock:203.0.113.9'))->toBeNull();
});

test('block refuses a duration that is not offered', function (string $duration) {
    expect(fn () => $this->service->block('someone@example.com', $duration, '127.0.0.1'))
        ->toThrow(InvalidArgumentException::class)
        ->and($this->service->blocked())->toBe([]);
})->with(['', '99y', '3600']);

test('block refuses a subject that is neither an email nor an IP', function () {
    expect(fn () => $this->service->block('not valid', '1h', '127.0.0.1'))
        ->toThrow(InvalidArgumentException::class);
});

test('manual blocks are listed as manual with their real block time', function () {
    $now = time();
    // Same shape block() writes: locked_at backdated for the login wrappers, blocked_at is the real time
    $this->store->set('manual:hardlock:198.51.100.5', json_encode([
        'locked_at' => $now + 86400 - 3600,
        'blocked_at' => $now,
        'manual' => true,
    ]), 86400);
    $this->limiter->lock('identify:hardlock:203.0.113.7', 900);

    $rows = array_column($this->service->blocked(), null, 'subject');

    expect($rows['198.51.100.5']['manual'])->toBeTrue()
        ->and($rows['198.51.100.5']['label'])->toBe('Site-wide block')
        ->and($rows['198.51.100.5']['locked_at'])->toBe($now)
        ->and($rows['203.0.113.7']['manual'])->toBeFalse();
});
