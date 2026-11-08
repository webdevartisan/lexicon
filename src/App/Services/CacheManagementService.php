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
     * @param  CacheService  $cache  HTTP response and fragment cache.
     * @param  TemplateViewerInterface  $renderer  Compiled view cache management.
     */
    public function __construct(
        private CacheService $cache,
        private TemplateViewerInterface $renderer,
        private CompiledViewBrowser $compiledViews,
    ) {}

    /**
     * Filter compiled templates for the browse screen.
     *
     * @param  string  $search  Case-insensitive text the source path must contain
     * @return array<int, array<string, mixed>>
     */
    public function browseCompiled(string $search): array
    {
        $views = $this->compiledViews->all($this->cache->isEnabled());

        $matches = [];
        foreach ($views as $view) {
            if ($search === '' || stripos($view['source'], $search) !== false) {
                $matches[] = $view;
            }
        }

        return $matches;
    }

    /**
     * Read the start of a stored entry for the preview screen.
     *
     * @param  string  $key  Key as shown on the browse screen
     * @param  int  $limit  Maximum number of characters returned
     * @return string|null Null if the entry is unknown or already expired.
     */
    public function previewEntry(string $key, int $limit = 4000): ?string
    {
        $content = $this->cache->get($key);

        return $content === null ? null : mb_substr($content, 0, $limit);
    }

    /**
     * Read the start of a compiled template for the preview screen.
     *
     * @return string|null Null if the template no longer exists.
     */
    public function previewCompiled(string $id, int $limit = 4000): ?string
    {
        return $this->compiledViews->read($id, $limit);
    }

    /**
     * Delete several stored entries at once.
     *
     * @param  array<int, mixed>  $keys  Keys as shown on the browse screen
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return int Number of entries removed; unknown keys are skipped.
     */
    public function deleteEntries(array $keys, string $userIp): int
    {
        $known = $this->storedKeys();
        $deleted = 0;

        foreach (array_unique(array_filter($keys, 'is_string')) as $key) {
            if (in_array($key, $known, true) && $this->cache->delete($key)) {
                $deleted++;
            }
        }

        $this->audit('cache.entries_deleted', ['deleted_files' => $deleted], $userIp);

        return $deleted;
    }

    /**
     * Keys currently stored, so a form can only ever aim at entries the index knows.
     *
     * @return array<int, string>
     */
    private function storedKeys(): array
    {
        return array_column($this->cache->entries(), 'key');
    }

    /**
     * Delete several compiled templates at once.
     *
     * @param  array<int, mixed>  $ids  File hashes shown on the browse screen
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return int Number of templates removed; unknown ids are skipped.
     */
    public function deleteCompiledMany(array $ids, string $userIp): int
    {
        $deleted = 0;

        foreach (array_unique(array_filter($ids, 'is_string')) as $id) {
            if ($this->compiledViews->delete($id)) {
                $deleted++;
            }
        }

        $this->audit('cache.compiled_many_deleted', ['deleted_files' => $deleted], $userIp);

        return $deleted;
    }

    /**
     * Delete one compiled template so it recompiles on its next use.
     *
     * @param  string  $id  File hash shown on the browse screen
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return bool False if no such file exists.
     */
    public function deleteCompiled(string $id, string $userIp): bool
    {
        if (!$this->compiledViews->delete($id)) {
            return false;
        }

        $this->audit('cache.compiled_deleted', ['id' => $id], $userIp);

        return true;
    }

    /**
     * Get detailed cache statistics covering both cache layers.
     *
     * @return array<string, mixed> Combined stats from CacheService and compiled view cache.
     */
    public function getStats(): array
    {
        $stats = $this->cache->stats();

        // Merge compiled view stats so the admin dashboard shows both layers.
        $compiledStats = $this->renderer->compiledViewStats();
        $stats['compiled_views_count'] = $compiledStats['count'];
        $stats['compiled_views_size_bytes'] = $compiledStats['size_bytes'];
        $stats['compiled_views_stale'] = count(array_filter(array_column($this->browseCompiled(''), 'stale')));
        $stats['layers'] = $this->cache->layerStats();

        return $stats;
    }

    /**
     * Filter and page the stored entries for the browse screen.
     *
     * @param  string  $layer  'pages', 'fragments' or '' for both
     * @param  string  $search  Case-insensitive text the key must contain
     * @return array{entries: array<int, array<string, mixed>>, total: int, pages: int, page: int}
     */
    public function browse(string $layer, string $search, int $page, int $perPage = 50): array
    {
        $entries = [];
        foreach ($this->cache->entries() as $entry) {
            $inLayer = $layer === '' || $entry['layer'] === $layer;
            $matchesSearch = $search === '' || stripos($entry['key'], $search) !== false;

            if ($inLayer && $matchesSearch) {
                $entries[] = $entry;
            }
        }

        $total = count($entries);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [
            'entries' => array_slice($entries, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'pages' => $pages,
            'page' => $page,
        ];
    }

    /**
     * Delete one stored entry by its exact key.
     *
     * @param  string  $key  Key as shown on the browse screen
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return bool False if no entry has that key.
     */
    public function deleteEntry(string $key, string $userIp): bool
    {
        if (!in_array($key, $this->storedKeys(), true)) {
            return false;
        }

        $this->cache->delete($key);
        $this->audit('cache.entry_deleted', ['key' => $key], $userIp);

        return true;
    }

    /**
     * Delete the compiled templates so they recompile under the current cache setting.
     *
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return int Number of compiled templates removed.
     */
    public function rebuildCompiledViews(string $userIp): int
    {
        $deleted = $this->renderer->clearCompiledViews()['deleted'];

        $this->audit('cache.views_rebuilt', ['compiled_views_deleted' => $deleted], $userIp);

        return $deleted;
    }

    /**
     * Clear one stored layer (full pages or fragments) and leave the other alone.
     *
     * @param  string  $layer  'pages' or 'fragments'
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return int Number of entries removed.
     *
     * @throws \InvalidArgumentException If the layer is unknown.
     */
    public function clearLayer(string $layer, string $userIp): int
    {
        if (!in_array($layer, ['pages', 'fragments'], true)) {
            throw new \InvalidArgumentException('Unknown cache layer.');
        }

        $deleted = $this->cache->deleteLayer($layer);

        $this->audit('cache.layer_cleared', ['layer' => $layer, 'deleted_files' => $deleted], $userIp);

        return $deleted;
    }

    /**
     * Prune expired response cache files and orphaned compiled view files.
     *
     * Response cache: removes files whose TTL timestamp has expired.
     * Compiled views: removes .php files older than 7 days (orphans left
     * behind when a template is edited and a new compiled file is generated).
     *
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return array{deleted: int, compiled_views_pruned: int, duration_ms: float}
     */
    public function prune(string $userIp): array
    {
        $startTime = microtime(true);

        $deleted = $this->cache->pruneExpired();
        $deletedViews = $this->renderer->pruneCompiledViews(604800);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->audit('cache.pruned', [
            'deleted_files' => $deleted,
            'compiled_views_pruned' => $deletedViews,
            'duration_ms' => $duration,
        ], $userIp);

        return [
            'deleted' => $deleted,
            'compiled_views_pruned' => $deletedViews,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Clear all response cache files and all compiled view files.
     *
     * More aggressive than prune: deletes everything regardless of expiry.
     * Compiled views are regenerated automatically on the next request.
     *
     * @param  string  $userIp  IP address of the admin performing the action.
     * @return array{deleted: int, compiled_views_deleted: int, size_mb: float, duration_ms: float}
     */
    public function clear(string $userIp): array
    {
        $statsBefore = $this->cache->stats();
        $startTime = microtime(true);

        $this->cache->clear();
        $compiledResult = $this->renderer->clearCompiledViews();

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $sizeMb = round($statsBefore['total_size_bytes'] / 1024 / 1024, 2);

        $this->audit('cache.cleared', [
            'deleted_files' => $statsBefore['total_files'],
            'compiled_views_deleted' => $compiledResult['deleted'],
            'size_freed_mb' => $sizeMb,
            'duration_ms' => $duration,
        ], $userIp);

        return [
            'deleted' => $statsBefore['total_files'],
            'compiled_views_deleted' => $compiledResult['deleted'],
            'size_mb' => $sizeMb,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Delete response cache files matching a glob-style pattern.
     *
     * Only affects the response/fragment cache. Compiled views are
     * invalidated automatically via mtime-based hashing, not by pattern.
     *
     * @param  string  $pattern  Glob-style pattern (e.g. 'en:GET:/discover*').
     * @param  string  $userIp  IP address of the admin performing the action.
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
        $deleted = $this->cache->deletePattern($pattern);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->audit('cache.pattern_deleted', [
            'pattern' => $pattern,
            'deleted_files' => $deleted,
            'duration_ms' => $duration,
        ], $userIp);

        return [
            'deleted' => $deleted,
            'pattern' => $pattern,
            'duration_ms' => $duration,
        ];
    }

    /**
     * Log an admin cache action to the audit trail.
     *
     * Silently skips if no authenticated user is found, which can happen
     * when operations are triggered from CLI commands.
     *
     * @param  array<string, mixed>  $metadata  Operation context for the audit row
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
