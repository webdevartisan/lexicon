<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Framework\Cache\CacheService;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * CLI command to prune stale cache files from both caches.
 *
 * Response cache: removes files whose TTL timestamp has expired.
 * Compiled views: removes orphaned .php files older than $maxAgeSeconds
 *                 (i.e., views that were superseded by a template edit).
 *
 * Usage: php cli cache:prune
 * Cron:  0 3 * * * cd /var/www/html && php cli cache:prune >> /var/log/cache-prune.log 2>&1
 */
class CachePruneCommand
{
    /**
     * @param CacheService     $cache    HTTP response / fragment cache service.
     * @param TemplateViewerInterface $renderer Template renderer with compiled view management.
     */
    public function __construct(
        private CacheService $cache,
        private TemplateViewerInterface $renderer,
    ) {}

    /**
     * Execute the cache pruning operation.
     *
     * Scans and removes expired response cache files and compiled view files
     * that are older than the configured max age. Safe to run concurrently
     * with the application due to atomic file operations.
     *
     * @return int Exit code (0 = success, 1 = failure).
     */
    public function handle(): int
    {
        try {
            $startTime = microtime(true);

            echo "Starting cache pruning...\n";

            // ── Response / fragment cache ─────────────────────────────
            $statsBefore = $this->cache->stats();
            echo "Response cache before: {$statsBefore['total_files']} total, "
               . "{$statsBefore['expired_files']} expired\n";

            $deletedResponse = $this->cache->pruneExpired();

            $statsAfter = $this->cache->stats();

            // ── Compiled view cache ───────────────────────────────────
            // Prune compiled view files older than 7 days. These are orphans
            // left behind when a template was edited (mtime-based invalidation
            // creates a new file and abandons the old one).
            $deletedViews = $this->renderer->pruneCompiledViews(maxAgeSeconds: 604800);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            echo "✓ Pruning complete in {$duration}ms\n";
            echo "  Response cache  — deleted: {$deletedResponse} expired files\n";
            echo "  Compiled views  — deleted: {$deletedViews} orphaned files\n";
            echo "  Remaining response files: {$statsAfter['total_files']} "
               . '(' . round($statsAfter['total_size_bytes'] / 1024 / 1024, 2) . " MB)\n";

            if ($statsAfter['files_over_limit'] > 0) {
                echo "⚠ WARNING: {$statsAfter['files_over_limit']} response cache files over configured limit.\n";
                echo "  Consider increasing max_files or reducing TTL values.\n";
            }

            return 0;

        } catch (\Exception $e) {
            echo "✗ Error during cache pruning: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
            return 1;
        }
    }
}
