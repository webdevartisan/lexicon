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
    /**
     * Headers restored onto a cache hit.
     *
     * Deliberately a short allowlist rather than everything the response carried.
     * Replaying Set-Cookie, Location or any auth-varying header would hand one
     * visitor's state to the next.
     */
    private const REPLAYABLE_HEADERS = ['Content-Type', 'Content-Language'];

    /**
     * Companion cache key holding those headers, so the body entry stays a plain
     * string and nothing else that reads the cache has to change.
     */
    private const HEADER_KEY_SUFFIX = '#headers';

    private CacheService $cache;

    private CacheKey $keyGenerator;

    private AuthInterface $auth;

    /** @var array<string, int> */
    private array $ttlRules;

    private bool $debug;

    /**
     * @param  array<string, int>  $ttlRules  Map of path pattern => TTL in seconds
     */
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

            // Without this the rebuilt response carries no content type and PHP's
            // default text/html wins, so a cached sitemap or robots.txt served
            // itself correctly once and then lied on every later request.
            foreach ($this->readStoredHeaders($cacheKey) as $name => $value) {
                $response->addHeader($name, $value);
            }

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
        //
        // The same skip test as the read path above. Without it a logged-in
        // visitor's render gets stored under a key that carries no auth
        // discriminator, and the next guest is served their navigation and
        // any other viewer-specific markup on the page.
        if ($this->shouldSkipCache($request) === false && $this->isCacheable($response) && $ttl > 0) {
            $this->cache->set($cacheKey, $response->getBody(), $ttl);
            $this->storeHeaders($cacheKey, $response, $ttl);

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
            stripos($request->uri, '/library') !== false ||
            stripos($request->uri, '/admin') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Headers stored alongside a cached body, filtered to the allowlist again on
     * the way out so an entry written by older code can never widen it.
     *
     * @return array<string, string>
     */
    private function readStoredHeaders(string $cacheKey): array
    {
        $raw = $this->cache->get($cacheKey.self::HEADER_KEY_SUFFIX);

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $headers = [];
        foreach (self::REPLAYABLE_HEADERS as $name) {
            if (isset($decoded[$name]) && is_string($decoded[$name])) {
                $headers[$name] = $decoded[$name];
            }
        }

        return $headers;
    }

    /**
     * Persist the headers a cached response cannot be rebuilt without.
     *
     * Stored beside the body rather than with it, so the entry format the rest
     * of the cache uses stays a plain string and an entry written before this
     * existed simply restores no headers.
     */
    private function storeHeaders(string $cacheKey, Response $response, int $ttl): void
    {
        $headers = [];

        foreach (self::REPLAYABLE_HEADERS as $name) {
            $value = $response->getHeader($name);

            if ($value !== null && $value !== '') {
                $headers[$name] = $value;
            }
        }

        if ($headers !== []) {
            // JSON rather than the array itself, because CacheService::set takes
            // a string and widening that contract would reach every other caller.
            $this->cache->set($cacheKey.self::HEADER_KEY_SUFFIX, (string) json_encode($headers), $ttl);
        }
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
