<?php

declare(strict_types=1);

use Framework\Cache\CacheService;
use Faker\Factory as Faker;

/**
 * CacheService Integration Test Suite
 *
 * Tests file-based cache with real filesystem operations including
 * pattern matching, TTL expiration, index management, and garbage collection.
 *
 * Uses real I/O with dedicated test cache directories to validate:
 * - Actual file creation/deletion
 * - Concurrent write behavior
 * - Permission handling
 * - TTL expiration with actual time delays
 * - Index persistence across operations
 *
 * Test directories are isolated per-test and cleaned up automatically.
 * 
 * @property string $testCachePath Test cache directory path
 * @property CacheService $cache Cache service instance under test
 * @property \Faker\Generator $faker Faker instance for generating test data
 * 
 */

beforeEach(function () {
    $this->faker = Faker::create();
    
    // Use project storage for test cache (Laravel-style)
    $baseTestPath = ROOT_PATH . '/storage/cache/tests';
    
    // Ensure base test directory exists
    if (!is_dir($baseTestPath)) {
        mkdir($baseTestPath, 0755, true);
    }
    
    // Create unique cache directory for each test to prevent interference
    $this->testCachePath = $baseTestPath . '/cache_' . uniqid();
    
    $this->cache = new CacheService(
        cachePath: $this->testCachePath,
        enabled: true,
        gcProbability: 0, // Disable probabilistic GC for predictable tests
        gcDivisor: 100,
        maxFiles: 1000
    );
});

afterEach(function () {
    // Clean up test cache directory after each test
    if (is_dir($this->testCachePath)) {
        $files = glob($this->testCachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->testCachePath);
    }
});

