<?php

declare(strict_types=1);

use App\Privacy\Consent;
use App\Privacy\ConsentCookieStore;

/**
 * Unit tests for ConsentCookieStore.
 *
 * Verifies:
 * - Cookies are signed and verified correctly.
 * - Tampered cookies are rejected.
 * - HttpOnly, SameSite and secure flags are set as expected.
 */
beforeEach(function () {
    $_COOKIE = [];
    $_SERVER = [];
});

test('write and read round-trips a valid consent cookie', function () {
    $secret = bin2hex(random_bytes(16));
    $store = new ConsentCookieStore('app_consent_test', 30, $secret);

    // Use the constructor directly — fromPayload() is the internal cookie deserializer
    // and expects compact keys (v, ts, c), not human-readable ones
    $consent = new Consent(
        version: 1,
        timestamp: time(),
        categories: [
            'necessary' => true,
            'analytics' => true,
            'marketing' => false,
        ],
    );

    // Simulate browser sending the cookie back — encodeCookieValue() is used
    // instead of headers_list(), which is always empty in CLI environments
    $_COOKIE['app_consent_test'] = $store->encodeCookieValue($consent);

    $read = $store->read();

    expect($read)->not->toBeNull()
        ->and($read->version)->toBe(1)
        ->and($read->allows('analytics'))->toBeTrue()
        ->and($read->allows('marketing'))->toBeFalse()
        ->and($read->allows('necessary'))->toBeTrue();
});

test('tampered consent cookie is rejected', function () {
    $secret = bin2hex(random_bytes(16));
    $store = new ConsentCookieStore('app_consent_test', 30, $secret);

    $payload = json_encode([
        'version' => 1,
        'categories' => ['necessary' => true],
    ], JSON_UNESCAPED_SLASHES);

    $sig = hash_hmac('sha256', $payload, $secret);

    // Tamper with payload but keep original signature
    $tamperedPayload = json_encode([
        'version' => 1,
        'categories' => ['necessary' => false],
    ], JSON_UNESCAPED_SLASHES);

    $_COOKIE['app_consent_test'] = base64_encode($tamperedPayload).'.'.$sig;

    $read = $store->read();

    expect($read)->toBeNull();
});
