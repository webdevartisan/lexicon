<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class RateLimitPrecheck
 *
 * Purpose:
 * - Provide a lightweight, coarse rate limiter before routing to reduce load during floods or abuse.
 *
 * Behavior:
 * - Implements a fixed window counter using APCu, keyed by client IP and time window.
 * - If APCu is not available or disabled, the limiter becomes a no-op (no rate limiting, no errors).
 * - When the limit (max + burst) is exceeded, responds with HTTP 429 Too Many Requests
 *   and includes a Retry-After header indicating the remaining window in seconds.
 *
 * Configuration:
 * - RATE_LIMIT_ENABLED=0|1 (optional, default 1) to globally toggle this precheck.
 * - RATE_LIMIT_WINDOW (seconds), default 60.
 * - RATE_LIMIT_MAX (requests per window), default 120.
 * - RATE_LIMIT_BURST (extra tokens allowed for short spikes), default 60.
 * - RATE_LIMIT_WHITELIST IPs (comma-separated) to bypass rate limits.
 *
 * Order in Kernel:
 * - After host/HTTPS normalization and maintenance gate,
 *   before path/locale steps to short-circuit abusive traffic early.
 *
 * Usage:
 * - Call RateLimitPrecheck::handle($request) from the pre-routing PipelineRunner.
 */
final class RateLimitPrecheck
{
    /**
     * Apply a coarse, IP-based rate limit using APCu if available.
     *
     * @param  Request  $request  Incoming HTTP request object (not directly used; present for symmetry).
     */
    public static function handle(Request $request): void
    {
        // Optional global enable/disable switch.
        if (($_ENV['RATE_LIMIT_ENABLED'] ?? '1') !== '1') {
            return;
        }

        // Skip if APCu not available or disabled in this SAPI.
        if (!self::apcuAvailable()) {
            return;
        }

        // Determine client IP; if missing, group under a generic bucket.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip === '') {
            $ip = 'unknown';
        }

        // IP whitelist: any address in RATE_LIMIT_WHITELIST bypasses rate limits.
        $whitelistRaw = $_ENV['RATE_LIMIT_WHITELIST'] ?? '';
        $whitelist = array_filter(
            array_map(
                static fn (string $entry): string => trim($entry, " \t\n\r\0\x0B\"'"),
                explode(',', $whitelistRaw)
            )
        );

        if (in_array($ip, $whitelist, true)) {
            return;
        }

        // Read rate limit parameters from environment or use sane defaults.
        $window = max(1, (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60));  // seconds
        $max = max(1, (int) ($_ENV['RATE_LIMIT_MAX'] ?? 120)); // allowed in window
        $burst = max(0, (int) ($_ENV['RATE_LIMIT_BURST'] ?? 60));  // extra tolerance

        $limit = $max + $burst;

        // Compute the current window key: integer division groups timestamps into windows.
        $now = time();
        $currentWindow = (int) ($now / $window);

        // Increment the APCu counter for this IP+window.
        $count = self::incrementApcu($ip, $currentWindow, $window);
        if ($count === null) {
            // If increment fails for any reason, fail-open (no rate limiting).
            return;
        }

        if ($count > $limit) {
            http_response_code(429);
            header('Content-Type: text/plain; charset=utf-8');

            // Advise clients when to retry: time until next window boundary.
            $elapsed = $now - ($currentWindow * $window);
            $remaining = max(1, $window - $elapsed);
            header('Retry-After: '.$remaining);

            echo 'Too Many Requests';
            exit;
        }
    }

    /**
     * Check if APCu is available and enabled for this SAPI.
     */
    private static function apcuAvailable(): bool
    {
        return function_exists('apcu_fetch')
            && function_exists('apcu_inc')
            && function_exists('apcu_store')
            && (bool) ini_get('apc.enabled');
    }

    /**
     * Increment an APCu-based counter for the given IP and window.
     *
     * @param  string  $ip  Client IP or "unknown".
     * @param  int  $currentWindow  Current window index (e.g., time() / windowSize).
     * @param  int  $window  Window size in seconds; used as TTL.
     * @return int|null Current count or null on failure.
     */
    private static function incrementApcu(string $ip, int $currentWindow, int $window): ?int
    {
        $key = 'rl:'.$ip.':'.$currentWindow;

        // apcu_inc() can create the key if missing when the fourth parameter (TTL) is provided.
        $ok = false;
        $val = apcu_inc($key, 1, $ok, $window);

        if (!$ok) {
            // Initialize counter with TTL = window.
            if (!apcu_store($key, 1, $window)) {
                return null;
            }
            $val = 1;
        }

        return (int) $val;
    }
}
