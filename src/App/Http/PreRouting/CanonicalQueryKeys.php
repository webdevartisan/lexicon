<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class CanonicalQueryKeys
 *
 * Purpose:
 * - Normalize query parameters (order and allowed keys) to avoid duplicate URLs and improve cache hit rates.
 *
 * Behavior:
 * - For GET/HEAD requests on targeted paths, keeps only a whitelist of keys and sorts them.
 * - Redirects to the canonicalized URL if it differs; preserves the path exactly.
 *
 * Config:
 * - Define per-path whitelists in code or config. Example below keeps "page" and "q" only.
 * - Reserved or security-sensitive parameters (e.g. CSRF tokens) should not be whitelisted here.
 *
 * Order:
 * - After host/HTTPS canonicalization, before trailing slash/locale steps to minimize multi-hop redirects.
 */
final class CanonicalQueryKeys
{
    /**
     * Canonicalize query strings for specific paths and redirect when needed.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Only normalize safe idempotent methods.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        // Extract path and raw query string from the request URI.
        $uri = $request->uri ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $qs = parse_url($uri, PHP_URL_QUERY) ?? '';

        // Configure per-path whitelists.
        // Keys not listed here will be dropped from the canonical URL.
        $whitelistByPath = [
            '/blogs' => ['page', 'q'],
            // '/search' => ['q', 'lang', 'sort'],
        ];

        $allowed = $whitelistByPath[$path] ?? null;
        if ($allowed === null) {
            // No canonicalization rules for this path.
            return;
        }

        // Parse the existing query string into an associative array.
        // parse_str() decodes URL encoding and handles repeated keys.
        $params = [];
        if ($qs !== '') {
            parse_str($qs, $params);
        }

        // Keep only allowed keys.
        $filtered = array_intersect_key($params, array_flip($allowed));

        // Sort by key for a single canonical order.
        // This ensures URLs differing only by parameter order become identical.
        ksort($filtered);

        // Build canonical query string.
        $canonicalQs = http_build_query($filtered);
        $canonical = $path.($canonicalQs !== '' ? ('?'.$canonicalQs) : '');

        // If nothing changed, there is no need to redirect.
        if ($canonical === $uri) {
            return;
        }

        // Use 301 in production (SEO-friendly), 302 in other environments.
        $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';
        header('Location: '.$canonical, true, $isProd ? 301 : 302);
        exit;
    }
}
