<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Dashboard\NotificationController as DashboardNotificationController;

/**
 * Admin operations inbox: report thresholds, mail queue failures, a stalled
 * scheduler, events with no per-user relevance that AdminNotificationDispatcher
 * fans out to whoever holds the matching permission.
 *
 * No further Gate check is needed past the areaAbility below: a row only
 * exists here because AdminNotificationDispatcher already decided this
 * account qualified for it at write time, and every read stays scoped to the
 * caller's own user_id the same way the personal and content inboxes are.
 */
class NotificationController extends DashboardNotificationController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'accessDashboard';

    protected string $scope = 'admin';

    protected string $listPath = '/admin/notifications';
}
