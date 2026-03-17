<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Framework\Cache\CacheService;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * CLI command to clear ALL cache files — both HTTP response cache and compiled views.
 *
 * More aggressive than pruning: deletes every file regardless of expiry.
 * Use after deployments or when you need a guaranteed fresh state.
 *
 * Usage: php cli cache:clear
 * Cron:  Not recommended — use cache:prune for routine cleanup.
 */
class CacheClearCommand
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
     * Execute the cache clearing operation.
     *
     * Deletes ALL cache files (expired and active) from both the response
     * cache and the compiled view cache. Compiled views will be regenerated
     * automatically on the next request to each template.
     *
     * @return int Exit code (0 = success, 1 = partial/full failure).
     */
    public function handle(): int
    {
        try {
            $startTime = microtime(true);

            echo "Clearing all cache...\n";

            // ── Response / fragment cache ─────────────────────────────
            $statsBefore = $this->cache->stats();
            echo "Response cache before: {$statsBefore['total_files']} files\n";

            $result = $this->cache->clear();

            // ── Compiled view cache ───────────────────────────────────
            $compiledResult = $this->renderer->clearCompiledViews();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $hasFailures = $result['failed'] > 0 || $compiledResult['failed'] > 0;

            echo "✓ Cache cleared in {$duration}ms\n";
            echo "  Response cache  — deleted: {$result['deleted']}, failed: {$result['failed']}\n";
            echo "  Compiled views  — deleted: {$compiledResult['deleted']}, failed: {$compiledResult['failed']}\n";

            if ($hasFailures) {
                echo "⚠ WARNING: Some files could not be deleted — check permissions.\n";
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            echo "✗ Error clearing cache: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
            return 1;
        }
    }
}
