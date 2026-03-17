<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class HttpsRedirector
 *
 * Purpose:
 * - Enforce HTTPS for all requests before routing, normalization, or locale handling.
 * - Prevent mixed canonical patterns (http vs https) and improve security/SEO.
 *
 * Behavior:
 * - If the incoming request is not HTTPS, issues a single redirect to the same host + URI with "https://".
 * - Preserves the full path and query string as provided by $request->uri.
 * - Uses 301 in production and 302 in non-production for GET/HEAD requests (based on APP_ENV).
 *
 * Proxy awareness:
 * - Considers common reverse-proxy headers (X-Forwarded-Proto, X-Forwarded-SSL).
 * - These headers must only be set or stripped by trusted proxies; they should not be trusted directly
 *   when the app is exposed to the public internet.
 *
 * Configuration:
 * - APP_ENV=production to enable 301 for GET/HEAD; any other value defaults to 302 to avoid strong caching during development.
 * - FORCE_HTTPS=0 (optional) to disable this redirector in special environments (e.g. local HTTP-only dev).
 *
 * Order in Kernel:
 * - Must run first, before path canonicalization, trailing slash normalization, and locale handling,
 *   so canonicalization happens on HTTPS only.
 *
 * Usage:
 * - Call HttpsRedirector::handle($request) from the pre-routing PipelineRunner (index.php invokes Kernel before dispatch).
 */
final class HttpsRedirector
{
    /**
     * Enforce HTTPS by redirecting non-HTTPS traffic to the https:// equivalent.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Skip in CLI or if env explicitly disables HTTPS enforcement.
        if (PHP_SAPI === 'cli' || ($_ENV['FORCE_HTTPS'] ?? '1') === '0') {
            return;
        }

        // Detect HTTPS, including common proxy headers.
        $httpsOn = false;

        // Direct HTTPS (Apache/Nginx/etc.).
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $httpsOn = true;
        }

        // Some environments expose REQUEST_SCHEME or SERVER_PORT; these can be used as hints.
        if (!$httpsOn && (($_SERVER['REQUEST_SCHEME'] ?? '') === 'https' || ($_SERVER['SERVER_PORT'] ?? '') === '443')) {
            $httpsOn = true;
        }

        // Proxy headers (to be trusted only when the app is behind a known, configured reverse proxy).
        // X-Forwarded-Proto: "https" when the original client connection was HTTPS.
        $xfProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (!$httpsOn && stripos($xfProto, 'https') !== false) {
            $httpsOn = true;
        }

        // X-Forwarded-SSL: "on" in some proxy setups to signal HTTPS at the edge.
        $xfSsl = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
        if (!$httpsOn && strcasecmp($xfSsl, 'on') === 0) {
            $httpsOn = true;
        }

        if ($httpsOn) {
            // Already HTTPS as far as this app can tell; no redirect needed.
            return;
        }

        // Build target https URL from the current host and request URI.
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $request->uri ?? '/';
        $scheme = 'https://';

        $target = $scheme.$host.$uri;

        // Decide redirect status based on environment and method.
        // - For GET/HEAD:
        //     * production: 301 (permanent) – SEO-friendly and widely supported.
        //     * non-prod : 302 (temporary) to avoid caches sticking to HTTPS while developing.
        // - For non-idempotent methods (POST/PUT/PATCH/DELETE), 302 is also acceptable in practice,
        //   but if strict method preservation is ever required, this can be tightened to 307/308.
        $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $status = 302;

        if (in_array($method, ['GET', 'HEAD'], true)) {
            $status = $isProd ? 301 : 302;
        } else {
            // For simplicity and broad compatibility, keep non-GET/HEAD temporary.
            // If method preservation becomes critical (e.g. for APIs), we could:
            // - use 308 in production and 307 in non-production for unsafe methods.
            $status = $isProd ? 302 : 302;
        }

        header('Location: '.$target, true, $status);
        exit;
    }
}
