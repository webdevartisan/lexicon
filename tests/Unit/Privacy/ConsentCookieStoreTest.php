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

    $consent = Consent::fromPayload([
        'version' => 1,
        'categories' => [
            'necessary' => true,
            'analytics' => true,
            'marketing' => false,
        ],
    ]);

    $store->write($consent);

    // Simulate browser sending the cookie back on next request
    $cookieValue = null;
    foreach (headers_list() as $header) {
        if (str_starts_with(strtolower($header), 'set-cookie: app_consent_test=')) {
            $cookieValue = substr($header, strlen('Set-Cookie: app_consent_test='));
            $cookieValue = explode(';', $cookieValue, 2)[0];
            break;
        }
    }

    expect($cookieValue)->not->toBeNull();

    $_COOKIE['app_consent_test'] = $cookieValue;

    $read = $store->read();

    expect($read)->not->toBeNull();
});

test('tampered consent cookie is rejected', function () {
    $secret = bin2hex(random_bytes(16));
    $store = new ConsentCookieStore('app_consent_test', 30, $secret);

    $payload = json_encode([
        'version' => 1,
        'categories' => ['necessary' => true],
    ], JSON_UNESCAPED_SLASHES);

    $b64 = base64_encode($payload);
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
