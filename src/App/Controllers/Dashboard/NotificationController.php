<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\NotificationModel;
use Framework\Core\Response;

/**
 * Notification list page, mark-read endpoints, and unread-count JSON.
 *
 * All actions are scoped to the authenticated user — markRead only succeeds
 * when the notification belongs to the caller.
 */
class NotificationController extends AppController
{
    public function __construct(
        private readonly NotificationModel $notifications,
    ) {}

    /**
     * Paginated notification list for the authenticated user.
     *
     * GET /dashboard/notifications
     */
    public function index(): Response
    {
        $user = auth()->user();
        $page = max(1, (int) ($this->request->getParam('page') ?? 1));

        $result = $this->notifications->findPageForUser((int) $user['id'], perPage: 20, page: $page);

        return $this->view('notifications.index', [
            'notificationRows' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'unreadCount' => $this->notifications->unreadCount((int) $user['id']),
        ]);
    }

    /**
     * Mark a single notification as read, then redirect to the originating link
     * if supplied — otherwise to the notifications list.
     *
     * The optional `target` POST field is the click-through URL embedded in the
     * notification item. Only local paths are honored (must start with a single
     * `/`, no `//` or `://`) — anything else falls back to the list to avoid an
     * open-redirect.
     *
     * POST /dashboard/notifications/{id}/read
     */
    public function markRead(string $id): Response
    {
        $user = auth()->user();
        $this->notifications->markRead((int) $id, (int) $user['id']);

        $target = (string) $this->request->postParam('target', '');

        if ($this->isLocalPath($target)) {
            return $this->redirect($target);
        }

        return $this->redirect(lurl('/dashboard/notifications'));
    }

    /**
     * Mark every notification for the authenticated user as read.
     *
     * POST /dashboard/notifications/read-all
     */
    public function markAllRead(): Response
    {
        $user = auth()->user();
        $this->notifications->markAllRead((int) $user['id']);

        $this->flash('success', 'All notifications marked as read.');

        return $this->redirect(lurl('/dashboard/notifications'));
    }

    /**
     * Delete a single notification, then return to the list.
     *
     * The model scopes the delete to the caller, so a forged id belonging to
     * someone else simply removes nothing.
     *
     * POST /dashboard/notifications/{id}/delete
     */
    public function destroy(string $id): Response
    {
        $user = auth()->user();

        if ($this->notifications->deleteForUser((int) $id, (int) $user['id'])) {
            $this->flash('success', 'Notification removed.');
        }

        return $this->redirect(lurl('/dashboard/notifications'));
    }

    /**
     * Delete every notification for the authenticated user.
     *
     * POST /dashboard/notifications/clear-all
     */
    public function clearAll(): Response
    {
        $user = auth()->user();
        $deleted = $this->notifications->deleteAllForUser((int) $user['id']);

        $this->flash('success', $deleted === 1
            ? 'Notification cleared.'
            : $deleted.' notifications cleared.');

        return $this->redirect(lurl('/dashboard/notifications'));
    }

    /**
     * Return the unread notification count as JSON for the bell badge.
     *
     * GET /dashboard/notifications/unread-count
     *
     * @return Response JSON: {"count": int}
     */
    public function unreadCount(): Response
    {
        $user = auth()->user();
        $count = $this->notifications->unreadCount((int) $user['id']);

        return $this->json(['count' => $count]);
    }

    /**
     * Whether a URL is a same-origin path that's safe to redirect to without
     * a host check — must start with a single `/` and contain no scheme or
     * protocol-relative prefix.
     */
    private function isLocalPath(string $target): bool
    {
        if ($target === '' || $target[0] !== '/') {
            return false;
        }

        if (str_starts_with($target, '//')) {
            return false;
        }

        return !str_contains($target, '://');
    }
}
