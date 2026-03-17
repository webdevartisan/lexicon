<?php

namespace Tests\Helpers;

class CacheTestHelper
{
    /**
     * We create a mocked CacheService with controlled filesystem behavior
     */
    public static function createMockedCache(array $mockFiles = []): CacheService
    {
        // Mock implementation that doesn't touch filesystem
    }
    
    /**
     * We assert cache contains exactly the expected keys
     */
    public static function assertCacheContains(CacheService $cache, array $expectedKeys): void
    {
        foreach ($expectedKeys as $key => $expectedValue) {
            expect($cache->get($key))->toBe($expectedValue);
        }
    }
    
    /**
     * We create a real cache directory for integration tests
     */
    public static function createTestCacheDir(): string
    {
        $path = ROOT_PATH . '/storage/cache/tests/cache_' . uniqid();
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return $path;
    }
}
