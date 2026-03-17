<?php

declare(strict_types=1);

namespace Framework\Cache;

/**
 * Fragment cache with locale support and efficient disabling.
 *
 * We automatically append the current locale to cache keys
 * and respect the cache enabled/disabled state to avoid wasting resources.
 */
class FragmentCache
{
    private CacheService $cache;

    private ?string $currentLocale = null;

    private bool $enabled;

    private bool $debug;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
        // check if caching is enabled from config
        // This avoids having to check on every method call
        $config = require ROOT_PATH.'/config/cache.php';
        $this->enabled = $config['enabled'] ?? true;
        $this->debug = $config['debug'] ?? false;
    }

    /**
     * Set the current locale.
     *
     * We use this to make cache keys locale-aware.
     * The locale should be set by LocaleMiddleware.
     *
     * @param  string  $locale  Current locale (e.g., 'en', 'el')
     */
    public function setLocale(string $locale): void
    {
        $this->currentLocale = $locale;
    }

    /**
     * Get the current locale.
     *
     * We fall back to 'en' if no locale is set.
     *
     * @return string Current locale
     */
    private function getLocale(): string
    {
        return $this->currentLocale
            ?? $_SESSION['locale']
            ?? $_ENV['APP_LOCALE']
            ?? 'en';
    }

    /**
     * Build locale-aware cache key.
     *
     * We append the locale to the key unless explicitly disabled.
     *
     * @param  string  $key  Base cache key
     * @param  bool  $localized  Whether to append locale
     * @return string Localized cache key
     */
    private function buildCacheKey(string $key, bool $localized = true): string
    {
        if (!$localized) {
            return $key;
        }

        $locale = $this->getLocale();

        if (preg_match('/:('.preg_quote($locale).')$/', $key)) {
            return $key;
        }

        return $key.':'.$locale;
    }

    /**
     * Post-process HTML output.
     *
     * We apply transformations that should happen on EVERY render,
     * whether from cache or fresh generation:
     * 1. Active navigation state injection (changes per page)
     * 2. Icon rendering (if not already done)
     *
     * @param  string  $html  HTML to process
     * @param  string  $key  Cache key (for determining processing needed)
     * @return string Processed HTML
     */
    /*private function postProcess(string $html, string $key): string
    {
        dd('postProcess');
        // inject active navigation state for navigation fragments
        if (str_contains($key, 'navigation') || str_contains($key, 'sidebar')) {
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $html = \Framework\Helpers\NavigationActiveInjector::inject($html, $currentPath);
        }

        return $html;
    }*/

    /**
     * Cache a fragment by key with optional callback.
     *
     * We automatically make the cache locale-aware unless disabled.
     * If caching is disabled globally, we skip all cache operations
     * and just execute the callback with post-processing.
     *
     * @param  string  $key  Fragment cache key
     * @param  callable  $callback  Function that generates the fragment
     * @param  int  $ttl  Cache lifetime in seconds
     * @param  bool  $localized  Whether to make cache locale-aware
     * @return string Cached or freshly generated fragment
     */
    public function remember(string $key, callable $callback, int $ttl = 3600, bool $localized = true): string
    {
        // EARLY EXIT: If caching is disabled, skip ALL cache logic
        if (!$this->enabled) {
            try {
                $result = $callback();
            } catch (\Throwable $e) {
                error_log("Fragment generation ERROR (cache disabled) for {$key}: ".$e->getMessage());
                throw $e;
            }

            if (is_string($result)) {
                // only render icons
                $result = \Framework\Helpers\IconRenderer::render($result);

                return $result;
            }

            return '';
        }

        // CACHE ENABLED: Full cache logic
        $cacheKey = $this->buildCacheKey($key, $localized);

        // try to get from cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            if ($this->debug) {
                $sizeKb = strlen($cached) / 1024;
                error_log(sprintf(
                    'Fragment cache HIT: %s (%.2f KB)',
                    $cacheKey,
                    $sizeKb
                ));
            }

            return $cached;
        }

        // Cache miss - execute callback
        if ($this->debug) {
            error_log("Fragment cache MISS: {$cacheKey}");
        }
        try {
            $result = $callback();
        } catch (\Throwable $e) {
            error_log("Fragment cache ERROR for {$cacheKey}: ".$e->getMessage());
            throw $e;
        }

        if (is_string($result)) {
            // render icons server-side before caching
            $result = \Framework\Helpers\IconRenderer::render($result);

            $this->cache->set($cacheKey, $result, $ttl);
            if ($this->debug) {
                $sizeKb = strlen($result) / 1024;
                error_log(sprintf(
                    'Fragment cached: %s (%.2f KB, TTL: %ds)',
                    $cacheKey,
                    $sizeKb,
                    $ttl
                ));
            }

            return $result;
        }
        if ($this->debug) {
            error_log("Fragment cache SKIP (non-string result): {$cacheKey}");
        }

        return '';
    }

    /**
     * Cache data (not HTML) with optional callback.
     *
     * We use this for caching database queries, API responses, or computed values.
     *
     * @param  string  $key  Cache key
     * @param  callable  $callback  Function that generates the data
     * @param  int  $ttl  Cache lifetime in seconds
     * @param  bool  $localized  Whether to make cache locale-aware
     * @return mixed Cached or freshly generated data
     */
    public function rememberData(string $key, callable $callback, int $ttl = 3600, bool $localized = true): mixed
    {
        // EARLY EXIT: If caching is disabled, just execute callback
        if (!$this->enabled) {
            return $callback();
        }

        $cacheKey = $this->buildCacheKey($key, $localized);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $result = $callback();
        $this->cache->set($cacheKey, json_encode($result), $ttl);

        return $result;
    }

    /**
     * Render a cached fragment with view template.
     *
     * @param  string  $key  Cache key
     * @param  string  $view  View template path
     * @param  array  $data  Data to pass to view
     * @param  int  $ttl  Cache lifetime in seconds
     * @param  bool  $localized  Whether to make cache locale-aware
     * @return string Rendered HTML fragment
     */
    public function view(string $key, string $view, array $data = [], int $ttl = 3600, bool $localized = true): string
    {
        return $this->remember($key, function () use ($view, $data) {
            ob_start();
            extract($data, EXTR_SKIP);
            require view_path($view);

            return ob_get_clean();
        }, $ttl, $localized);
    }

    /**
     * Check if a fragment is cached.
     *
     * @param  string  $key  Cache key
     * @param  bool  $localized  Whether to check locale-aware key
     */
    public function has(string $key, bool $localized = true): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheKey = $this->buildCacheKey($key, $localized);

        return $this->cache->has($cacheKey);
    }

    /**
     * Delete a cached fragment.
     *
     * @param  string  $key  Cache key
     * @param  bool  $localized  Whether to delete locale-aware key
     */
    public function forget(string $key, bool $localized = true): bool
    {
        $cacheKey = $this->buildCacheKey($key, $localized);

        return $this->cache->delete($cacheKey);
    }

    /**
     * Delete all cached versions of a fragment (all locales).
     *
     * @param  string  $key  Base cache key (without locale)
     */
    public function forgetAllLocales(string $key): int
    {
        return $this->cache->deletePattern($key.':*');
    }

    /**
     * Delete fragments matching a pattern.
     *
     * @param  string  $pattern  Cache key pattern
     */
    public function forgetPattern(string $pattern): int
    {
        return $this->cache->deletePattern($pattern);
    }

    /**
     * Check if fragment caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
