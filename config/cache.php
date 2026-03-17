<?php

declare(strict_types=1);

/**
 * Cache configuration.
 *
 * Defines file‑based caching behavior, including TTL rules, garbage
 * collection settings, and file‑limit enforcement to prevent disk growth.
 *
 * TTL values are expressed in seconds:
 * - 60 = 1 minute
 * - 300 = 5 minutes
 * - 600 = 10 minutes
 * - 1800 = 30 minutes
 * - 3600 = 1 hour
 * - 86400 = 24 hours
 */
return [

    /**
     * Enable or disable caching globally.
     */
    'enabled' => ($_ENV['CACHE_ENABLED'] ?? 'true') === 'true',

    /**
     * Cache storage path (must be writable).
     *
     * Cache files are stored outside the webroot for security.
     */
    'path' => ROOT_PATH.'/storage/cache',

    /**
     * Debug mode adds X‑Cache‑* headers to responses.
     *
     * Useful during development to inspect hit/miss behavior.
     * Should remain disabled in production to avoid exposing cache keys.
     */
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',

    /**
     * TTL rules by route pattern.
     *
     * Uses fnmatch() patterns (* = wildcard). More specific patterns
     * should appear before broader ones.
     *
     * Format: 'route_pattern' => ttl_in_seconds
     */
    'ttl_rules' => [
        // Authenticated areas (no caching)
        '/dashboard*' => 0,
        '/admin*' => 0,
        '/account*' => 0,
        '/login' => 0,
        '/register' => 0,
        '/geo' => 0,

        // Blog content
        '/blog/*/post/*' => 1800,   // Individual posts: 30 minutes
        '/blog/*' => 3600,          // Blog home: 1 hour
        '/blogs' => 3600,           // Blogs list: 1 hour

        // User profiles
        '/profile/*' => 300,        // 5 minutes

        // Public product pages
        '/products' => 600,         // 10 minutes
        '/product/*' => 1800,       // 30 minutes

        // Homepage
        '/' => 300,                 // 5 minutes
        '/home' => 300,             // 5 minutes

        // Static/SEO pages
        '/about' => 3600,
        '/contact' => 3600,
        '/privacy' => 86400,
        '/terms' => 86400,
    ],

    /**
     * Query parameter whitelist per route.
     *
     * Only whitelisted parameters are included in cache keys to prevent
     * fragmentation caused by tracking parameters (e.g., utm_*).
     *
     * Format: 'route' => ['param1', 'param2']
     */
    'query_whitelist' => [
        '/blogs' => ['page', 'q', 'category'],
        '/products' => ['page', 'sort', 'filter'],
        '/search' => ['q', 'lang', 'page'],
    ],

    /**
     * Default TTL for routes not matching any pattern (seconds).
     */
    'default_ttl' => 600, // 10 minutes

    // ==================== MAINTENANCE & CLEANUP ====================

    /**
     * Garbage collection probability (1–100).
     *
     * Cleanup is triggered probabilistically based on:
     * probability = gc_probability / gc_divisor
     *
     * Example:
     * - 1 / 100 → 1% chance per request (recommended)
     * - 2 / 100 → 2% chance (more aggressive)
     * - 0 → disabled (cleanup handled externally)
     */
    'gc_probability' => (int) ($_ENV['CACHE_GC_PROBABILITY'] ?? 1),

    /**
     * Garbage collection divisor.
     *
     * Used together with gc_probability to determine cleanup frequency.
     */
    'gc_divisor' => (int) ($_ENV['CACHE_GC_DIVISOR'] ?? 100),

    /**
     * Maximum number of cache files before LRU eviction.
     *
     * Enforces a hard limit to prevent unbounded disk usage. When the
     * limit is exceeded, the oldest 10% of files (by access time) are removed.
     *
     * Set to 0 for unlimited storage (not recommended).
     */
    'max_files' => (int) ($_ENV['CACHE_MAX_FILES'] ?? 5000),

    // Path for compiled template PHP files (separate from response cache)
    'compiled_views_path' => ROOT_PATH.'/storage/cache/views',

    // Maximum age (seconds) before compiled view files are pruned
    'compiled_views_max_age' => (int) ($_ENV['COMPILED_VIEWS_MAX_AGE'] ?? 604800), // 7 days

];
