<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class SubdomainNormalizer
 *
 * Purpose:
 * - Enforce a single canonical host by redirecting between "www" and apex (root) domain.
 * - Prevent duplicate content across www and non-www hosts; stabilizes cookies and sessions.
 *
 * Behavior:
 * - If FORCE_WWW=1, redirect example.com  -> https://www.example.com (preserving path/query).
 * - If FORCE_WWW=0, redirect www.example.com -> https://example.com (preserving path/query).
 * - No redirect for other subdomains (e.g. api.example.com), unless PRIMARY_DOMAIN allows it.
 * - Uses 301 in production and 302 in other environments.
 *
 * Configuration:
 * - APP_ENV=production for 301 redirects; any other value → 302.
 * - FORCE_WWW in .env, default "0" (force apex). FORCE_WWW="1" forces www.
 * - PRIMARY_DOMAIN (optional), e.g. "example.com":
 *     * Only normalize hosts that are exactly PRIMARY_DOMAIN or "www." . PRIMARY_DOMAIN.
 *     * Avoids touching unrelated hosts like "api.otherdomain.com".
 *
 * Order in Kernel:
 * - Should run after HttpsRedirector (so host canonicalization happens on HTTPS URLs).
 * - Should run before path canonicalization, trailing slash normalization, and locale intake.
 *
 * Usage:
 * - Call SubdomainNormalizer::handle($request) from the pre-routing PipelineRunner.
 */
final class SubdomainNormalizer
{
    /**
     * Normalize the host to either www.<domain> or <domain> based on FORCE_WWW,
     * optionally limited by PRIMARY_DOMAIN.
     *
     * @param  Request  $request  Incoming HTTP request object (only $request->uri is used here).
     */
    public static function handle(Request $request): void
    {
        // Resolve current host from the web server. If missing, there is nothing to normalize.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return;
        }

        // Split host into name and port (e.g. "example.com:8080").
        // compare names without ports, but preserve the port in the redirect target.
        [$hostName, $hostPort] = explode(':', $host, 2) + [1 => null];

        // Feature flags / environment configuration.
        // FORCE_WWW: "1" → canonical host is www.<domain>, "0" → canonical is apex <domain>.
        $forceWww = ($_ENV['FORCE_WWW'] ?? '0') === '1';

        // APP_ENV: production → 301 (permanent), anything else → 302 (temporary).
        $appEnv = $_ENV['APP_ENV'] ?? ($_SERVER['APP_ENV'] ?? 'production');
        $isProd = $appEnv === 'production';

        // Optionally restrict normalization to a single primary domain, e.g. "example.com".
        // This prevents rewriting completely different domains, like "static.other.com".
        $primary = $_ENV['PRIMARY_DOMAIN'] ?? null;
        if ($primary !== null && $primary !== '') {
            $primary = strtolower($primary);
            $hostLower = strtolower($hostName);

            // Consider only exact match or www-prefixed match as eligible for normalization.
            $isPrimaryExact = ($hostLower === $primary);
            $isPrimaryWithWww = ($hostLower === 'www.'.$primary);

            if (!$isPrimaryExact && !$isPrimaryWithWww) {
                // Host is neither example.com nor www.example.com → do not touch it.
                return;
            }
        }

        // At this point, hostName is either the primary domain (and/or its www variant),
        // or PRIMARY_DOMAIN is not set and we are free to normalize any host.

        $needsRedirect = false;
        $targetHost = $hostName;

        if ($forceWww) {
            // Canonical form is www.<domain>

            if ($primary && strcasecmp($hostName, $primary) === 0) {
                // Case 1: PRIMARY_DOMAIN is set and current host is apex (example.com).
                // Redirect to www.example.com.
                $targetHost = 'www.'.$hostName;
                $needsRedirect = true;
            } elseif (!$primary && strpos($hostName, 'www.') !== 0) {
                // Case 2: No PRIMARY_DOMAIN configured. Treat any non-www host as apex
                // and add "www." in front.
                $targetHost = 'www.'.$hostName;
                $needsRedirect = true;
            }
        } else {
            // Canonical form is apex (no "www.")

            if (stripos($hostName, 'www.') === 0) {
                // Current host starts with "www." → strip it off.
                $targetHost = substr($hostName, 4);
                $needsRedirect = true;
            }
        }

        // If the host is already canonical, no redirect is required.
        if (!$needsRedirect) {
            return;
        }

        // Reattach port if one was present on the original host (important for dev, e.g. :8080).
        if ($hostPort !== null && $hostPort !== '') {
            $targetHost .= ':'.$hostPort;
        }

        // Decide on the scheme:
        // - In production, always prefer https:// to align with HttpsRedirector and SEO.
        // - In non-production, mirror the current scheme to avoid surprises in local testing.
        $scheme = 'http://';
        if ($isProd || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
            $scheme = 'https://';
        }

        // Preserve the original path and query string as provided by $request->uri.
        $uri = $request->uri ?? '/';
        $target = $scheme.$targetHost.$uri;

        // Send redirect header and terminate.
        // 301 is cacheable and SEO-friendly in production; 302 keeps things flexible elsewhere.
        header('Location: '.$target, true, $isProd ? 301 : 302);
        exit;
    }
}
