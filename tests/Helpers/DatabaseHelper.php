<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Framework\Database;
use PDO;

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
     * @param Database $db Database connection
     * @return void
     */
    public static function cleanDatabase(Database $db): void
    {
        $db->getConnection()->exec('SET FOREIGN_KEY_CHECKS=0');
        
        // Order matters: child tables first, parent tables last
        $tables = [
            'user_social_links',
            'user_preferences', 
            'user_profiles',
            'post_categories',
            'posts',
            'blogs',
            'user_roles',
            'users',
            'categories',
        ];
        
        foreach ($tables as $table) {
            try {
                $db->getConnection()->exec("TRUNCATE TABLE {$table}");
            } catch (\PDOException $e) {
                // Table might not exist in all test scenarios
                error_log("Could not truncate {$table}: " . $e->getMessage());
            }
        }
        
        $db->getConnection()->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    
    /**
     * Assert table has specific row count.
     *
     * @param Database $db Database connection
     * @param string $table Table name
     * @param int $expected Expected count
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
     * @param Database $db Database connection
     * @param string $table Table name
     * @return int Last insert ID
     */
    public static function getLastInsertId(Database $db, string $table): int
    {
        $conn = $db->getConnection();
        return (int) $conn->query("SELECT MAX(id) FROM {$table}")->fetchColumn();
    }
}
