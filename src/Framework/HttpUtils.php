<?php

declare(strict_types=1);

namespace Framework;

/**
 * HTTP utility functions for server environment detection.
 *
 * Handles common patterns like HTTPS detection that work correctly
 * with reverse proxies (ngrok, CloudFlare, AWS ALB, etc.).
 */
final class HttpUtils
{
    /**
     * Detect if the current request is using HTTPS.
     *
     * Checks multiple sources since proxied connections may not have
     * $_SERVER['HTTPS'] set directly; instead, they send X-Forwarded-Proto.
     *
     * @return bool True if HTTPS connection, false otherwise
     */
    public static function isHttps(): bool
    {
        return
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && stripos($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') !== false);
    }
}
