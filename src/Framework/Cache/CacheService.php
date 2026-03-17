<?php

declare(strict_types=1);

namespace Framework\Cache;

use Framework\Filesystem\Path;

/**
 * File cache service with pattern matching support.
 *
 * use a simple format: [10-byte timestamp][serialized content]
 * also maintain a key index file for fast pattern-based invalidation.
 *
 * Storage path is outside webroot (/storage/cache) for security.
 * Cache keys are deterministic hashes of (locale + path + canonical query).
 *
 * Example cache file content:
 * 1738300000s:1234:"<!DOCTYPE html><html>...";
 * ^^^^^^^^^^
 * Expiration timestamp (10 bytes, zero-padded)
 *
 * Example index file content (keys.index):
 * en:GET:/→abc123def456
 * en:GET:/blogs→789ghi012jkl
 */
class CacheService
{
    private string $cachePath;

    private bool $enabled;

    private string $indexFile;

    // Cleanup configuration properties to control GC behavior.
    private int $gcProbability;

    private int $gcDivisor;

    private int $maxFiles;

    /**
     * @param  string  $cachePath  Absolute path to cache directory
     * @param  bool  $enabled  Whether caching is enabled
     * @param  int  $gcProbability  Probability numerator for garbage collection (1-100)
     * @param  int  $gcDivisor  Probability divisor for GC (typically 100 = 1% chance)
     * @param  int  $maxFiles  Maximum cache files before LRU eviction (0 = unlimited)
     */
    public function __construct(
        string $cachePath,
        bool $enabled = true,
        int $gcProbability = 1,
        int $gcDivisor = 100,
        int $maxFiles = 5000
    ) {
        $this->cachePath = Path::normalize($cachePath);
        $this->enabled = $enabled;
        $this->indexFile = $this->cachePath.'/keys.index';

        // Validate GC configuration to prevent invalid probability values.
        $this->gcProbability = max(0, min(100, $gcProbability));
        $this->gcDivisor = max(1, $gcDivisor);
        $this->maxFiles = max(0, $maxFiles);

        // Automatically create the cache directory if it doesn't exist.
        if ($this->enabled) {
            $this->ensureCacheDirectoryExists();
        }
    }

    /**
     * Get cached content for key or null if expired/missing.
     *
     * check expiration before returning to ensure stale content is never served.
     * Expired cache files are deleted automatically to prevent disk bloat.
     *
     * @param  string  $key  Cache key (e.g., "en:GET:/blogs?page=2")
     * @return string|null Cached content or null if not found/expired
     */
    public function get(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $hash = $this->hashKey($key);
        $file = $this->cachePath.'/'.$hash.'.cache';

        if (!is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);
        if ($contents === false || strlen($contents) < 10) {
            return null;
        }

        // extract the expiration timestamp from the first 10 bytes.
        $expiration = (int) substr($contents, 0, 10);

        if (time() >= $expiration) {
            // delete expired cache files immediately to free disk space.
            $this->deleteByHash($hash);

            return null;
        }

        // extract the serialized content (everything after byte 10).
        $serialized = substr($contents, 10);
        $content = @unserialize($serialized);

        return is_string($content) ? $content : null;
    }

    /**
     * Store content with TTL and trigger maintenance operations.
     *
     * We use atomic writes (temp file + rename) to prevent partial reads
     * during concurrent requests. We also update the key index for pattern matching.
     * After successful writes, we probabilistically run garbage collection and
     * enforce file limits to prevent disk bloat.
     *
     * @param  string  $key  Cache key
     * @param  string  $content  Content to cache (usually HTML)
     * @param  int  $ttl  Time-to-live in seconds (default: 1 hour)
     * @return bool True on success, false on failure
     */
    public function set(string $key, string $content, int $ttl = 3600): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $hash = $this->hashKey($key);
        $cacheFile = $this->cachePath.'/'.$hash.'.cache';
        $tmpFile = $cacheFile.'.tmp.'.getmypid();

        // Build the cache file content: [timestamp][serialized].
        $expiration = time() + $ttl;
        $timestamp = str_pad((string) $expiration, 10, '0', STR_PAD_LEFT);
        $data = $timestamp.serialize($content);