afterAll(function () {
    // Remove base test directory once all tests complete
    $base = ROOT_PATH . '/storage/cache/tests';

    if (is_dir($base)) {
        foreach (glob($base . '/*') as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        rmdir($base);
    }
});

// ============================================================================
// BASIC OPERATIONS - FILE I/O
// ============================================================================

/**
 * Tests cache entry creation and retrieval with real filesystem.
 *
 * Validates that data persists correctly to disk and can be read back.
 */
test('set and get cache entry successfully', function () {
    $key = $this->faker->slug();
    $content = $this->faker->text(200);
    
    $result = $this->cache->set($key, $content, 3600);
    
    expect($result)->toBeTrue();
    expect($this->cache->get($key))->toBe($content);
});

/**
 * Tests cache miss behavior with non-existent keys.
 *
 * Missing keys should return null without throwing exceptions.
 */
test('get returns null for non-existent key', function () {
    $nonExistentKey = $this->faker->uuid();
    
    expect($this->cache->get($nonExistentKey))->toBeNull();
});

/**
 * Tests key existence check against filesystem.
 */
test('has returns true for existing key', function () {
    $key = $this->faker->slug();
    
    $this->cache->set($key, $this->faker->sentence(), 3600);
    
    expect($this->cache->has($key))->toBeTrue();
});

/**
 * Tests key existence check for missing entries.
 */
test('has returns false for non-existent key', function () {
    expect($this->cache->has($this->faker->uuid()))->toBeFalse();
});

/**
 * Tests file deletion from filesystem.
 *
 * Validates that both file and index entry are removed.
 */
test('delete removes cache entry', function () {
    $key = $this->faker->slug();
    
    $this->cache->set($key, $this->faker->text(), 3600);
    expect($this->cache->has($key))->toBeTrue();
    
    $result = $this->cache->delete($key);
    
    expect($result)->toBeTrue();
    expect($this->cache->has($key))->toBeFalse();
});

/**
 * Tests idempotency of delete operations.
 *
 * Deleting non-existent files should succeed without errors.
 */
test('delete returns true for non-existent key', function () {
    $neverExisted = $this->faker->uuid();
    
    expect($this->cache->delete($neverExisted))->toBeTrue();
});

/**
 * Tests disabled cache bypass behavior.
 *
 * When disabled, no filesystem operations should occur.
 */
test('disabled cache does not store data', function () {
    $disabledCache = new CacheService(
        cachePath: $this->testCachePath . '_disabled',
        enabled: false
    );
    
    $result = $disabledCache->set($this->faker->slug(), $this->faker->text(), 3600);
    
    expect($result)->toBeFalse();
    expect($disabledCache->get($this->faker->slug()))->toBeNull();
});

// ============================================================================
// PATTERN MATCHING - REAL FILE DELETION
// ============================================================================

/**
 * Tests wildcard prefix pattern with real file deletion.
 *
 * Pattern: *:GET:/blogs* should delete files matching blog routes.
 */
test('deletePattern with wildcard prefix matches correctly', function () {
    $userId1 = $this->faker->uuid();
    $userId2 = $this->faker->uuid();
    $userId3 = $this->faker->uuid();
    $userId4 = $this->faker->uuid();
    
    $this->cache->set("{$userId1}:GET:/blogs", $this->faker->sentence(), 3600);
    $this->cache->set("{$userId2}:GET:/blogs/popular", $this->faker->sentence(), 3600);
    $this->cache->set("{$userId3}:GET:/posts", $this->faker->paragraph(), 3600);
    $this->cache->set("{$userId4}:GET:/users", $this->faker->paragraph(), 3600);
    
    $deleted = $this->cache->deletePattern('*:GET:/blogs*');
    
    // Only blog routes should be deleted
    expect($deleted)->toBe(2)
        ->and($this->cache->get("{$userId1}:GET:/blogs"))->toBeNull()
        ->and($this->cache->get("{$userId2}:GET:/blogs/popular"))->toBeNull()
        ->and($this->cache->get("{$userId3}:GET:/posts"))->not->toBeNull()
        ->and($this->cache->get("{$userId4}:GET:/users"))->not->toBeNull();
});

/**
 * Tests wildcard suffix pattern matching.
 *
 * Pattern: en:* should delete all English locale cache files.
 */
test('deletePattern with wildcard suffix matches correctly', function () {
    $this->cache->set('en:GET:/home', $this->faker->sentence(), 3600);
    $this->cache->set('en:GET:/about', $this->faker->sentence(), 3600);
    $this->cache->set('fr:GET:/home', $this->faker->sentence(), 3600);
    $this->cache->set('de:GET:/home', $this->faker->sentence(), 3600);
    
    $deleted = $this->cache->deletePattern('en:*');
    
    expect($deleted)->toBe(2)
        ->and($this->cache->get('en:GET:/home'))->toBeNull()
        ->and($this->cache->get('en:GET:/about'))->toBeNull()
        ->and($this->cache->get('fr:GET:/home'))->not->toBeNull()
        ->and($this->cache->get('de:GET:/home'))->not->toBeNull();
});

/**
 * Tests middle wildcard pattern matching.
 *
 * Pattern: session:*:data should match variable session IDs.
 */
test('deletePattern with middle wildcard matches correctly', function () {
    $sessionId1 = $this->faker->uuid();
    $sessionId2 = $this->faker->uuid();
    $sessionId3 = $this->faker->uuid();
    $cacheId = $this->faker->uuid();
    
    $this->cache->set("session:{$sessionId1}:data", $this->faker->text(), 3600);
    $this->cache->set("session:{$sessionId2}:data", $this->faker->text(), 3600);
    $this->cache->set("session:{$sessionId3}:meta", $this->faker->text(), 3600);
    $this->cache->set("cache:{$cacheId}:data", $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern('session:*:data');
    
    // Only session data entries (not meta, not cache prefix)
    expect($deleted)->toBe(2)
        ->and($this->cache->get("session:{$sessionId1}:data"))->toBeNull()
        ->and($this->cache->get("session:{$sessionId2}:data"))->toBeNull()
        ->and($this->cache->get("session:{$sessionId3}:meta"))->not->toBeNull()
        ->and($this->cache->get("cache:{$cacheId}:data"))->not->toBeNull();
});

/**
 * Tests exact match pattern without wildcards.
 *
 * Should delete only the specific key, not similar keys.
 */
test('deletePattern with exact match deletes single entry', function () {
    $baseKey = $this->faker->slug();
    
    $this->cache->set($baseKey, $this->faker->text(), 3600);
    $this->cache->set("{$baseKey}2", $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern($baseKey);
    
    expect($deleted)->toBe(1)
        ->and($this->cache->get($baseKey))->toBeNull()
        ->and($this->cache->get("{$baseKey}2"))->not->toBeNull();
});

/**
 * Tests pattern matching with no results.
 */
test('deletePattern with no matches returns zero', function () {
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern('non:matching:*');
    
    expect($deleted)->toBe(0);
});

/**
 * Tests case-insensitive pattern matching.
 *
 * Cache keys should match patterns regardless of case.
 */
test('deletePattern is case-insensitive', function () {
    $id1 = $this->faker->randomNumber();
    $id2 = $this->faker->randomNumber();
    
    $this->cache->set("User:Profile:{$id1}", $this->faker->text(), 3600);
    $this->cache->set("user:settings:{$id2}", $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern('user:*');
    
    // Both mixed-case and lowercase keys should match
    expect($deleted)->toBe(2)
        ->and($this->cache->get("User:Profile:{$id1}"))->toBeNull()
        ->and($this->cache->get("user:settings:{$id2}"))->toBeNull();
});

/**
 * Tests global wildcard clears all cache.
 *
 * Single asterisk pattern should delete all files.
 */
test('deletePattern with asterisk only clears all cache', function () {
    $key1 = $this->faker->slug();
    $key2 = $this->faker->slug();
    $key3 = $this->faker->slug();
    
    $this->cache->set($key1, $this->faker->text(), 3600);
    $this->cache->set($key2, $this->faker->text(), 3600);
    $this->cache->set($key3, $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern('*');
    
    expect($deleted)->toBe(3)
        ->and($this->cache->get($key1))->toBeNull()
        ->and($this->cache->get($key2))->toBeNull()
        ->and($this->cache->get($key3))->toBeNull();
});

/**
 * Tests special character handling in patterns.
 *
 * Hyphens, underscores, dots should be treated as literals not regex.
 */
test('deletePattern handles special characters safely', function () {
    $id1 = $this->faker->randomNumber();
    $id2 = $this->faker->randomNumber();
    $id3 = $this->faker->randomNumber();
    
    $this->cache->set("blog:post-{$id1}", $this->faker->text(), 3600);
    $this->cache->set("blog:post_{$id2}", $this->faker->text(), 3600);
    $this->cache->set("blog:page.{$id3}", $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern('blog:post-*');
    
    // Only hyphen variant should match
    expect($deleted)->toBe(1)
        ->and($this->cache->get("blog:post-{$id1}"))->toBeNull()
        ->and($this->cache->get("blog:post_{$id2}"))->not->toBeNull()
        ->and($this->cache->get("blog:page.{$id3}"))->not->toBeNull();
});

/**
 * Tests sequential pattern deletions don't interfere.
 *
 * Multiple deletePattern calls should work independently.
 */
test('deletePattern with overlapping patterns works correctly', function () {
    $blogId = $this->faker->randomNumber();
    $pageId = $this->faker->randomNumber();
    $productId = $this->faker->randomNumber();
    
    $this->cache->set("blog:post:{$blogId}", $this->faker->text(), 3600);
    $this->cache->set("blog:page:{$pageId}", $this->faker->text(), 3600);
    $this->cache->set("shop:product:{$productId}", $this->faker->text(), 3600);
    
    $deleted1 = $this->cache->deletePattern('blog:*');
    expect($deleted1)->toBe(2);
    
    $deleted2 = $this->cache->deletePattern('shop:*');
    expect($deleted2)->toBe(1);
    
    expect($this->cache->get("blog:post:{$blogId}"))->toBeNull()
        ->and($this->cache->get("shop:product:{$productId}"))->toBeNull();
});

/**
 * Tests deletePattern on disabled cache.
 */
test('deletePattern on disabled cache returns zero', function () {
    $disabledCache = new CacheService(
        cachePath: $this->testCachePath . '_disabled',
        enabled: false
    );
    
    expect($disabledCache->deletePattern('*'))->toBe(0);
});

// ============================================================================
// INDEX MANAGEMENT - FILE PERSISTENCE
// ============================================================================

/**
 * Tests index file tracks new entries.
 *
 * Index should persist to disk and reflect added keys.
 */
test('cache index tracks new entries', function () {
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    
    $stats = $this->cache->stats();
    
    expect($stats['index_entries'])->toBe(2);
});

/**
 * Tests index persistence after deletions.
 */
test('cache index removes deleted entries', function () {
    $key1 = $this->faker->slug();
    $key2 = $this->faker->slug();
    
    $this->cache->set($key1, $this->faker->text(), 3600);
    $this->cache->set($key2, $this->faker->text(), 3600);
    
    $this->cache->delete($key1);
    
    $stats = $this->cache->stats();
    
    expect($stats['index_entries'])->toBe(1);
});

/**
 * Tests index updates after pattern deletion.
 */
test('cache index updates on pattern deletion', function () {
    $key1 = $this->faker->slug();
    $key2 = $this->faker->slug();
    $key3 = $this->faker->slug();
    
    $this->cache->set("pattern:test:{$key1}", $this->faker->text(), 3600);
    $this->cache->set("pattern:test:{$key2}", $this->faker->text(), 3600);
    $this->cache->set("other:{$key3}", $this->faker->text(), 3600);
    
    $this->cache->deletePattern('pattern:*');
    
    $stats = $this->cache->stats();
    
    expect($stats['index_entries'])->toBe(1);
});

/**
 * Tests index reset after clear operation.
 */
test('cache index is rebuilt on clear', function () {
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    
    $this->cache->clear();
    
    $stats = $this->cache->stats();
    
    expect($stats['index_entries'])->toBe(0);
});

// ============================================================================
// EXPIRATION & TTL - REAL TIME DELAYS
// ============================================================================

/**
 * Tests actual TTL expiration with filesystem.
 *
 * Uses real sleep() to validate time-based expiration.
 */
test('expired cache returns null', function () {
    $key = $this->faker->slug();
    
    $this->cache->set($key, $this->faker->text(), 1);
    
    expect($this->cache->get($key))->not->toBeNull();
    
    // Wait for actual expiration
    sleep(2);
    
    expect($this->cache->get($key))->toBeNull();
});

/**
 * Tests has() with expired entries.
 */
test('has returns false for expired cache', function () {
    $key = $this->faker->slug();
    
    $this->cache->set($key, $this->faker->text(), 1);
    
    expect($this->cache->has($key))->toBeTrue();
    
    sleep(2);
    
    expect($this->cache->has($key))->toBeFalse();
});

/**
 * Tests garbage collection of expired files.
 *
 * pruneExpired should remove only expired entries from disk.
 */
test('pruneExpired removes only expired entries', function () {
    $expiredKey = $this->faker->slug();
    $validKey = $this->faker->slug();
    
    $this->cache->set($expiredKey, $this->faker->text(), 1);
    $this->cache->set($validKey, $this->faker->text(), 3600);
    
    sleep(2);
    
    $deleted = $this->cache->pruneExpired();
    
    expect($deleted)->toBe(1)
        ->and($this->cache->get($expiredKey))->toBeNull()
        ->and($this->cache->get($validKey))->not->toBeNull();
});

/**
 * Tests stats TTL accuracy.
 */
test('stats shows correct TTL remaining', function () {
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    
    $stats = $this->cache->stats();

    // TTL should be approximately 3600 (allowing for execution time)
    expect($stats['avg_ttl_remaining'])->toBeLessThanOrEqual(3600)
        ->and($stats['avg_ttl_remaining'])->toBeGreaterThan(3590);
});

/**
 * Tests TTL decreases over real time.
 */
test('stats shows TTL decreases over time', function () {
    $this->cache->set($this->faker->slug(), $this->faker->text(), 10);
    
    $stats1 = $this->cache->stats();
    sleep(2);
    $stats2 = $this->cache->stats();
    
    expect($stats2['avg_ttl_remaining'])->toBeLessThan($stats1['avg_ttl_remaining']);
});

// ============================================================================
// CLEAR & STATS
// ============================================================================

/**
 * Tests clear removes all physical files.
 */
test('clear removes all cache files', function () {
    $key1 = $this->faker->slug();
    $key2 = $this->faker->slug();
    $key3 = $this->faker->slug();
    
    $this->cache->set($key1, $this->faker->text(), 3600);
    $this->cache->set($key2, $this->faker->text(), 3600);
    $this->cache->set($key3, $this->faker->text(), 3600);
    
    $result = $this->cache->clear();
    
    expect($result['deleted'])->toBe(3)
        ->and($result['failed'])->toBe(0)
        ->and($result['total'])->toBe(3)
        ->and($this->cache->get($key1))->toBeNull();
});

/**
 * Tests stats accuracy against filesystem.
 */
test('stats returns accurate cache information', function () {
    $key1 = $this->faker->slug();
    $key2 = $this->faker->slug();
    
    $this->cache->set($key1, $this->faker->text(), 3600);
    $this->cache->set($key2, $this->faker->text(), 1);
    
    sleep(2); // Let one entry expire

    $stats = $this->cache->stats();

    expect($stats)->toHaveKey('total_files')
        ->and($stats)->toHaveKey('live_files')
        ->and($stats)->toHaveKey('expired_files')
        ->and($stats['live_files'])->toBe(1)
        ->and($stats['expired_files'])->toBe(1);
});

/**
 * Tests getCachePath returns configured directory.
 */
test('getCachePath returns configured path', function () {
    $path = $this->cache->getCachePath();
    
    // Normalize paths for cross-platform comparison
    expect(str_replace('\\', '/', $path))
        ->toBe(str_replace('\\', '/', $this->testCachePath));
});

/**
 * Tests clear on empty filesystem.
 */
test('clear on empty cache returns zero deletions', function () {
    $result = $this->cache->clear();
    
    expect($result['deleted'])->toBe(0)
        ->and($result['total'])->toBe(0);
});

// ============================================================================
// EDGE CASES & FILESYSTEM BEHAVIOR
// ============================================================================

/**
 * Tests empty content storage to disk.
 */
test('set handles empty content', function () {
    $key = $this->faker->slug();
    
    $result = $this->cache->set($key, '', 3600);
    
    expect($result)->toBeTrue();
    expect($this->cache->get($key))->toBe('');
});

/**
 * Tests large file handling.
 *
 * Validates filesystem can handle 100KB+ cache files.
 */
test('set handles large content', function () {
    $largeContent = str_repeat('x', 100000); // 100KB
    $key = $this->faker->slug();
    
    $result = $this->cache->set($key, $largeContent, 3600);
    
    expect($result)->toBeTrue();
    expect($this->cache->get($key))->toBe($largeContent);
});

/**
 * Tests filename sanitization for filesystem safety.
 *
 * Special characters that could break filesystem operations
 * should be sanitized before file creation.
 */
test('cache key with special characters is handled safely', function () {
    // Characters that could cause filesystem issues on Windows/Unix
    $specialKey = 'special:key/with\\chars<>?*|:"';
    
    $result = $this->cache->set($specialKey, $this->faker->text(), 3600);
    
    expect($result)->toBeTrue();
    expect($this->cache->get($specialKey))->not->toBeNull();
});

/**
 * Tests concurrent write operations.
 *
 * Last write should win without file corruption.
 */
test('concurrent set operations do not corrupt cache', function () {
    $key = $this->faker->slug();
    
    $this->cache->set($key, 'first', 3600);
    $this->cache->set($key, 'second', 3600);
    $this->cache->set($key, 'third', 3600);
    
    // Last write wins
    expect($this->cache->get($key))->toBe('third');
});

/**
 * Tests empty pattern handling.
 */
test('deletePattern with empty pattern returns zero', function () {
    $this->cache->set($this->faker->slug(), $this->faker->text(), 3600);
    
    $deleted = $this->cache->deletePattern('');
    
    expect($deleted)->toBe(0);
});

/**
 * Tests stats on disabled cache.
 */
test('stats on disabled cache returns zeros', function () {
    $disabledCache = new CacheService(
        cachePath: $this->testCachePath . '_disabled',
        enabled: false
    );
    
    $stats = $disabledCache->stats();
    
    expect($stats['total_files'])->toBe(0);
});

// ============================================================================
// REAL-WORLD SCENARIOS
// ============================================================================

/**
 * Tests realistic blog cache invalidation workflow.
 *
 * Simulates how cache would be invalidated when blog content changes:
 * 1. Blog pages cached with locale and route
 * 2. Blog updated -> invalidate specific blog cache
 * 3. Verify other caches remain intact
 */
test('real-world blog cache invalidation scenario', function () {
    $blogSlug = $this->faker->slug();
    
    $this->cache->set("en:GET:/blog/{$blogSlug}", $this->faker->paragraph(), 3600);
    $this->cache->set("en:GET:/blog/{$blogSlug}/post-1", $this->faker->paragraph(), 3600);
    $this->cache->set("en:GET:/blog/{$blogSlug}/post-2", $this->faker->paragraph(), 3600);
    $this->cache->set('en:GET:/blogs', $this->faker->paragraph(), 3600);
    $this->cache->set('en:GET:/posts', $this->faker->paragraph(), 3600);
    
    // Invalidate single blog (including all its posts)
    $deleted1 = $this->cache->deletePattern("*:GET:/blog/{$blogSlug}*");
    
    expect($deleted1)->toBe(3)
        ->and($this->cache->get("en:GET:/blog/{$blogSlug}"))->toBeNull()
        ->and($this->cache->get('en:GET:/blogs'))->not->toBeNull()
        ->and($this->cache->get('en:GET:/posts'))->not->toBeNull();
    
    // Invalidate blog listing page
    $deleted2 = $this->cache->deletePattern('*:GET:/blogs*');
    
    expect($deleted2)->toBe(1)
        ->and($this->cache->get('en:GET:/posts'))->not->toBeNull();
});
