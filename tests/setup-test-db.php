<?php

declare(strict_types=1);

/**
 * Test Database Setup
 *
 * Creates and populates the test database using the same schema as production.
 */
$testDbHost = '127.0.0.1';
$testDbName = 'blog_test';
$testDbUser = 'root';
$testDbPass = '';

echo "🧪 Setting up test database...\n\n";

// Connect to MySQL server (without database)
try {
    $pdo = new PDO(
        "mysql:host={$testDbHost};charset=utf8mb4",
        $testDbUser,
        $testDbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected to MySQL server\n";
} catch (PDOException $e) {
    echo '❌ Failed to connect to MySQL: '.$e->getMessage()."\n";
    exit(1);
}

// Create test database
try {
    $pdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
    $pdo->exec("CREATE DATABASE `{$testDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Test database '{$testDbName}' created\n";
} catch (PDOException $e) {
    echo '❌ Failed to create test database: '.$e->getMessage()."\n";
    exit(1);
}

// Connect to test database
$pdo->exec("USE `{$testDbName}`");

// Import schema
$schemaFile = __DIR__.'/../database/schema.sql';
if (!file_exists($schemaFile)) {
    echo "❌ Schema file not found: {$schemaFile}\n";
    exit(1);
}

try {
    $sql = file_get_contents($schemaFile);
    $pdo->exec($sql);
    echo "✅ Schema imported\n";
} catch (PDOException $e) {
    echo '❌ Failed to import schema: '.$e->getMessage()."\n";
    exit(1);
}

echo "\n Test database ready!\n\n";