        // Atomic write: write to temp file, then rename.
        // This ensures concurrent reads never see partial content.
        if (file_put_contents($tmpFile, $data, LOCK_EX) === false) {
            return false;
        }

        // Atomic replace: rename is instant and safe across processes.
        if (!rename($tmpFile, $cacheFile)) {
            @unlink($tmpFile);

            return false;
        }

        // Update the key index for pattern matching.
        $this->indexKey($key, $hash);

        // Run garbage collection probabilistically after successful writes.
        // This spreads cleanup cost across requests instead of blocking one user.
        $this->maybeRunGarbageCollection();

        // Enforce file limits to prevent unbounded cache growth.
        $this->enforceFileLimit();

        return true;
    }

    /**
     * Delete cache by exact key.
     *
     * @param  string  $key  Cache key to delete
     * @return bool True on success or if file doesn't exist
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $hash = $this->hashKey($key);

        return $this->deleteByHash($hash);
    }

    /**
     * Delete cache files matching a glob-style pattern.
     *
     * use glob-style patterns (* = wildcard) to match cache keys.
     * This is useful for invalidating related cache entries.
     *
     * Examples:
     * - deletePattern('*blogs*') → Deletes all blog-related cache
     * - deletePattern('en:*') → Deletes all English locale cache
     * - deletePattern('*page=2*') → Deletes all page 2 cache
     * - deletePattern('*') → Clears all cache
     *
     * @param  string  $pattern  Glob-style pattern (e.g., 'blogs*', '*:GET:/products*')
     * @return int Number of deleted files
     */
    public function deletePattern(string $pattern): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $deleted = 0;
        $index = $this->readIndex();

        foreach ($index as $key => $hash) {
            // use fnmatch for glob-style pattern matching.
            if (fnmatch($pattern, $key, FNM_CASEFOLD)) {
                if ($this->deleteByHash($hash)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Clear all cache files and rebuild the index.
     *
     * delete all .cache files and reset the key index.
     * This is safe to call at any time (atomic file operations prevent corruption).
     *
     * and track both successful deletions and failures to provide complete
     * reporting. Failures might occur due to file permissions or locks.
     *
     * @return array{deleted: int, failed: int, total: int} Deletion statistics
     */
    public function clear(): array
    {
        if (!$this->enabled) {
            return ['deleted' => 0, 'failed' => 0, 'total' => 0];
        }

        $deleted = 0;
        $failed = 0;

        // get all cache files before deletion
        $files = glob($this->cachePath.'/*.cache') ?: [];
        $total = count($files);

        // delete each cache file and track success/failure
        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            } else {
                $failed++;
                // log failures for debugging (file might be locked or permission issue)
                error_log("Failed to delete cache file: {$file}");
            }
        }

        // clear the index file as well (doesn't count toward deleted files)
        if (file_exists($this->indexFile)) {
            unlink($this->indexFile);
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
            'total' => $total,
        ];
    }

    /**
     * Check if a cache key exists and is fresh (not expired).
     *
     * @param  string  $key  Cache key to check
     * @return bool True if cache exists and is fresh
     */
    public function has(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $hash = $this->hashKey($key);
        $file = $this->cachePath.'/'.$hash.'.cache';

        if (!is_file($file)) {
            return false;
        }

        $contents = @file_get_contents($file);
        if ($contents === false || strlen($contents) < 10) {
            return false;
        }

        $expiration = (int) substr($contents, 0, 10);

        return time() < $expiration;
    }

    /**
     * Get detailed cache statistics including maintenance info.
     *
     * We extend the base stats() method with additional GC/limit information
     * for monitoring and debugging cache health.
     *
     * @return array{
     *     total_files: int,
     *     live_files: int,
     *     expired_files: int,
     *     total_size_bytes: int,
     *     avg_ttl_remaining: int,
     *     index_entries: int,
     *     gc_probability: string,
     *     max_files: int,
     *     files_over_limit: int
     * }
     */
    public function stats(): array
    {
        $files = glob($this->cachePath.'/*.cache') ?: [];
        $total = count($files);
        $live = 0;
        $expired = 0;
        $size = 0;
        $totalTtl = 0;
        $now = time();

        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if ($contents === false || strlen($contents) < 10) {
                continue;
            }

            $expiration = (int) substr($contents, 0, 10);
            $fileSize = filesize($file);

            if ($expiration > $now) {
                $live++;
                $size += $fileSize;
                $totalTtl += ($expiration - $now);
            } else {
                $expired++;
            }
        }

        $index = $this->readIndex();

        return [
            'total_files' => $total,
            'live_files' => $live,
            'expired_files' => $expired,
            'total_size_bytes' => $size,
            'avg_ttl_remaining' => $live ? (int) ($totalTtl / $live) : 0,
            'index_entries' => count($index),
            'gc_probability' => $this->gcProbability.'/'.$this->gcDivisor,
            'max_files' => $this->maxFiles,
            'files_over_limit' => max(0, $total - $this->maxFiles),
        ];
    }

    /**
     * Scan and delete all expired cache files.
     *
     * We iterate through all .cache files and check their expiration timestamp
     * (stored in the first 10 bytes). Files where current time >= expiration
     * are deleted immediately to free disk space.
     *
     * This method can be called manually, via probabilistic GC, or from a cron job.
     * It's safe to call concurrently from multiple processes due to atomic file operations.
     *
     * @return int Number of expired files deleted
     */
    public function pruneExpired(): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $deleted = 0;
        $files = glob($this->cachePath.'/*.cache') ?: [];
        $now = time();

        foreach ($files as $file) {
            // skip if file was deleted by another process (race condition).
            if (!is_file($file)) {
                continue;
            }

            // read only the first 10 bytes to check expiration without loading full content.
            $handle = @fopen($file, 'r');
            if (!$handle) {
                continue;
            }

            $timestamp = fread($handle, 10);
            fclose($handle);

            // validate timestamp format before comparison.
            if (strlen($timestamp) !== 10 || !ctype_digit($timestamp)) {
                continue;
            }

            $expiration = (int) $timestamp;

            // delete the file if it's expired.
            if ($now >= $expiration) {
                $hash = basename($file, '.cache');
                if ($this->deleteByHash($hash)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * Generate a deterministic hash for a cache key.
     *
     * use SHA-256 truncated to 12 characters (96 bits) which provides
     * collision resistance for millions of cache entries while keeping
     * filenames short and filesystem-friendly.
     *
     * @param  string  $key  Original cache key
     * @return string 12-character hash
     */
    private function hashKey(string $key): string
    {
        return substr(hash('sha256', $key), 0, 12);
    }

    /**
     * Delete cache file by hash and remove from index.
     *
     * @param  string  $hash  Cache file hash
     * @return bool True on success
     */
    private function deleteByHash(string $hash): bool
    {
        $file = $this->cachePath.'/'.$hash.'.cache';
        $deleted = !file_exists($file) || @unlink($file);

        if ($deleted) {
            // remove the entry from the index.
            $this->removeFromIndex($hash);
        }

        return $deleted;
    }

    /**
     * Add a key→hash mapping to the index file.
     *
     * use file locking to prevent race conditions during concurrent writes.
     * The index format is: key→hash (one per line, → is U+2192 RIGHTWARDS ARROW).
     *
     * @param  string  $key  Cache key
     * @param  string  $hash  Cache file hash
     */
    private function indexKey(string $key, string $hash): void
    {
        // first remove any existing entry for this hash (prevents duplicates).
        $this->removeFromIndex($hash);

        // append the new entry with exclusive lock.
        $entry = $key.'→'.$hash."\n";

        $fp = @fopen($this->indexFile, 'a');
        if (!$fp) {
            return; // Fail silently - caching still works without index.
        }

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $entry);
            flock($fp, LOCK_UN);
        }

        fclose($fp);
    }

    /**
     * Remove a hash from the index file.
     *
     * read the entire index, filter out the target hash, and rewrite.
     * This is safe but potentially slow for large indexes (thousands of entries).
     *
     * @param  string  $hash  Cache file hash to remove
     */
    private function removeFromIndex(string $hash): void
    {
        if (!file_exists($this->indexFile)) {
            return;
        }

        $fp = @fopen($this->indexFile, 'r+');
        if (!$fp) {
            return;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);

            return;
        }

        // read all entries and filter out the target hash.
        $lines = [];
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if ($line && !str_ends_with($line, '→'.$hash)) {
                $lines[] = $line;
            }
        }

        // rewrite the index file without the removed entry.
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, implode("\n", $lines).($lines ? "\n" : ''));

        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * Read the key index file into a key→hash associative array.
     *
     * @return array<string, string> Map of cache keys to file hashes
     */
    private function readIndex(): array
    {
        if (!file_exists($this->indexFile)) {
            return [];
        }

        $lines = @file($this->indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }

        $index = [];
        foreach ($lines as $line) {
            $parts = explode('→', $line, 2);
            if (count($parts) === 2) {
                [$key, $hash] = $parts;
                $index[$key] = $hash;
            }
        }

        return $index;
    }

    /**
     * Ensure cache directory exists with proper permissions.
     *
     * create the directory recursively if needed and verify write permissions.
     * If creation fails, disable caching silently to avoid breaking the app.
     */
    private function ensureCacheDirectoryExists(): void
    {
        if (is_dir($this->cachePath)) {
            // Directory exists - verify it's writable.
            if (!is_writable($this->cachePath)) {
                // disable caching instead of throwing to avoid breaking the app.
                $this->enabled = false;
                error_log("Cache directory exists but is not writable: {$this->cachePath}");
            }

            return;
        }

        // Attempt to create directory with secure permissions (755).
        if (!@mkdir($this->cachePath, 0755, true)) {
            $this->enabled = false;
            error_log("Failed to create cache directory: {$this->cachePath}");

            return;
        }

        // Verify the directory is now writable.
        if (!is_writable($this->cachePath)) {
            $this->enabled = false;
            error_log("Cache directory created but is not writable: {$this->cachePath}");
        }
    }

    /**
     * Probabilistically trigger garbage collection to remove expired files.
     *
     * We use a random probability check (default 1 in 100 requests) to decide
     * when to scan and delete expired cache files. This mimics PHP's native
     * session garbage collection and spreads cleanup cost across many requests
     * instead of blocking a single user with a full cleanup operation.
     *
     * The cleanup is designed to be fast (typically <50ms for hundreds of files)
     * and only processes expired files, skipping fresh cache entries.
     */
    private function maybeRunGarbageCollection(): void
    {
        // skip GC if probability is 0 or if randomness doesn't trigger.
        if ($this->gcProbability === 0) {
            return;
        }

        if (random_int(1, $this->gcDivisor) > $this->gcProbability) {
            return;
        }

        // run the actual garbage collection process.
        $this->pruneExpired();
    }

    /**
     * Enforce maximum file count using LRU (Least Recently Used) eviction.
     *
     * We check if the total number of cache files exceeds the configured maximum.
     * If exceeded, we delete the oldest 10% of files by access time (fileatime).
     * This ensures frequently accessed "hot" cache stays while "cold" cache is evicted.
     *
     * This acts as a safety net to prevent unbounded disk growth, even if
     * TTL-based cleanup or GC fails to run properly.
     *
     * @return int Number of files evicted due to limit enforcement
     */
    private function enforceFileLimit(): int
    {
        // skip enforcement if max_files is 0 (unlimited) or caching is disabled.
        if (!$this->enabled || $this->maxFiles === 0) {
            return 0;
        }

        $files = glob($this->cachePath.'/*.cache') ?: [];
        $totalFiles = count($files);

        // exit early if we're under the limit.
        if ($totalFiles <= $this->maxFiles) {
            return 0;
        }

        // calculate how many files to evict (10% of total, minimum 1).
        $evictCount = max(1, (int) ceil($totalFiles * 0.1));

        // sort files by access time (oldest first) for LRU eviction.
        $filesByAtime = [];
        foreach ($files as $file) {
            // use fileatime to get last access time (LRU metric).
            $atime = @fileatime($file);
            if ($atime === false) {
                $atime = 0; // Fallback for files we can't stat (delete these first).
            }
            $filesByAtime[$file] = $atime;
        }

        // sort ascending (oldest access times first).
        asort($filesByAtime, SORT_NUMERIC);

        // delete the oldest files up to evictCount.
        $evicted = 0;
        foreach (array_keys($filesByAtime) as $file) {
            if ($evicted >= $evictCount) {
                break;
            }

            $hash = basename($file, '.cache');
            if ($this->deleteByHash($hash)) {
                $evicted++;
            }
        }

        return $evicted;
    }
}
