<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\ActivityLogModel;
use Framework\Core\Response;

/**
 * Browse the audit trail written by AuditService.
 *
 * Read-only: entries are never edited or deleted from the UI, since a
 * tamperable audit log defeats its own purpose.
 */
class AuditLogController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'viewAuditLog';

    public function __construct(
        private ActivityLogModel $model
    ) {}

    /**
     * Display the audit trail with action and resource-type filters.
     */
    public function index(): Response
    {
        $action = trim((string) ($this->request->get['action'] ?? ''));
        $resourceType = trim((string) ($this->request->get['resource_type'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $result = $this->model->findWithFilters($action, $resourceType, $page, 25);
        $options = $this->model->filterOptions();

        return $this->view([
            'entries' => $result['data'],
            'pagination' => $result['pagination'],
            'actionFilter' => $action,
            'resourceTypeFilter' => $resourceType,
            'actionOptions' => $options['actions'],
            'resourceTypeOptions' => $options['resource_types'],
        ]);
    }
}
