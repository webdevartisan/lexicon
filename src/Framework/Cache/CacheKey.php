<?php

declare(strict_types=1);

namespace Framework\Cache;

use Framework\Core\Request;

/**
 * Deterministic cache key generation.
 *
 * generate unique keys based on:
 * - Locale (from session)
 * - Request path
 * - Canonical query parameters (only whitelisted ones)
 * - Method (GET/HEAD)
 *
 * This ensures /en/blogs?page=2 has a different cache than /el/blogs?page=2.
 */
class CacheKey
{
    private array $queryWhitelist;

    /**
     * @param  array  $queryWhitelist  Map of path => allowed query params
     *                                 e.g., ['/blogs' => ['page', 'q']]
     */
    public function __construct(array $queryWhitelist = [])
    {
        $this->queryWhitelist = $queryWhitelist;
    }

    /**
     * Generate cache key for a request.
     *
     * Format: "{locale}:{method}:{path}?{canonical_query}"
     */
    public function forRequest(Request $request): string
    {
        $locale = $_SESSION['locale'] ?? 'en';
        $method = $request->method;
        $path = $this->normalizePath($request->uri);
        $query = $this->canonicalQuery($path, $request->get);

        $key = "{$locale}:{$method}:{$path}";
        if ($query !== '') {
            $key .= "?{$query}";
        }

        return $key;
    }

    /**
     * Generate cache key for manual operations.
     *
     * Example: cache()->delete(CacheKey::for('/blogs', ['page' => 2]))
     */
    public static function for(string $path, array $query = [], ?string $locale = null): string
    {
        $locale = $locale ?? $_SESSION['locale'] ?? 'en';
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        ksort($query);
        $queryString = http_build_query($query);

        $key = "{$locale}:GET:{$path}";
        if ($queryString !== '') {
            $key .= "?{$queryString}";
        }

        return $key;
    }

    // ==================== PRIVATE HELPERS ====================

    private function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // remove trailing slashes except for root.
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function canonicalQuery(string $path, array $getParams): string
    {
        // Get allowed params for this path.
        $allowed = $this->queryWhitelist[$path] ?? null;

        // If no whitelist defined, allow all params (risky but flexible).
        if ($allowed === null) {
            ksort($getParams);

            return http_build_query($getParams);
        }

        // Filter to only whitelisted params.
        $filtered = array_intersect_key($getParams, array_flip($allowed));
        ksort($filtered);

        return http_build_query($filtered);
    }
}
