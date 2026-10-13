<?php

declare(strict_types=1);

/**
 * Login sessions: where they are stored and how long they last.
 *
 * Switching the driver signs everyone out, since sessions stored by one driver
 * are invisible to another.
 */
return [
    // file, database or redis. Anything else stops the application at boot.
    'driver' => env('SESSION_DRIVER', 'database'),

    'cookie' => env('SESSION_COOKIE', 'PHPSESSID'),

    // Device list and per-session metadata for signed-in sessions, on every driver.
    // The database driver also keeps guest sessions and the session data here.
    'table' => 'user_sessions',

    // Signed-in sessions end after this much inactivity, or this long after sign-in.
    'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 480),
    'absolute_hours' => (int) env('SESSION_ABSOLUTE_HOURS', 720),

    // Accounts that can open the control panel get a shorter leash.
    'privileged_idle_minutes' => (int) env('SESSION_PRIVILEGED_IDLE_MINUTES', 60),
    'privileged_absolute_hours' => (int) env('SESSION_PRIVILEGED_ABSOLUTE_HOURS', 24),

    // Guest sessions only carry CSRF tokens, flash messages and a pending comment.
    'guest_idle_minutes' => (int) env('SESSION_GUEST_IDLE_MINUTES', 120),

    // A session holding nothing but these keys is not worth storing, so a reader
    // who only browses gets no stored session and no cookie.
    'disposable_keys' => ['locale'],

    // last_activity is written at most this often when nothing else changed.
    'activity_interval_seconds' => 60,

    // How long a request waits for another request on the same session to finish.
    'lock_seconds' => 30,

    // Revoking another device or every other device asks for the password again
    // once per this many minutes.
    'reauth_minutes' => 10,

    'redis' => [
        'host' => env('SESSION_REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('SESSION_REDIS_PORT', 6379),
        'password' => env('SESSION_REDIS_PASSWORD', null),
        'database' => (int) env('SESSION_REDIS_DB', 0),
        'prefix' => env('SESSION_REDIS_PREFIX', 'lx_sess:'),
        'timeout' => 2.0,
    ],

    // DB-IP Lite city file, refreshed by analytics:update-geo. Without it the
    // session list falls back to the country file, and without that, to nothing.
    'geo' => [
        'city_path' => 'storage/geo/dbip-city-lite.mmdb',
        'city_download_url' => 'https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz',
    ],
];
