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
 *
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
