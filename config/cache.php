<?php

declare(strict_types=1);

/**
 * Cache configuration.
 *
 * Defines file-based response caching behavior, including TTL rules,
 * query-key normalization, garbage collection, and cache limits.
 *
 * TTL values are expressed in seconds:
 * - 60 = 1 minute
 * - 300 = 5 minutes
 * - 600 = 10 minutes
 * - 1800 = 30 minutes
 * - 3600 = 1 hour
 * - 86400 = 24 hours
 *
 * Route rules use fnmatch() wildcards:
 * - *  matches zero or more characters
 * - ?  matches a single character
 *
 * Keep more specific rules before broader ones.
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
     * Debug mode adds X-Cache-* headers to responses.
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
        /*
         |--------------------------------------------------------------------------
         | Never cache: authenticated, private, tokenized, state-changing, utility
         |--------------------------------------------------------------------------
         */
        '/dashboard*' => 0,
        '/admin*' => 0,
        '/account*' => 0,

        '/login' => 0,
        '/login/submit' => 0,
        '/logout' => 0,
        '/register' => 0,
        '/register/submit' => 0,

        '/password/forgot' => 0,
        '/password/reset*' => 0,

        '/csrf-token' => 0,
        '/csp-report' => 0,
        '/geo' => 0,
        '/consent' => 0,
        '/consent/withdraw' => 0,

        '/comments/create' => 0,
        '/comments/*/delete' => 0,
        '/comments/*/vote' => 0,
        '/comments/*/report' => 0,
        '/comments/*/pin' => 0,

        '/blog/*/subscribe' => 0,
        '/subscriptions/unsubscribe/*' => 0,

        '/posts/*/vote' => 0,
        '/posts/*/bookmark' => 0,
        '/posts/*/report' => 0,

        '/invite/*' => 0,
        '/invite/*/accept' => 0,
        '/invite/*/decline' => 0,

        '/sitemap.xml' => 0,

        /*
         |--------------------------------------------------------------------------
         | Public pages
         |--------------------------------------------------------------------------
         */
        '/' => 300,
        '/home' => 300,

        '/blogs' => 1800,

        '/about' => 3600,
        '/contact' => 0,          // GET page may contain CSRF form; safest uncached
        '/privacy' => 86400,
        '/terms' => 86400,
        '/cookies' => 86400,

        '/getting-started' => 3600,
        '/getting-started/*' => 3600,

        '/profile/*' => 300,

        /*
         |--------------------------------------------------------------------------
         | Public blog surfaces
         |--------------------------------------------------------------------------
         */
        '/blog/*/archive' => 1800,
        '/blog/*/category/*' => 1800,
        '/blog/*/tag/*' => 1800,
        '/blog/*/index-feed' => 900,
        '/blog/*' => 1800,

    ],

    /**
     * Query parameter whitelist per route.
     *
     * Only whitelisted parameters are included in cache keys to prevent
     * fragmentation caused by tracking parameters (e.g. utm_*).
     *
     * Format: 'route' => ['param1', 'param2']
     */
    'query_whitelist' => [
        '/blogs' => ['page', 'q'],
        '/profile/*' => [],

        '/getting-started/*' => [],

        '/blog/*' => ['page', 'comments'],
        '/blog/*/archive' => ['page'],
        '/blog/*/category/*' => ['page'],
        '/blog/*/tag/*' => ['page'],
        '/blog/*/index-feed' => ['page'],
    ],

    /**
     * Default TTL for routes not matching any pattern (seconds).
     *
     * Safer default for an app with mixed public/private surfaces:
     * unmatched routes are not cached unless explicitly allowed above.
     */
    'default_ttl' => 0,

    /**
     * Sample values used to expand wildcard ttl_rules into concrete routes.
     *
     * A blog archive pattern with a wildcard slug segment can't be fetched as-is,
     * so cache:warm substitutes these values to produce real URLs. They must
     * reference content that actually exists, or warming just collects 404s.
     */
    'warmup_mocks' => [
        // Real published blog slugs, picked by post count so warming exercises
        // actual content instead of 404ing on placeholder data. Avoids blogs on
        // the "closest" theme, which is missing archive/index-feed templates
        // (a separate, pre-existing bug unrelated to cache warming).
        'blog_slugs' => ['blog-1', 'evergreen-traffic-notes', 'le-grand-oeuvre'],
        // Categories/tags belong to a single blog (blog_id FK), so these are
        // only ever paired with the first blog slug above (see expandWildcardPattern).
        'categories' => ['freebies', 'backend-architecture', 'guides'],
        'tags' => ['redis', 'caching', 'performance'],
    ],

    /**
     * ttl_rules patterns whose warmed route can never succeed via a synthetic
     * GET - either the endpoint is POST-only, or its wildcard base needs a
     * real id/token/slug that warming has no way to guess. Cache:warm still
     * visits these (for any fragment side effects) but excludes the result
     * from its success-rate calculation.
     */
    'warmup_rate_exempt_patterns' => [
        '/login/submit',
        '/register/submit',
        '/consent',
        '/consent/withdraw',
        '/csp-report',
        '/comments/create',
        '/comments/*/delete',
        '/comments/*/vote',
        '/comments/*/report',
        '/comments/*/pin',
        '/account*',
        '/password/reset*',
        '/subscriptions/unsubscribe/*',
        '/invite/*',
        '/profile/*',
        '/blog/*/subscribe',
    ],

    // ==================== MAINTENANCE & CLEANUP ====================

    /**
     * Garbage collection probability (1–100).
     *
     * Cleanup is triggered probabilistically based on:
     * probability = gc_probability / gc_divisor
     */
    'gc_probability' => (int) ($_ENV['CACHE_GC_PROBABILITY'] ?? 1),

    /**
     * Garbage collection divisor.
     */
    'gc_divisor' => (int) ($_ENV['CACHE_GC_DIVISOR'] ?? 100),

    /**
     * Maximum number of cache files before LRU eviction.
     *
     * Set to 0 for unlimited storage (not recommended).
     */
    'max_files' => (int) ($_ENV['CACHE_MAX_FILES'] ?? 5000),

    /**
     * Path for compiled template PHP files (separate from response cache).
     */
    'compiled_views_path' => ROOT_PATH.'/storage/cache/views',

    /**
     * Maximum age (seconds) before compiled view files are pruned.
     */
    'compiled_views_max_age' => (int) ($_ENV['COMPILED_VIEWS_MAX_AGE'] ?? 604800), // 7 days
];
