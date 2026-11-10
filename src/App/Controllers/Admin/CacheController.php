<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Services\CacheManagementService;
use Framework\Core\Response;

/**
 * Admin cache management controller.
 *
 * Thin HTTP layer delegating to CacheManagementService.
 * Handles authorization, input validation, and view rendering.
 */
class CacheController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageCache';

    public function __construct(
        protected Response $response,
        private CacheManagementService $cacheService
    ) {}

    /**
     * Display cache management dashboard.
     */
    public function index(): Response
    {
        $cacheStats = $this->cacheService->getStats();

        return $this->view('admin.cache.index', [
            'cacheStats' => $cacheStats,
        ]);
    }

    /**
     * Prune expired cache files.
     */
    public function prune(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->cacheService->prune($this->request->ip());

        $this->flash('success',
            "Pruned {$result['deleted']} expired response files "
            ."and {$result['compiled_views_pruned']} orphaned compiled views "
            ."in {$result['duration_ms']}ms."
        );

        return $this->redirect('/admin/cache');
    }

    /**
     * Clear all cache files.
     */
    public function clear(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $result = $this->cacheService->clear($this->request->ip());

        $this->flash('success',
            "Cleared all cache. Deleted {$result['deleted']} response files "
            ."and {$result['compiled_views_deleted']} compiled views "
            ."({$result['size_mb']} MB freed) in {$result['duration_ms']}ms."
        );

        return $this->redirect('/admin/cache');
    }

    /**
     * Browse stored cache entries with a layer filter and key search.
     */
    public function browse(): Response
    {
        $layer = (string) $this->request->getParam('layer', '');
        $layer = in_array($layer, ['pages', 'fragments'], true) ? $layer : '';
        $search = trim((string) $this->request->getParam('q', ''));
        $page = (int) $this->request->getParam('page', 1);

        return $this->view('admin.cache.browse', [
            'listing' => $this->cacheService->browse($layer, $search, $page),
            'layer' => $layer,
            'search' => $search,
        ]);
    }

    /**
     * List compiled templates with a source search.
     */
    public function browseCompiled(): Response
    {
        $search = trim((string) $this->request->getParam('q', ''));

        return $this->view('admin.cache.browse-compiled', [
            'views' => $this->cacheService->browseCompiled($search),
            'search' => $search,
        ]);
    }

    /**
     * Delete the ticked stored entries and return to the same filtered list.
     */
    public function deleteSelected(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $keys = $this->request->postParam('keys');
        $deleted = $this->cacheService->deleteEntries(is_array($keys) ? $keys : [], $this->request->ip());
        $this->flash('success', "Cache entries deleted: {$deleted}.");

        return $this->redirectToBrowse();
    }

    /**
     * Delete the ticked compiled templates.
     */
    public function deleteSelectedCompiled(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $ids = $this->request->postParam('ids');
        $deleted = $this->cacheService->deleteCompiledMany(is_array($ids) ? $ids : [], $this->request->ip());
        $this->flash('success', "Compiled templates deleted: {$deleted}.");

        return $this->redirect('/admin/cache/browse-compiled');
    }

    /**
     * Show the start of one stored entry or compiled template as plain text.
     */
    public function preview(): Response
    {
        $id = (string) $this->request->getParam('id', '');
        $key = (string) $this->request->getParam('key', '');
        $isCompiled = $id !== '';

        $content = $isCompiled
            ? $this->cacheService->previewCompiled($id)
            : $this->cacheService->previewEntry($key);

        return $this->view('admin.cache.preview', [
            'title' => $isCompiled ? $id : $key,
            'content' => $content,
            'backUrl' => $isCompiled ? '/admin/cache/browse-compiled' : '/admin/cache/browse',
        ]);
    }

    /**
     * Delete one compiled template.
     */
    public function deleteCompiled(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $id = (string) $this->request->postParam('id');

        if ($this->cacheService->deleteCompiled($id, $this->request->ip())) {
            $this->flash('success', 'Compiled template deleted.');
        } else {
            $this->flash('error', 'That template no longer exists.');
        }

        return $this->redirect('/admin/cache/browse-compiled');
    }

    /**
     * Delete one stored entry, then return to the same filtered list.
     */
    public function deleteEntry(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $key = (string) $this->request->postParam('key');

        if ($this->cacheService->deleteEntry($key, $this->request->ip())) {
            $this->flash('success', 'Cache entry deleted.');
        } else {
            $this->flash('error', 'That entry no longer exists.');
        }

        return $this->redirectToBrowse();
    }

    /**
     * Send the admin back to the browse screen with the filter they had.
     *
     * Only the query string is taken from the form, so the redirect can never leave the browse screen.
     */
    private function redirectToBrowse(): Response
    {
        $query = (string) $this->request->postParam('return');

        return $this->redirect('/admin/cache/browse'.(str_starts_with($query, '?') ? $query : ''));
    }

    /**
     * Recompile all templates under the current cache setting.
     */
    public function rebuildViews(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $deleted = $this->cacheService->rebuildCompiledViews($this->request->ip());

        $this->flash('success', "Compiled templates removed: {$deleted}. They rebuild on their next visit.");

        return $this->redirect('/admin/cache');
    }

    /**
     * Clear one cache layer (pages or fragments).
     */
    public function clearLayer(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $layer = (string) $this->request->postParam('layer');

        try {
            $deleted = $this->cacheService->clearLayer($layer, $this->request->ip());
            $this->flash('success', "Entries cleared from {$layer}: {$deleted}.");
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/cache');
    }

    /**
     * Delete cache by pattern.
     */
    public function deletePattern(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $pattern = trim((string) $this->request->postParam('pattern'));

        try {
            $result = $this->cacheService->deletePattern($pattern, $this->request->ip());
            $this->flash('success',
                "Deleted {$result['deleted']} files matching '{$result['pattern']}'."
            );
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        return $this->redirect('/admin/cache');
    }
}
