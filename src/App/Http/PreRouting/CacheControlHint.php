<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class CacheControlHint
 *
 * Purpose:
 * - Apply sensible Cache-Control headers early for endpoints with predictable caching needs
 *   (robots.txt, sitemap.xml, health checks, static assets).
 * - Keep dynamic HTML/API caching decisions in a dedicated response middleware or controllers,
 *   where ETag/Last-Modified and auth-sensitive pages can be handled safely.
 *
 * Behavior:
 * - Matches by request path and sets Cache-Control headers using header().
 * - Preserves query strings; does not redirect or mutate the path.
 * - Safe defaults:
 *   - /healthz, /ping           → very short TTL to avoid stale health signals.
 *   - /robots.txt               → modest TTL (e.g., 1 hour).
 *   - /sitemap.xml              → modest TTL + stale-while-revalidate for resilience.
 *   - /assets/* (versioned)     → long-lived, immutable.
 *   - /assets/* (non-versioned) → shorter TTL to allow updates.
 * - Leaves other paths untouched so downstream code can decide (pages, APIs, admin).
 *
 * Order in Kernel:
 * - After HTTPS/host normalization and before LocaleAwareStaticBypass / LocalePrefixIntake.
 * - Typically placed after MaintenanceModeGate / RateLimitPrecheck / UserAgentBlocklist
 *   and before any internal path rewrites to minimize header churn.
 *
 * Notes:
 * - For dynamic HTML pages, a good pattern is: "Cache-Control: no-cache" plus ETag/Last-Modified,
 *   so revalidation avoids full downloads when content is unchanged.
 * - For authenticated pages, Cache-Control should never be "public"; use "private, no-store"
 *   to prevent shared cache leaks.
 *
 * Usage:
 * - Call CacheControlHint::handle($request) from the pre-routing PipelineRunner.
 * - Extend the path rules below as the app evolves (feeds, JSON endpoints, etc.).
 */
final class CacheControlHint
{
    /**
     * Apply early Cache-Control hints for specific, well-known paths.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Only apply hints for idempotent methods that are traditionally cacheable.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        $uri = $request->uri ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Health checks: tiny TTL; OK to be public.
        // These responses are simple text and safe for shared caches.
        if ($path === '/healthz' || $path === '/ping') {
            header('Cache-Control: public, max-age=5'); // seconds

            return;
        }

        // Robots: moderate TTL.
        // Crawlers do not need second-by-second updates, but avoid very long caching
        // so changes are picked up within a reasonable window.
        if ($path === '/robots.txt') {
            header('Cache-Control: public, max-age=3600'); // 1 hour

            return;
        }

        // Sitemap: moderate TTL + resiliency.
        // stale-while-revalidate and stale-if-error help keep crawlers served
        // even while regenerating or during brief upstream errors.
        if ($path === '/sitemap.xml') {
            header('Cache-Control: public, max-age=3600, stale-while-revalidate=600, stale-if-error=600');

            return;
        }

        // ========== RSS/Atom Feeds ==========
        if ($path === '/feed' || $path === '/rss' || $path === '/atom' || str_ends_with($path, '.xml')) {
            // cache feeds for 15 minutes with stale-while-revalidate
            // Readers don't need second-by-second updates
            header('Cache-Control: public, max-age=900, stale-while-revalidate=300');

            return;
        }

        // ========== Public API Endpoints ==========
        if (str_starts_with($path, '/api/v1/public/')) {
            // cache public API responses briefly (5 min)
            // Use CDN s-maxage for edge caching
            header('Cache-Control: public, max-age=300, s-maxage=600');

            return;
        }

        // ========== PWA Manifest ==========
        if ($path === '/manifest.json' || $path === '/site.webmanifest') {
            // cache manifest for 1 day (changes rarely)
            header('Cache-Control: public, max-age=86400');

            return;
        }

        // ========== Favicon ==========
        if ($path === '/favicon.ico') {
            // cache favicon for 1 week (changes very rarely)
            header('Cache-Control: public, max-age=604800');

            return;
        }

        // Static assets under /assets.
        if (str_starts_with($path, '/assets/') || str_starts_with($path, '/themes/')) {
            $fileName = basename($path);

            // Clean up legacy anti-cache headers (e.g. from sessions) so asset policy wins.
            // Older PHP setups or session handlers may emit "Pragma: no-cache" or "Expires"
            // by default; removing them ensures modern Cache-Control takes precedence.
            header_remove('Pragma');
            header_remove('Expires');

            // Determine whether this asset URL is "versioned".
            // Strategy:
            // 1) Check for a hash in the filename (e.g. app.3a4f9d8c.css).
            // 2) If not present, check for a non-empty "v" query parameter (e.g. ?v=1762969198).

            $isVersioned = (bool) preg_match('#\.[0-9a-f]{8,}\.#i', $fileName);

            // Parse query string to look for ?v=...
            $query = parse_url($uri, PHP_URL_QUERY) ?: '';
            if (!$isVersioned && $query !== '') {
                $params = [];
                parse_str($query, $params); // decode "v=1762969198" → ['v' => '1762969198']

                if (isset($params['v']) && $params['v'] !== '') {
                    // Any non-empty "v" is treated as a cache-busting version token.
                    // When the file changes, can bump "v" and caches will see a new URL.
                    $isVersioned = true;
                }
            }

            if ($isVersioned) {
                // Long-lived, immutable cache for versioned assets.
                header('Cache-Control: public, max-age=31536000, immutable'); // 1 year
            } else {
                // Non-versioned assets: shorter TTL to let updates propagate.
                header('Cache-Control: public, max-age=600'); // 10 minutes
            }

            return;
        }

        // For HTML pages that will be server-cached
        // set modest browser cache to work WITH server cache
        // CacheMiddleware will override this if needed
        if (self::isPublicHtmlRoute($path)) {
            // hint short browser cache for public pages
            // This allows browsers to cache while server-side cache is primary
            header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

            return;
        }

        // Example: static JSON that updates infrequently (optional).
        // if (str_starts_with($path, '/data/')) {
        //     header('Cache-Control: public, max-age=300, stale-while-revalidate=120');
        //     return;
        // }

        // Everything else: no hint here; response middleware/controllers should decide:
        // - HTML public lists: short max-age + stale-while-revalidate OR no-cache + ETag.
        // - HTML with user data: private, no-store.
        // - APIs: public, s-maxage + stale directives for CDN resilience, if appropriate.
    }

    /**
     * Check if path is a public HTML route that can be browser cached
     *
     * coordinate with CacheMiddleware TTL rules.
     */
    private static function isPublicHtmlRoute(string $path): bool
    {
        // read cache config to check if path is cacheable
        $config = require ROOT_PATH.'/config/cache.php';
        $ttlRules = $config['ttl_rules'] ?? [];

        foreach ($ttlRules as $pattern => $ttl) {
            // check if pattern matches and TTL > 0
            if ($ttl > 0 && fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
