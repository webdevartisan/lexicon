<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\CacheService;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Orchestrates cache management operations for the admin panel.
 *
 * Coordinates both the HTTP response/fragment cache (CacheService)
 * and the compiled template view cache (TemplateRenderer) so that
 * admin operations always act on both caches together.
 */
class CacheManagementService
{
    /**
     * @param CacheService     $cache    HTTP response and fragment cache.
     * @param TemplateViewerInterface $renderer Compiled view cache management.
     */
    public function __construct(
        private CacheService $cache,
        private TemplateViewerInterface $renderer,
    ) {}

    /**
     * Get detailed cache statistics covering both cache layers.
     *
     * @return array Combined stats from CacheService and compiled view cache.
     */
    public function getStats(): array
    {
        $stats = $this->cache->stats();

        // Merge compiled view stats so the admin dashboard shows both layers.
        $compiledStats = $this->renderer->compiledViewStats();
        $stats['compiled_views_count']      = $compiledStats['count'];
        $stats['compiled_views_size_bytes'] = $compiledStats['size_bytes'];

        return $stats;
    }

    /**
     * Prune expired response cache files and orphaned compiled view files.
     *
     * Response cache: removes files whose TTL timestamp has expired.
     * Compiled views: removes .php files older than 7 days (orphans left
     * behind when a template is edited and a new compiled file is generated).
     *
     * @param  string $userIp IP address of the admin performing the action.
     * @return array{deleted: int, compiled_views_pruned: int, duration_ms: float}
     */
    public function prune(string $userIp): array
    {
        $startTime = microtime(true);

        $deleted      = $this->cache->pruneExpired();
        $deletedViews = $this->renderer->pruneCompiledViews(604800);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->audit('cache.pruned', [
            'deleted_files'         => $deleted,
            'compiled_views_pruned' => $deletedViews,
            'duration_ms'           => $duration,
        ], $userIp);

        return [
            'deleted'               => $deleted,
            'compiled_views_pruned' => $deletedViews,
            'duration_ms'           => $duration,
        ];
    }

    /**
     * Clear all response cache files and all compiled view files.
     *
     * More aggressive than prune — deletes everything regardless of expiry.
     * Compiled views are regenerated automatically on the next request.
     *
     * @param  string $userIp IP address of the admin performing the action.
     * @return array{deleted: int, compiled_views_deleted: int, size_mb: float, duration_ms: float}
     */
    public function clear(string $userIp): array
    {
        $statsBefore = $this->cache->stats();
        $startTime   = microtime(true);

        $this->cache->clear();
        $compiledResult = $this->renderer->clearCompiledViews();

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $sizeMb   = round($statsBefore['total_size_bytes'] / 1024 / 1024, 2);

        $this->audit('cache.cleared', [
            'deleted_files'           => $statsBefore['total_files'],
            'compiled_views_deleted'  => $compiledResult['deleted'],
            'size_freed_mb'           => $sizeMb,
            'duration_ms'             => $duration,
        ], $userIp);

        return [
            'deleted'                => $statsBefore['total_files'],
            'compiled_views_deleted' => $compiledResult['deleted'],
            'size_mb'                => $sizeMb,
            'duration_ms'            => $duration,
        ];
    }

    /**
     * Delete response cache files matching a glob-style pattern.
     *
     * Only affects the response/fragment cache — compiled views are
     * invalidated automatically via mtime-based hashing, not by pattern.
     *
     * @param  string $pattern  Glob-style pattern (e.g. 'en:GET:/blogs*').
     * @param  string $userIp   IP address of the admin performing the action.
     * @return array{deleted: int, pattern: string, duration_ms: float}
     *
     * @throws \InvalidArgumentException If pattern is empty or contains invalid characters.
     */
    public function deletePattern(string $pattern, string $userIp): array
    {
        if (empty(trim($pattern))) {
            throw new \InvalidArgumentException('Cache pattern cannot be empty.');
        }
        if (!preg_match('/^[\w*?:\/\-_ ]+$/', $pattern)) {
            throw new \InvalidArgumentException('Invalid pattern characters.');
        }

        $startTime = microtime(true);
        $deleted   = $this->cache->deletePattern($pattern);
        $duration  = round((microtime(true) - $startTime) * 1000, 2);

        $this->audit('cache.pattern_deleted', [
            'pattern'       => $pattern,
            'deleted_files' => $deleted,
            'duration_ms'   => $duration,
        ], $userIp);

        return [
            'deleted'     => $deleted,
            'pattern'     => $pattern,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Log an admin cache action to the audit trail.
     *
     * Silently skips if no authenticated user is found, which can happen
     * when operations are triggered from CLI commands.
     */
    private function audit(string $action, array $metadata, string $userIp): void
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }

        audit()->log(
            $user['id'],
            $action,
            'system',
            0,
            $metadata,
            $userIp
        );
    }
}
