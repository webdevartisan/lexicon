<?php

declare(strict_types=1);

namespace Framework;

use Framework\Security\ClientIp;

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
     * The server's own flags always count. X-Forwarded-Proto and X-Forwarded-SSL
     * count only when the connecting peer is in TRUSTED_PROXIES, the same rule
     * ClientIp applies to X-Forwarded-For; otherwise any client could claim HTTPS.
     *
     * @param  array<string,mixed>|null  $server  Server variables; defaults to $_SERVER
     * @return bool True if HTTPS connection, false otherwise
     */
    public static function isHttps(?array $server = null): bool
    {
        $server ??= $_SERVER;

        if (!empty($server['HTTPS']) && strcasecmp((string) $server['HTTPS'], 'off') !== 0) {
            return true;
        }
        if ((string) ($server['SERVER_PORT'] ?? '') === '443'
            || strcasecmp((string) ($server['REQUEST_SCHEME'] ?? ''), 'https') === 0) {
            return true;
        }

        if (!ClientIp::peerIsTrustedProxy($server)) {
            return false;
        }

        // A proxy chain can send "https, http"; the first entry is the scheme the client used
        $proto = trim(explode(',', (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]);

        return strcasecmp($proto, 'https') === 0
            || strcasecmp((string) ($server['HTTP_X_FORWARDED_SSL'] ?? ''), 'on') === 0;
    }
}
