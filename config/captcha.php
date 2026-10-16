<?php

declare(strict_types=1);

/*
 * The proof-of-work check behind sign-in and sign-up (App\Services\Captcha).
 *
 * Measured 2026-10-16: one PBKDF2 derive at cost 1000 takes about 0.5 ms in a
 * desktop browser, so a level-0 challenge (about 250 derives) is roughly 0.1 s
 * on one core and an estimated 0.3 s on a mid-range phone.
 */
return [
    'provider' => (string) env('CAPTCHA_PROVIDER', 'altcha'),

    // Empty means derived from APP_KEY with a purpose label, so the key needs no extra setup.
    'hmac_key' => (string) env('CAPTCHA_HMAC_KEY', ''),

    'cost' => (int) env('CAPTCHA_COST', 1000),
    'counter_min' => (int) env('CAPTCHA_COUNTER_MIN', 125),
    'counter_max' => (int) env('CAPTCHA_COUNTER_MAX', 375),
    'max_level' => (int) env('CAPTCHA_MAX_LEVEL', 3),
    'expires_seconds' => (int) env('CAPTCHA_EXPIRES_SECONDS', 300),

    // New challenges per IP per minute; issuing one costs the server a derive.
    'challenges_per_minute' => 30,

    'login' => [
        // Decayed failure scores (LoginRateLimiter's 5 minute half-life) that start the check.
        'email_failures' => 3,
        'ip_failures' => 10,
        // How long an account stays in "challenge required" after repeated soft blocks.
        'challenge_seconds' => 3600,
        // NIST SP 800-63B caps consecutive failures per account at 100. Failures that got
        // past the check count here, and reaching the cap locks sign-in until a password
        // reset, an admin unlock or the end of the window.
        'solved_failure_ceiling' => 100,
        'ceiling_window_seconds' => 86400,
    ],

    // Accounts created per IP, decayed with a one hour half-life.
    'registrations_per_ip' => 5,
];
