<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\UserModel;

/**
 * Fans an operational event out to every admin who can act on it.
 *
 * Unlike NotificationService, an admin notification has no single recipient:
 * it goes to whoever holds the relevant SystemPolicy permission (plus every
 * administrator, who passes every check). Each qualifying user gets their
 * own 'admin'-scope row in the same notifications table personal and content
 * notifications use, one shared mechanism rather than a parallel system.
 *
 * A cooldown per type stops a standing condition (mail still failing, the
 * scheduler still stalled) from writing a fresh batch of rows on every check
 * that finds it still true.
 */
class AdminNotificationDispatcher
{
    public function __construct(
        private NotificationModel $notifications,
        private UserModel $users,
    ) {}

    /**
     * Fan a type out to every user holding $permission, unless one already
     * went out inside the cooldown window.
     *
     * @param  string  $type  e.g. 'admin.mail_queue_failures'
     * @param  string  $permission  SystemPolicy::AREA_PERMISSIONS slug
     * @param  array<string, mixed>  $data  Payload rendered by the admin inbox
     * @param  int  $cooldownMinutes  Minimum gap between two notifications of the same type
     * @param  string|null  $dedupeKey  Narrows the cooldown to one specific incident
     *                                  (e.g. a case id) instead of the type as a whole,
     *                                  so two different stalled tasks can both notify
     *                                  even inside the same window
     * @return bool True when it actually fanned out (false when skipped by the cooldown or nobody qualifies)
     */
    public function dispatch(string $type, string $permission, array $data, int $cooldownMinutes = 60, ?string $dedupeKey = null): bool
    {
        if ($this->notifications->existsRecentAdminNotification($type, $cooldownMinutes, $dedupeKey)) {
            return false;
        }

        $recipients = $this->users->findAllWithPermission($permission);

        if ($recipients === []) {
            return false;
        }

        if ($dedupeKey !== null) {
            $data['dedupe_key'] = $dedupeKey;
        }

        foreach ($recipients as $recipient) {
            $this->notifications->create((int) $recipient['id'], $type, $data, 'admin');
        }

        return true;
    }
}
