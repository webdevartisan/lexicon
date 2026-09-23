<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Dashboard\NotificationController;

/**
 * The reader's personal inbox: things that happened to them or their own
 * content, like a reply, a moderation warning, or their post's review status.
 *
 * Lives on the front rather than in the back office, the same way the
 * reader surfaces in ReaderController do, since most people who get a reply
 * never open the dashboard at all. Everything else is inherited from the
 * dashboard NotificationController; only the scope and the paths differ.
 */
class PersonalNotificationController extends NotificationController
{
    protected string $scope = 'personal';

    protected string $listPath = '/notifications';
}
