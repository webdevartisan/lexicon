<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\NotificationController as InboxController;

/**
 * Admin operations inbox: report thresholds, mail queue failures, a stalled
 * scheduler, events with no per-user relevance that AdminNotificationDispatcher
 * fans out to whoever holds the matching permission.
 *
 * Kept apart from a person's own inbox on purpose. It reads the admin scope
 * only, has no tabs, and its rows never count toward the bell everyone else
 * sees. No further Gate check is needed past the areaAbility below: a row only
 * exists here because the dispatcher already decided this account qualified.
 */
class NotificationController extends InboxController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'accessDashboard';

    /** @var array<int, string> */
    protected array $scopes = ['admin'];

    protected bool $hasTabs = false;

    protected string $listPath = '/admin/notifications';

    protected ?string $settingsPath = null;
}
