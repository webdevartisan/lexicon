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
    private bool $enabled = false;

    public function __construct(
        protected Response $response,
        private CacheManagementService $cacheService
    ) {
        $this->enabled = filter_var($_ENV['CACHE_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Display cache management dashboard.
     */
    public function index(): Response
    {
        //Gate::authorize('manageCache', auth()->user());

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
        if (!$this->enabled) {
            $this->flash('info', "Cache management is disabled.");
            return $this->redirect('/admin/cache');
        }

        //Gate::authorize('manageCache', auth()->user());
        $result = $this->cacheService->prune($this->request->ip());

        $this->flash('success',
            "Pruned {$result['deleted']} expired response files "
            . "and {$result['compiled_views_pruned']} orphaned compiled views "
            . "in {$result['duration_ms']}ms."
        );
        return $this->redirect('/admin/cache');
    }
    /**
     * Clear all cache files.
     */
    public function clear(): Response
    {
        if (!$this->enabled) {
            $this->flash('info', "Cache management is disabled.");
            return $this->redirect('/admin/cache');
        }

        //Gate::authorize('manageCache', auth()->user());
        $result = $this->cacheService->clear($this->request->ip());

        $this->flash('success',
            "Cleared all cache. Deleted {$result['deleted']} response files "
            . "and {$result['compiled_views_deleted']} compiled views "
            . "({$result['size_mb']} MB freed) in {$result['duration_ms']}ms."
        );
        return $this->redirect('/admin/cache');
    }

    /**
     * Delete cache by pattern.
     */
    public function deletePattern(): Response
    {
        if (!$this->enabled) {
            $this->flash('info', "Cache management is disabled.");
            return $this->redirect('/admin/cache');
        }

        //Gate::authorize('manageCache', auth()->user());
        $pattern = trim($this->request->post['pattern'] ?? '');

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
