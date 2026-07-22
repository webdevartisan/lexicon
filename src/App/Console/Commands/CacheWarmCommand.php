<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Framework\Cache\CacheKey;
use Framework\Cache\CacheService;
use Framework\Cache\FragmentCache;
use Framework\Core\Dispatcher;
use Framework\Core\Request;

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

    private FragmentCache $fragmentCache;

    /** @var array<string, mixed> */
    private array $config;

    private bool $verbose = false;

    public function __construct(CacheService $cache, CacheKey $keyGenerator, FragmentCache $fragmentCache)
    {
        $this->cache = $cache;
        $this->keyGenerator = $keyGenerator;
        $this->fragmentCache = $fragmentCache;

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

            $iconStats = $this->warmIconFragments($locales);
            echo "Icons:        {$iconStats['warmed']} warmed, {$iconStats['already_cached']} already cached, {$iconStats['failed']} failed\n";
            echo "\n";

            $stats = [
                'total' => 0,
                'success' => 0,
                'cached' => 0,
                'failed' => 0,
                'skipped' => 0,
                'exempt_total' => 0,
                'exempt_success' => 0,
            ];

            // warm cache for each locale + route combination
            foreach ($locales as $locale) {
                echo "Warming locale: {$locale}\n";
                echo str_repeat('─', 60)."\n";

                foreach ($routes as $route => $routeInfo) {
                    $ttl = $routeInfo['ttl'];
                    $exempt = $routeInfo['exempt'];

                    if ($exempt) {
                        $stats['exempt_total']++;
                    } else {
                        $stats['total']++;
                    }

                    // still visit routes with TTL=0 to warm their fragments
                    // (even though full-page caching is disabled for them)
                    $result = $this->warmRoute($route, $locale, $ttl);
                    $tag = $exempt ? ' [exempt]' : '';

                    if ($result['success']) {
                        $exempt ? $stats['exempt_success']++ : $stats['success']++;

                        if ($ttl === 0) {
                            // Route has no full-page cache, but fragments may have been warmed
                            echo "  ○ {$route}{$tag} (no page cache, {$result['time']}ms)\n";
                        } elseif ($result['cached']) {
                            $stats['cached']++;
                            echo "  ✓ {$route}{$tag} ({$result['size']} bytes, {$result['time']}ms)\n";
                        } else {
                            echo "  ○ {$route}{$tag} (not cacheable, {$result['time']}ms)\n";
                        }
                    } else {
                        if (!$exempt) {
                            $stats['failed']++;
                        }
                        echo "  ✗ {$route}{$tag} (Error: {$result['error']})\n";
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
            echo "Exempt:       {$stats['exempt_success']}/{$stats['exempt_total']} attempted (excluded from rate; POST-only or need a real id/slug)\n";
            echo "Cached:       {$stats['cached']} entries created\n";
            echo "Failed:       {$stats['failed']} errors\n";
            echo "Skipped:      {$stats['skipped']} (TTL=0)\n";
            echo "\n";
            echo "Cache Stats:\n";
            echo "  Total files: {$cacheStats['total_files']}\n";
            echo '  Total size:  '.round($cacheStats['total_size_bytes'] / 1024 / 1024, 2)." MB\n";
            echo "  Live files:  {$cacheStats['live_files']}\n";
            echo "\n";

            // return success if at least 80% of rate-eligible routes were warmed
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
            $cacheKey = $ttl > 0 ? $this->keyGenerator->forRequest($request) : null;

            if ($cacheKey !== null && $this->cache->has($cacheKey)) {
                return [
                    'success' => true,
                    'cached' => false,
                    'size' => 0,
                    'time' => round((microtime(true) - $startTime) * 1000, 2),
                    'error' => 'Already cached',
                ];
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

            // Check the cache store directly rather than the X-Cache-Status header,
            // since that header is only added when config('cache.debug') is on.
            $wasCached = $cacheKey !== null && $this->cache->has($cacheKey);

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
     * Pre-warm icon fragments so real page loads never pay the Node.js
     * subprocess cost of rendering an uncached data-lucide icon.
     *
     * Fragment cache TTLs for icons are long-lived (typically 1 year) but are
     * scoped to whichever page first renders them. Route warming only visits
     * a handful of pages, so icons unique to unwarmed pages (e.g. deep
     * dashboard forms) stay cold until a real user hits them. This scans every
     * template for {% cache 'key' %}<i data-lucide="...">{% endcache %} blocks
     * and forces them all through the cache up front, regardless of which
     * route uses them.
     *
     * @param  array<string>  $locales
     * @return array{warmed: int, already_cached: int, failed: int}
     */
    private function warmIconFragments(array $locales): array
    {
        $fragments = $this->discoverIconFragments();

        $stats = ['warmed' => 0, 'already_cached' => 0, 'failed' => 0];

        foreach ($locales as $locale) {
            $this->fragmentCache->setLocale($locale);

            foreach ($fragments as $key => $fragment) {
                if ($this->fragmentCache->has($key)) {
                    $stats['already_cached']++;

                    continue;
                }

                try {
                    $this->fragmentCache->remember($key, fn () => $fragment['markup'], $fragment['ttl']);
                    $stats['warmed']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    if ($this->verbose) {
                        echo "  ✗ icon fragment '{$key}' (Error: {$e->getMessage()})\n";
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * Discover icon fragments to pre-warm across every view template.
     *
     * Combines two sources:
     * 1. Static {% cache 'literal-key' %} blocks holding a data-lucide icon.
     * 2. Icon components, whose cache key is built per call site from an $icon
     *    parameter ("lucide:btn:" . $icon) and so has no literal to match. These
     *    are found by convention, not configuration - see discoverIconComponents().
     *
     * @return array<string, array{markup: string, ttl: int}>
     */
    private function discoverIconFragments(): array
    {
        $componentBlocks = $this->discoverIconComponents();
        $componentNames = array_keys($componentBlocks);

        // Single pass over all templates: collect literal-key icon fragments and
        // the icon values each icon component is called with.
        $fragments = [];
        $componentIcons = array_fill_keys($componentNames, []);
        $cmpPattern = $this->buildComponentIconPattern($componentNames);

        foreach ($this->templateFiles() as $contents) {
            foreach ($this->matchLiteralIconBlocks($contents) as $key => $fragment) {
                $fragments[$key] = $fragment;
            }

            if ($cmpPattern !== null && preg_match_all($cmpPattern, $contents, $cmpMatches, PREG_SET_ORDER)) {
                foreach ($cmpMatches as $cmpMatch) {
                    $componentIcons[$cmpMatch[1]][$cmpMatch[2]] = true;
                }
            }
        }

        // Render each icon component's own cache block for every icon value used
        // at its call sites, deriving both key and markup from the component
        // source so warmed HTML always matches the template.
        foreach ($componentBlocks as $name => $block) {
            foreach (array_keys($componentIcons[$name]) as $icon) {
                $key = $this->evalCacheKeyExpression($block['keyExpr'], ['icon' => $icon]);
                $markup = $this->renderTemplateSnippet($block['markup'], ['icon' => $icon]);

                if ($key !== null && $markup !== null) {
                    $fragments[$key] = ['markup' => $markup, 'ttl' => $block['ttl']];
                }
            }
        }

        return $fragments;
    }

    /**
     * Find components that render an icon under an $icon-parameterised cache key.
     *
     * Discovered by convention rather than declared: the framework resolves
     * {% cmp="x" %} to views/components/x.lex.php (TemplateComponentsTrait::
     * renderComponent), so any component there whose icon cache block keys off
     * $icon is warmable, and its name is simply its filename. A new icon
     * component is picked up with no config change.
     *
     * @return array<string, array{keyExpr: string, markup: string, ttl: int}>
     */
    private function discoverIconComponents(): array
    {
        $components = [];

        foreach (glob(ROOT_PATH.'/views/components/*.lex.php') ?: [] as $path) {
            $contents = file_get_contents($path);
            if ($contents === false || !str_contains($contents, 'data-lucide')) {
                continue;
            }

            $block = $this->extractIconCacheBlock($contents, '$icon');
            if ($block !== null) {
                $components[basename($path, '.lex.php')] = $block;
            }
        }

        return $components;
    }

    /**
     * Yield the contents of every .lex.php template, app views first then themes.
     *
     * @return \Generator<string>
     */
    private function templateFiles(): \Generator
    {
        $viewDirs = [ROOT_PATH.'/views'];
        foreach (glob(ROOT_PATH.'/themes/*/views', GLOB_ONLYDIR) ?: [] as $themeViewDir) {
            $viewDirs[] = $themeViewDir;
        }

        foreach ($viewDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (!str_ends_with($file->getFilename(), '.lex.php')) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if ($contents !== false) {
                    yield $contents;
                }
            }
        }
    }

    /**
     * Extract literal-key icon cache blocks that are safe to render statelessly.
     *
     * Only blocks with a single-quoted literal key, a data-lucide icon, and no
     * PHP/template interpolation qualify - anything request-dependent is skipped.
     *
     * @return array<string, array{markup: string, ttl: int}>
     */
    private function matchLiteralIconBlocks(string $contents): array
    {
        if (!str_contains($contents, 'data-lucide')) {
            return [];
        }

        $pattern = '#\{%\s*cache\s+\'([a-zA-Z0-9_:\-]+)\'(?:\s+ttl=(\d+))?\s*%\}(.*?)\{%\s*endcache\s*%\}#s';
        if (!preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $blocks = [];
        foreach ($matches as $match) {
            $markup = $match[3];

            if (!str_contains($markup, 'data-lucide')
                || str_contains($markup, '<?')
                || str_contains($markup, '{{')
                || str_contains($markup, '{%')
            ) {
                continue;
            }

            $blocks[$match[1]] = ['markup' => $markup, 'ttl' => $match[2] !== '' ? (int) $match[2] : 3600];
        }

        return $blocks;
    }

    /**
     * Build a regex matching call sites of the given icon components, capturing
     * the component name and the icon= value (e.g. {% cmp="btn" ... icon="x" %}).
     *
     * @param  array<string>  $componentNames
     */
    private function buildComponentIconPattern(array $componentNames): ?string
    {
        if ($componentNames === []) {
            return null;
        }

        $alternation = implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '#'),
            $componentNames
        ));

        return '#\{%\s*cmp="('.$alternation.')"(?:(?!%\}).)*?\bicon="([a-zA-Z0-9_-]+)"#s';
    }

    /**
     * Read the first icon {% cache %} block whose key expression matches.
     *
     * Returns the block's key expression, markup, and TTL taken straight from the
     * template source. keyExprContains selects the intended block when a file
     * holds several icon blocks (e.g. '$icon' for a component's parameterised key).
     *
     * @param  string  $contents  Raw template source.
     * @return array{keyExpr: string, markup: string, ttl: int}|null
     */
    private function extractIconCacheBlock(string $contents, string $keyExprContains): ?array
    {
        if (!preg_match_all('#\{%\s*cache\s+(.*?)\s*%\}(.*?)\{%\s*endcache\s*%\}#s', $contents, $matches, PREG_SET_ORDER)) {
            return null;
        }

        foreach ($matches as $match) {
            $markup = $match[2];
            if (!str_contains($markup, 'data-lucide')) {
                continue;
            }

            // Strip cache options (ttl=, localized=) to isolate the key expression.
            $keyExpr = trim((string) preg_replace('/\s*\b(?:ttl=\d+|localized=(?:true|false))\b/', '', $match[1]));
            if ($keyExprContains !== '' && !str_contains($keyExpr, $keyExprContains)) {
                continue;
            }

            $ttl = preg_match('/\bttl=(\d+)\b/', $match[1], $ttlMatch) ? (int) $ttlMatch[1] : 3600;

            return ['keyExpr' => $keyExpr, 'markup' => $markup, 'ttl' => $ttl];
        }

        return null;
    }

    /**
     * Evaluate a cache-key expression captured from a template with vars bound.
     *
     * Same trust model as renderTemplateSnippet(): the expression is a substring
     * extracted from our own template source, run in a CLI-only command with no
     * untrusted input. Returns null on error so the caller can skip the fragment.
     *
     * @param  array<string, mixed>  $vars
     */
    private function evalCacheKeyExpression(string $keyExpr, array $vars): ?string
    {
        try {
            extract($vars, EXTR_SKIP);
            $result = eval('return '.$keyExpr.';');

            return is_string($result) ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Render a raw template snippet (HTML mixed with short PHP echo tags) captured
     * from a source .lex.php file, with the given variables bound.
     *
     * Used only for the handful of icon fragments whose cache key depends on a
     * call-site value we can't match as a static literal - this executes the
     * actual captured markup instead of us maintaining a hand-written copy of it.
     * Returns null on any error so the caller can skip that fragment rather than
     * risk caching something wrong.
     *
     * @param  array<string, mixed>  $vars
     */
    private function renderTemplateSnippet(string $snippet, array $vars): ?string
    {
        try {
            extract($vars, EXTR_SKIP);
            ob_start();
            // $snippet is never external/user input - it's a substring this same
            // command just extracted, via regex, from our own .lex.php files on
            // disk (discoverIconFragments()). This command is CLI-only (never
            // reachable from an HTTP request) and only warms the deploy's own
            // cache, so there is no untrusted-data path into this eval().
            eval('?>'.$snippet);

            return ob_get_clean();
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return null;
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
        $warmAsUserId = env('CACHE_WARM_AS_USER_ID', 1);
        if ($warmAsUserId !== '') {
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
            'HTTP_HOST' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?? 'localhost',
            'HTTP_USER_AGENT' => 'Cache-Warmer/1.0 (CLI)',
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_NAME' => parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?? 'localhost',
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
     * which routes should be warmed. Expands wildcard patterns using
     * mock data to create concrete routes for warming.
     *
     * @param  string|null  $routesOption  Command-line routes option
     * @return array<string, array{ttl: int, exempt: bool}> Map of route => TTL and rate-exempt flag
     */
    private function getRoutesToWarm(?string $routesOption): array
    {
        // allow overriding routes via command line
        if ($routesOption !== null) {
            $routes = [];
            foreach (explode(',', $routesOption) as $route) {
                $route = trim($route);
                // look up TTL from config or use default
                $routes[$route] = ['ttl' => $this->resolveTtl($route), 'exempt' => false];
            }

            return $routes;
        }

        // use all routes from cache config
        /** @var array<string, int> $configRoutes */
        $configRoutes = $this->config['ttl_rules'] ?? [];

        // load mock data for wildcard expansion
        /** @var array<string, list<string>> $mocks */
        $mocks = $this->config['warmup_mocks'] ?? [
            'blog_slugs' => ['blog-1'],
            'categories' => ['general'],
            'tags' => ['featured'],
        ];

        $rateExemptPatterns = $this->config['warmup_rate_exempt_patterns'] ?? [];

        $routesToWarm = [];

        // process each route from config
        foreach ($configRoutes as $pattern => $ttl) {
            // If it's an exact route (no wildcards), keep it as-is
            if (!str_contains($pattern, '*') && !str_contains($pattern, '?')) {
                $routesToWarm[$pattern] = ['ttl' => $ttl, 'exempt' => in_array($pattern, $rateExemptPatterns, true)];

                continue;
            }

            if (!str_contains($pattern, '*')) {
                continue;
            }

            // Expand patterns with wildcards (including nested like /blog/*/archive)
            $expanded = $this->expandWildcardPattern($pattern, $mocks);
            if ($expanded !== []) {
                $exempt = in_array($pattern, $rateExemptPatterns, true);
                foreach ($expanded as $route) {
                    if (!isset($routesToWarm[$route])) {
                        $routesToWarm[$route] = ['ttl' => $ttl, 'exempt' => $exempt];
                    }
                }

                continue;
            }

            if (!str_ends_with($pattern, '*')) {
                continue;
            }

            $baseRoute = rtrim($pattern, '*');
            if ($baseRoute === '' || isset($routesToWarm[$baseRoute])) {
                continue;
            }

            $routesToWarm[$baseRoute] = [
                'ttl' => $ttl,
                'exempt' => in_array($pattern, $rateExemptPatterns, true),
            ];
        }

        return $routesToWarm;
    }

    /**
     * Expand a wildcard route pattern into concrete routes using mock data.
     *
     * supports single-wildcard patterns (blog, category, tag routes) and
     * nested two-wildcard patterns, filling slots from the warmup mocks.
     *
     * @param  string  $pattern  Route pattern containing wildcards
     * @param  array<string, list<string>>  $mocks  Mock values keyed by type (blog_slugs, categories, tags)
     * @return list<string> Concrete routes ready for warming
     */
    private function expandWildcardPattern(string $pattern, array $mocks): array
    {
        $routes = [];
        $wildcardCount = substr_count($pattern, '*');

        if ($wildcardCount === 1) {
            $values = [];
            // Matches both '/blog/*' and '/blog/*/archive' etc.
            if (str_starts_with($pattern, '/blog/*')) {
                $values = $mocks['blog_slugs'] ?? [];
            } elseif (str_contains($pattern, '/category/')) {
                $values = $mocks['categories'] ?? [];
            } elseif (str_contains($pattern, '/tag/')) {
                $values = $mocks['tags'] ?? [];
            }
            foreach ($values as $value) {
                $routes[] = str_replace('*', $value, $pattern);
            }
        } elseif ($wildcardCount === 2) {
            $primaryBlog = $mocks['blog_slugs'][0] ?? null;
            if ($primaryBlog === null) {
                return $routes;
            }

            if (str_contains($pattern, 'category')) {
                $categoryValues = $mocks['categories'] ?? [];
                foreach ($categoryValues as $category) {
                    $pos = strpos($pattern, '*');
                    $route = substr_replace($pattern, $primaryBlog, $pos, 1);
                    $pos2 = strpos($route, '*');
                    $route = substr_replace($route, $category, $pos2, 1);
                    $routes[] = $route;
                }
            } elseif (str_contains($pattern, 'tag')) {
                $tagValues = $mocks['tags'] ?? [];
                foreach ($tagValues as $tag) {
                    $pos = strpos($pattern, '*');
                    $route = substr_replace($pattern, $primaryBlog, $pos, 1);
                    $pos2 = strpos($route, '*');
                    $route = substr_replace($route, $tag, $pos2, 1);
                    $routes[] = $route;
                }
            }
        }

        return $routes;
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
