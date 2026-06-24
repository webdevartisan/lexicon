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
            'total'            => $result['total'],
            'page'             => $result['page'],
            'perPage'          => $result['perPage'],
            'unreadCount'      => $this->notifications->unreadCount((int) $user['id']),
        ]);
    }

    /**
     * Mark a single notification as read, then redirect to the list.
     *
     * POST /dashboard/notifications/{id}/read
     */
    public function markRead(string $id): Response
    {
        $user = auth()->user();
        $this->notifications->markRead((int) $id, (int) $user['id']);

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
}
