<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\NotificationController as InboxController;

/**
 * The same inbox as /notifications, served inside the dashboard so the bell's
 * "See all" and every action keep a person in the area they were working in.
 * Only the path differs; the view is the dashboard skin of the shared rows.
 */
class NotificationController extends InboxController
{
    protected string $listPath = '/dashboard/notifications';
}
