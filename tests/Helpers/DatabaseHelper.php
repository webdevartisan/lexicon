<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Framework\Database;

/**
 * Database testing utilities.
 * We provide helpers for common test database operations.
 */
class DatabaseHelper
{
    /**
     * Truncate all test tables in safe order.
     *
     * We disable foreign key checks temporarily to avoid constraint violations,
     * then truncate tables in reverse dependency order for safety.
     *
     * @param  Database  $db  Database connection
     */
    public static function cleanDatabase(Database $db): void
    {
        $db->getConnection()->exec('SET FOREIGN_KEY_CHECKS=0');

        // Order matters: child tables first, parent tables last
        $tables = [
            // User-related
            'account_deletion_requests',
            'data_export_requests',
            'password_resets',
            'activity_log',
            'user_social_links',
            'user_preferences',
            'user_profiles',
            'user_roles',
            'users',

            // Post-related
            'post_reviewers',
            'post_tags',
            'submissions',
            'reviews',
            'posts',
            'comments',

            // Blog-related
            'blog_settings',
            'blog_users',
            'blogs',

            // Taxonomy
            'tags',
            'categories',

            // Misc
            'reserved_slugs',
            'settings',
        ];

        foreach ($tables as $table) {
            try {
                $db->getConnection()->exec("TRUNCATE TABLE {$table}");
            } catch (\PDOException $e) {
                // Table might not exist in all test scenarios
                error_log("Could not truncate {$table}: ".$e->getMessage());
            }
        }

        $db->getConnection()->exec('SET FOREIGN_KEY_CHECKS=1');

        // Guard: fail loudly if a new migration adds a table not listed above
        self::assertAllTablesClean($db, $tables);
    }

    /**
     * Assert table has specific row count.
     *
     * @param  Database  $db  Database connection
     * @param  string  $table  Table name
     * @param  int  $expected  Expected count
     */
    public static function assertTableCount(Database $db, string $table, int $expected): void
    {
        $conn = $db->getConnection();
        $count = (int) $conn->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();

        if ($count !== $expected) {
            throw new \Exception("Expected {$expected} rows in {$table}, found {$count}");
        }
    }

    /**
     * Get last inserted ID for table.
     *
     * @param  Database  $db  Database connection
     * @param  string  $table  Table name
     * @return int Last insert ID
     */
    public static function getLastInsertId(Database $db, string $table): int
    {
        $conn = $db->getConnection();

        return (int) $conn->query("SELECT MAX(id) FROM {$table}")->fetchColumn();
    }

    /**
     * Verify all expected tables were cleaned.
     * Fail loudly if a table is missing from the cleanup list.
     *
     * @param  array  $cleaned  List of tables that were truncated
     */
    public static function assertAllTablesClean(Database $db, array $cleaned): void
    {
        $stmt = $db->getConnection()->query("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_TYPE = 'BASE TABLE'
        ");

        $allTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $missing = array_diff($allTables, $cleaned);

        if (!empty($missing)) {
            throw new \RuntimeException(
                'cleanDatabase() is missing these tables: '.implode(', ', $missing)
            );
        }
    }
}
