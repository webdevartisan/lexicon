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
     * Seeded once by schema.sql and read by the code under test, so they outlive each test.
     */
    private const PRESERVED_TABLES = [
        'migrations',
        'permissions',
        'reserved_handles',
        'role_permissions',
        'roles',
        'scheduled_tasks',
    ];

    /**
     * Truncate every table a test can write to, leaving the seeded ones in place.
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
            'password_resets',
            'pending_email_changes',
            'notifications',
            'activity_log',
            'account_erasure_records',
            'pending_erasures',
            'user_social_links',
            'user_preferences',
            'user_profiles',
            'user_roles',
            'users',

            // Post-related
            'post_votes',
            'post_bookmarks',
            'post_reviewers',
            'post_tags',
            'post_translations',
            'submissions',
            'reviews',
            'posts',
            'post_reports',
            'comment_votes',
            'comment_reports',
            'comments',

            // Blog-related
            'blog_subscribers',
            'blog_invitations',
            'blog_settings',
            'blog_users',
            'blogs',

            // Taxonomy
            'tags',
            'categories',

            // Misc
            'media',
            'settings',
            'pages',
            'site_content',
            'mail_queue',
            'scheduled_task_runs',
        ];

        foreach ($tables as $table) {
            $db->getConnection()->exec("TRUNCATE TABLE {$table}");
        }

        $db->getConnection()->exec('SET FOREIGN_KEY_CHECKS=1');

        // Guard: fail loudly if a new migration adds a table not listed above
        self::assertEveryTableAccountedFor($db, [...$tables, ...self::PRESERVED_TABLES]);
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
     * Fail loudly if a table is neither truncated nor deliberately preserved.
     *
     * @param  array<int, string>  $accountedFor  Tables truncated or preserved
     */
    private static function assertEveryTableAccountedFor(Database $db, array $accountedFor): void
    {
        $stmt = $db->getConnection()->query("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_TYPE = 'BASE TABLE'
        ");

        $allTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $missing = array_diff($allTables, $accountedFor);

        if (!empty($missing)) {
            throw new \RuntimeException(
                'cleanDatabase() neither truncates nor preserves these tables: '.implode(', ', $missing)
            );
        }
    }
}
