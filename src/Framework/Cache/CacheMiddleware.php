<?php

declare(strict_types=1);

namespace Framework\Cache;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\AuthInterface;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Full-Page HTTP Response Caching Middleware
 *
 * cache entire HTML responses on the server and set browser cache headers.
 */
class CacheMiddleware implements MiddlewareInterface
{
    private CacheService $cache;

    private CacheKey $keyGenerator;

    private AuthInterface $auth;

    private array $ttlRules;

    private bool $debug;

    public function __construct(
        CacheService $cache,
        CacheKey $keyGenerator,
        AuthInterface $auth,
        array $ttlRules = [],
        bool $debug = false
    ) {
        $this->cache = $cache;
        $this->keyGenerator = $keyGenerator;
        $this->auth = $auth;
        $this->ttlRules = $ttlRules;
        $this->debug = $debug;
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        // skip caching for unsafe methods
        $method = $request->method;
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next->handle($request);
        }

        // Skip if client sends Cache-Control: no-cache
        if (stripos($request->header('Cache-Control') ?? '', 'no-cache') !== false) {
            return $next->handle($request);
        }

        $cacheKey = $this->keyGenerator->forRequest($request);
        $ttl = $this->resolveTtl($request->uri);

        // 1) TRY CACHE HIT
        if ($this->shouldSkipCache($request) === false && ($cached = $this->cache->get($cacheKey)) !== null) {
            $response = new Response();
            $response->setBody($cached);

            if ($this->debug) {
                $response->addHeader('X-Cache-Status', 'HIT');
                $response->addHeader('X-Cache-Key', substr($cacheKey, 0, 16).'...');
                $response->addHeader('X-Cache-TTL', (string) $ttl);
            }

            // set browser cache headers
            $this->setBrowserCacheHeaders($response, $ttl);

            return $response;
        }

        // 2) MISS: run full stack
        $response = $next->handle($request);

        // 3) STORE if cacheable (server-side cache)
        if ($this->isCacheable($response) && $ttl > 0) {
            $this->cache->set($cacheKey, $response->getBody(), $ttl);

            if ($this->debug) {
                $response->addHeader('X-Cache-Status', 'STORED');
            }
        } else {
            if ($this->debug) {
                $response->addHeader('X-Cache-Status', 'BYPASS');
            }
        }

        // 4) ALWAYS set browser cache headers (even if bypassed server cache)
        // set browser cache headers for public pages even if they can't be server-cached
        $this->setBrowserCacheHeaders($response, $ttl);

        if ($this->debug) {
            $response->addHeader('X-Cache-Key', substr($cacheKey, 0, 16).'...');
            $response->addHeader('X-Cache-TTL', (string) $ttl);
        }

        return $response;
    }

    /**
     * Set browser/CDN cache headers
     *
     * coordinate browser cache with server cache for optimal performance.
     * Browser cache TTL is typically shorter than server cache TTL.
     */
    private function setBrowserCacheHeaders(Response $response, int $ttl): void
    {
        // skip if Cache-Control already set (by pre-routing for static assets)
        if ($response->hasHeader('Cache-Control')) {
            return;
        }

        // skip for authenticated users
        if ($this->auth->check()) {
            $response->addHeader('Cache-Control', 'private, no-cache, must-revalidate');

            return;
        }

        // calculate browser TTL (20% of server TTL, max 5 minutes)
        $browserTtl = (int) min($ttl * 0.2, 300);

        if ($browserTtl > 0) {
            // set public cache with stale-while-revalidate
            $swr = (int) min($browserTtl, 60);
            $response->addHeader(
                'Cache-Control',
                "public, max-age={$browserTtl}, stale-while-revalidate={$swr}"
            );
        } else {
            $response->addHeader('Cache-Control', 'public, no-cache, must-revalidate');
        }
    }

    private function shouldSkipCache(Request $request): bool
    {
        if ($this->auth->check()) {
            return true;
        }

        if (stripos($request->uri, '/login') !== false ||
            stripos($request->uri, '/dashboard') !== false ||
            stripos($request->uri, '/admin') !== false) {
            return true;
        }

        return false;
    }

    private function isCacheable(Response $response): bool
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            if ($this->debug) {
                error_log("Cache BYPASS: Status code {$status}");
            }

            return false;
        }

        $cacheControl = $response->getHeader('Cache-Control') ?? '';
        if (stripos($cacheControl, 'private') !== false ||
            stripos($cacheControl, 'no-store') !== false) {
            if ($this->debug) {
                error_log("Cache BYPASS: Cache-Control is {$cacheControl}");
            }

            return false;
        }

        $body = $response->getBody();
        $hasPostForm = preg_match('/<form[^>]+method=["\']post["\']/i', $body);

        if ($hasPostForm) {
            if ($this->debug) {
                error_log('Cache BYPASS: Page has POST form');
            }

            return false;
        }

        return true;
    }

    private function resolveTtl(string $uri): int
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->ttlRules as $pattern => $ttl) {
            if (fnmatch($pattern, $path)) {
                return $ttl;
            }
        }

        return 600;
    }
}
