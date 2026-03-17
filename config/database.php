<?php

declare(strict_types=1);

/**
 * Database Configuration
 *
 */
return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'name' => $_ENV['DB_NAME'] ?? 'lexicon',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',

    /**
     * PDO Connection Options
     *
     * Security: ATTR_EMULATE_PREPARES=false uses native prepared statements
     * Performance: ATTR_STRINGIFY_FETCHES=false preserves native types
     * Timeout: 5 seconds balances responsiveness vs hanging connections
     */
    'options' => [
        // Error handling - throw exceptions for all errors
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,

        // Security - use native prepared statements (prevents SQL injection)
        \PDO::ATTR_EMULATE_PREPARES => false,

        // Data integrity - return associative arrays by default
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,

        // Type safety - preserve native types (ints as ints, not strings)
        \PDO::ATTR_STRINGIFY_FETCHES => false,

        // Connection timeout in seconds
        \PDO::ATTR_TIMEOUT => 5,

        // Character encoding - prevent encoding-based injection
        \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ],

    /**
     * Persistent Connections
     *
     * Generally NOT recommended for web applications (PHP-FPM/Apache).
     * Can cause connection leaks and state pollution between requests.
     * Enable only for long-running CLI processes if needed.
     */
    'persistent' => false,

    /**
     * Query Logging
     *
     * Enable to log all queries for debugging.
     * WARNING: 6-20% performance impact - disable in production.
     * Logs are written to storage/logs/queries.log
     */
    'log_queries' => filter_var($_ENV['DB_LOG_QUERIES'] ?? false, FILTER_VALIDATE_BOOLEAN),

    /**
     * Slow Query Threshold
     *
     * Log queries slower than this threshold (in seconds).
     * Minimal performance impact (<1%).
     */
    'log_slow_queries' => filter_var($_ENV['DB_LOG_SLOW_QUERIES'] ?? true, FILTER_VALIDATE_BOOLEAN),
    'slow_query_threshold' => (float) ($_ENV['DB_SLOW_QUERY_THRESHOLD'] ?? 1.0),
];
