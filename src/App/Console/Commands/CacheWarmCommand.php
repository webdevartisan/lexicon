<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Framework\Cache\CacheKey;
use Framework\Cache\CacheService;
use Framework\Core\Dispatcher;
use Framework\Core\Request;
use Framework\Core\Response;

/**
 * Cache warming command.
 *
 * pre-generate cache entries for high-traffic routes to eliminate
 * cold starts after deployment. This command simulates real requests
 * to populate both full-page and fragment caches.
 *
 * Usage:
 *   php cli cache:warm                    - Warm all configured routes
 *   php cli cache:warm --routes=/,/blogs  - Warm specific routes
 *   php cli cache:warm --locale=el        - Warm specific locale only
 *   php cli cache:warm --verbose          - Show detailed progress
 */
class CacheWarmCommand
{
    private CacheService $cache;

    private CacheKey $keyGenerator;

    private array $config;

    private bool $verbose = false;

    public function __construct(CacheService $cache, CacheKey $keyGenerator)
    {
        $this->cache = $cache;
        $this->keyGenerator = $keyGenerator;

        // load cache configuration to know which routes to warm
        $this->config = require ROOT_PATH.'/config/cache.php';
    }

    /**
     * Execute the cache warming operation.
     *
     * iterate through configured routes and locales, making internal
     * requests to populate the cache. This prevents users from experiencing
     * slow first-page-loads after deployment.
     *
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(): int
    {
        try {
            $startTime = microtime(true);

            // parse command-line options
            $options = $this->parseOptions();
            $this->verbose = $options['verbose'];

            echo "Starting cache warming...\n";

            if (!$this->config['enabled']) {
                echo "⚠ Cache is disabled in config - warming skipped\n";

                return 0;
            }

            // get routes to warm (from config or command line)
            $routes = $this->getRoutesToWarm($options['routes']);
            $locales = $this->getLocalesToWarm($options['locale']);

            echo 'Routes to warm: '.count($routes)."\n";
            echo 'Locales: '.implode(', ', $locales)."\n";
            echo "\n";

            $stats = [
                'total' => 0,
                'success' => 0,
                'cached' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];

            // warm cache for each locale + route combination
            foreach ($locales as $locale) {
                echo "Warming locale: {$locale}\n";
                echo str_repeat('─', 60)."\n";

                foreach ($routes as $route => $ttl) {
                    $stats['total']++;

                    // still visit routes with TTL=0 to warm their fragments
                    // (even though full-page caching is disabled for them)
                    $result = $this->warmRoute($route, $locale, $ttl);

                    if ($result['success']) {
                        $stats['success']++;

                        if ($ttl === 0) {
                            // Route has no full-page cache, but fragments may have been warmed
                            echo "  ○ {$route} (no page cache, {$result['time']}ms)\n";
                        } elseif ($result['cached']) {
                            $stats['cached']++;
                            echo "  ✓ {$route} ({$result['size']} bytes, {$result['time']}ms)\n";
                        } else {
                            echo "  ○ {$route} (not cacheable, {$result['time']}ms)\n";
                        }
                    } else {
                        $stats['failed']++;
                        echo "  ✗ {$route} (Error: {$result['error']})\n";
                    }
                }

                echo "\n";
            }

            // get final cache statistics
            $cacheStats = $this->cache->stats();
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            echo "╔════════════════════════════════════════════════════════════╗\n";
            echo "║                   CACHE WARMING COMPLETE                   ║\n";
            echo "╚════════════════════════════════════════════════════════════╝\n";
            echo "\n";
            echo "Duration:     {$duration}ms\n";
            echo "Routes:       {$stats['success']}/{$stats['total']} warmed successfully\n";
            echo "Cached:       {$stats['cached']} entries created\n";
            echo "Failed:       {$stats['failed']} errors\n";
            echo "Skipped:      {$stats['skipped']} (TTL=0)\n";
            echo "\n";
            echo "Cache Stats:\n";
            echo "  Total files: {$cacheStats['total_files']}\n";
            echo '  Total size:  '.round($cacheStats['total_size_bytes'] / 1024 / 1024, 2)." MB\n";
            echo "  Live files:  {$cacheStats['live_files']}\n";
            echo "\n";

            // return success if at least 80% of routes were warmed
            $successRate = $stats['total'] > 0 ? ($stats['success'] / $stats['total']) : 1;
            if ($successRate < 0.8) {
                echo '⚠ WARNING: Only '.round($successRate * 100)."% success rate\n";

                return 1;
            }

            return 0; // Success

        } catch (\Exception $e) {
            echo "✗ Error during cache warming: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1; // Failure
        }
    }

    /**
     * Warm cache for a specific route and locale.
     *
     * simulate a real HTTP request to trigger the full application stack,
     * including middleware, controllers, and view rendering. This ensures
     * both full-page cache and fragment caches are populated.
     *
     * Even for routes with TTL=0 (no full-page caching), still visit them
     * to warm any fragment caches that exist in their templates.
     *
     * @param  string  $route  Route path (e.g., '/', '/blogs')
     * @param  string  $locale  Locale code (e.g., 'en', 'el')
     * @param  int  $ttl  TTL for this route (0 = no full-page cache)
     * @return array{success: bool, cached: bool, size: int, time: float, error: string|null}
     */
    private function warmRoute(string $route, string $locale, int $ttl): array
    {
        $startTime = microtime(true);

        try {
            // set up the environment for this request
            $this->setupEnvironment($locale);

            // create a fake request for this route
            $request = $this->createInternalRequest($route, $locale);

            // skip cache checking for TTL=0 routes (they won't be full-page cached)
            if ($ttl > 0) {
                // check if already cached (avoid unnecessary regeneration)
                $cacheKey = $this->keyGenerator->forRequest($request);
                if ($this->cache->has($cacheKey)) {
                    if ($this->verbose) {
                        return [
                            'success' => true,
                            'cached' => false,
                            'size' => 0,
                            'time' => round((microtime(true) - $startTime) * 1000, 2),
                            'error' => 'Already cached',
                        ];
                    }
                }
            }

            // load the full application stack
            $router = require ROOT_PATH.'/config/routes.php';
            $container = \Framework\Core\App::container();
            $middleware = require ROOT_PATH.'/config/middleware.php';
            $routeContext = $container->get(\Framework\View\RouteContext::class);

            // build the dispatcher (same as public/index.php)
            $dispatcher = new Dispatcher($router, $container, $routeContext, $middleware);

            // execute the request through the full middleware stack
            // This will trigger fragment caching even if full-page caching is disabled
            $response = $dispatcher->handle($request);

            $size = strlen($response->getBody());
            $time = round((microtime(true) - $startTime) * 1000, 2);

            // check if the response was cached (only relevant for TTL > 0)
            $wasCached = $ttl > 0
                && $response->hasHeader('X-Cache-Status')
                && $response->getHeader('X-Cache-Status') === 'STORED';

            return [
                'success' => true,
                'cached' => $wasCached,
                'size' => $size,
                'time' => $time,
                'error' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'cached' => false,
                'size' => 0,
                'time' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Set up the PHP environment for cache warming.
     *
     * mock session data and set up global state to simulate a real
     * browser request without actually starting PHP sessions (which
     * don't work properly in CLI mode after output has been sent).
     *
     * @param  string  $locale  Locale code
     */
    private function setupEnvironment(string $locale): void
    {
        // mock the session data instead of starting a real session
        // This prevents "headers already sent" errors in CLI mode
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $_SESSION['locale'] = $locale;

        // mock authentication for authenticated routes during warming
        // This allows us to warm dashboard/admin fragments without requiring real login
        $warmAsUserId = $_ENV['CACHE_WARM_AS_USER_ID'] ?? 1;
        if ($warmAsUserId !== null && $warmAsUserId !== '') {
            $_SESSION['user_id'] = (int) $warmAsUserId;

            if ($this->verbose) {
                echo "  [Auth: Mocking user ID {$warmAsUserId}]\n";
            }
        }
        // set up environment variables for cache warming context
        $_ENV['CACHE_WARMING'] = 'true';

        // disable session auto-start if it was enabled
        if (function_exists('ini_set')) {
            @ini_set('session.auto_start', '0');
        }
    }

    /**
     * Create an internal request for cache warming.
     *
     * simulate a real browser request with proper headers and session
     * to ensure the cache middleware behaves identically to production.
     *
     * @param  string  $route  Route path
     * @param  string  $locale  Locale code
     * @return Request Fake request object
     */
    private function createInternalRequest(string $route, string $locale): Request
    {
        // clear and rebuild $_SERVER for this request
        // This prevents contamination between requests
        $originalServer = $_SERVER;

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $route,
            'HTTP_HOST' => parse_url($_ENV['APP_URL'] ?? 'http://localhost', PHP_URL_HOST) ?? 'localhost',
            'HTTP_USER_AGENT' => 'Cache-Warmer/1.0 (CLI)',
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_NAME' => parse_url($_ENV['APP_URL'] ?? 'http://localhost', PHP_URL_HOST) ?? 'localhost',
            'SERVER_PORT' => '80',
            'SCRIPT_NAME' => '/index.php',
            'PHP_SELF' => '/index.php',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
        ];

        // clear query params and post data
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];

        return Request::createFromGlobals();
    }

    /**
     * Get routes to warm from config or command-line options.
     *
     * use the TTL rules from cache.php as the source of truth for
     * which routes should be warmed. For wildcard patterns (e.g., /dashboard*),
     * extract the base route (e.g., /dashboard) to warm.
     *
     * @param  string|null  $routesOption  Command-line routes option
     * @return array<string, int> Map of route => TTL
     */
    private function getRoutesToWarm(?string $routesOption): array
    {
        // allow overriding routes via command line
        if ($routesOption !== null) {
            $routes = [];
            foreach (explode(',', $routesOption) as $route) {
                $route = trim($route);
                // look up TTL from config or use default
                $routes[$route] = $this->resolveTtl($route);
            }

            return $routes;
        }

        // use all routes from cache config
        $configRoutes = $this->config['ttl_rules'] ?? [];

        $routesToWarm = [];

        // process each route from config
        foreach ($configRoutes as $pattern => $ttl) {
            // If it's an exact route (no wildcards), keep it as-is
            if (!str_contains($pattern, '*') && !str_contains($pattern, '?')) {
                $routesToWarm[$pattern] = $ttl;
            }
            // If it's a wildcard pattern, extract the base route
            elseif (str_ends_with($pattern, '*')) {
                // convert /dashboard* to /dashboard
                $baseRoute = rtrim($pattern, '*');

                // skip if base route is empty or already exists
                if ($baseRoute === '' || isset($routesToWarm[$baseRoute])) {
                    continue;
                }

                // add the base route with the same TTL as the pattern
                $routesToWarm[$baseRoute] = $ttl;
            }
            // For other patterns (? wildcards, etc.), skip them
            // as they're too ambiguous to expand automatically
        }

        return $routesToWarm;
    }

    /**
     * Get locales to warm from config or command-line options.
     *
     * default to warming all available locales to ensure users
     * don't experience cold starts regardless of their language preference.
     *
     * @param  string|null  $localeOption  Command-line locale option
     * @return array<string> List of locale codes
     */
    private function getLocalesToWarm(?string $localeOption): array
    {
        // allow warming a single locale via command line
        if ($localeOption !== null) {
            return [$localeOption];
        }

        // get all available locales from the locales directory
        $localesPath = ROOT_PATH.'/locales';
        if (!is_dir($localesPath)) {
            return ['en']; // Fallback to English only
        }

        $locales = [];
        foreach (glob($localesPath.'/*.php') as $file) {
            $locale = basename($file, '.php');
            $locales[] = $locale;
        }

        return $locales ?: ['en'];
    }

    /**
     * Resolve TTL for a route using cache config rules.
     *
     * match the route against TTL patterns in cache.php.
     *
     * @param  string  $route  Route path
     * @return int TTL in seconds
     */
    private function resolveTtl(string $route): int
    {
        $rules = $this->config['ttl_rules'] ?? [];

        // try exact match first
        if (isset($rules[$route])) {
            return $rules[$route];
        }

        // try pattern matching
        foreach ($rules as $pattern => $ttl) {
            if (fnmatch($pattern, $route)) {
                return $ttl;
            }
        }

        // fall back to default TTL
        return $this->config['default_ttl'] ?? 600;
    }

    /**
     * Parse command-line options.
     *
     * support:
     *   --routes=/,/blogs,/products  - Comma-separated list of routes
     *   --locale=el                  - Single locale to warm
     *   --verbose                    - Detailed output
     *
     * @return array{routes: string|null, locale: string|null, verbose: bool}
     */
    private function parseOptions(): array
    {
        global $argv;

        $options = [
            'routes' => null,
            'locale' => null,
            'verbose' => false,
        ];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--routes=')) {
                $options['routes'] = substr($arg, 9);
            } elseif (str_starts_with($arg, '--locale=')) {
                $options['locale'] = substr($arg, 9);
            } elseif ($arg === '--verbose' || $arg === '-v') {
                $options['verbose'] = true;
            }
        }

        return $options;
    }
}
