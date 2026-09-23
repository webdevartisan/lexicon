<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\NotificationModel;
use App\Presenters\NotificationPresenter;
use Framework\Core\Response;

/**
 * Notification list page, mark-read endpoints, and unread-count JSON.
 *
 * All actions are scoped to the authenticated user — markRead only succeeds
 * when the notification belongs to the caller.
 *
 * This is the 'content' (dashboard-relevant) inbox: a post submitted for
 * review, a comment awaiting moderation, blog-wide activity, the kind of
 * thing a blog manager already expects the dashboard to surface. `PersonalNotificationController`
 * and `Admin\NotificationController` extend this with a different `$scope` and
 * `$listPath` for the personal and admin inboxes; the CRUD underneath is
 * identical on purpose; the three inboxes are one mechanism, not three.
 */
class NotificationController extends AppController
{
    /** Which inbox this controller reads: notifications.scope. */
    protected string $scope = 'content';

    /** Unlocalized path this controller's actions redirect back to. */
    protected string $listPath = '/dashboard/notifications';

    /** How many rows the masthead panel shows before "see all". */
    private const PANEL_LIMIT = 8;

    public function __construct(
        private readonly NotificationModel $notifications,
    ) {}

    /**
     * Paginated notification list for the authenticated user.
     *
     * `?filter=unread` narrows it to what has not been read yet, which is the
     * only filter worth having: everything else about a notification is
     * already visible in the row.
     *
     * GET /dashboard/notifications
     */
    public function index(): Response
    {
        $user = auth()->user();
        $page = max(1, (int) ($this->request->getParam('page') ?? 1));
        $onlyUnread = $this->request->getParam('filter') === 'unread';

        $result = $this->notifications->findPageForUser(
            (int) $user['id'],
            perPage: 20,
            page: $page,
            scope: $this->scope,
            onlyUnread: $onlyUnread
        );

        return $this->view('notifications.index', [
            'notificationRows' => NotificationPresenter::forAll($result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'onlyUnread' => $onlyUnread,
            'unreadCount' => $this->notifications->unreadCount((int) $user['id'], $this->scope),
            'listPath' => $this->listPath,
            // The id to scroll to and outline, set when a notification with
            // nowhere to go was opened and the message itself is the payload.
            'focusId' => (int) ($this->request->getParam('focus') ?? 0),
        ]);
    }

    /**
     * Open one notification: mark it read, then go where it points.
     *
     * A GET rather than a POST because this is a link in a list, and a link is
     * what makes middle-click, "open in new tab" and the browser's own history
     * work. The target is worked out here from the row's own payload, so the
     * page can no longer hand the server a URL to redirect to.
     *
     * GET /dashboard/notifications/{id}/open
     */
    public function open(string $id): Response
    {
        $user = auth()->user();
        $row = $this->notifications->findOneForUser((int) $id, (int) $user['id'], $this->scope);

        if ($row === null) {
            $this->flash('error', 'That notification is no longer here.');

            return $this->redirect(lurl($this->listPath));
        }

        $this->notifications->markRead((int) $id, (int) $user['id'], $this->scope);

        $item = NotificationPresenter::for($row);

        // Nothing to open means the notification is the message, so the list is
        // where it can be read in full.
        if ($item['href'] === '') {
            return $this->redirect(lurl($this->listPath).'?focus='.(int) $id.'#notification-'.(int) $id);
        }

        return $this->redirect(lurl($item['href']));
    }

    /**
     * The masthead panel's contents: the latest few, rendered on their own.
     *
     * Fetched when the bell is first opened rather than rendered into every
     * page, so a reader who never opens it never pays for the query, and one
     * who does always sees the current state even on a cached page.
     *
     * GET /notifications/panel
     */
    public function panel(): Response
    {
        $user = auth()->user();
        $rows = $this->notifications->findForUser((int) $user['id'], self::PANEL_LIMIT, false, $this->scope);

        return $this->view('partials/public/_notification_panel.lex.php', [
            'panelItems' => NotificationPresenter::forAll($rows),
            'unreadCount' => $this->notifications->unreadCount((int) $user['id'], $this->scope),
            'listPath' => $this->listPath,
        ]);
    }

    /**
     * Mark a single notification as read, without going anywhere.
     *
     * POST /dashboard/notifications/{id}/read
     */
    public function markRead(string $id): Response
    {
        $user = auth()->user();
        $this->notifications->markRead((int) $id, (int) $user['id'], $this->scope);

        return $this->backToList();
    }

    /**
     * Mark every notification for the authenticated user as read.
     *
     * POST /dashboard/notifications/read-all
     */
    public function markAllRead(): Response
    {
        $user = auth()->user();
        $this->notifications->markAllRead((int) $user['id'], $this->scope);

        $this->flash('success', 'All notifications marked as read.');

        return $this->backToList();
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

        if ($this->notifications->deleteForUser((int) $id, (int) $user['id'], $this->scope)) {
            $this->flash('success', 'Notification removed.');
        }

        return $this->backToList();
    }

    /**
     * Delete every notification for the authenticated user.
     *
     * POST /dashboard/notifications/clear-all
     */
    public function clearAll(): Response
    {
        $user = auth()->user();
        $deleted = $this->notifications->deleteAllForUser((int) $user['id'], $this->scope);

        $this->flash('success', $deleted === 1
            ? 'Notification cleared.'
            : $deleted.' notifications cleared.');

        return $this->backToList();
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
        $count = $this->notifications->unreadCount((int) $user['id'], $this->scope);

        return $this->json(['count' => $count]);
    }

    /**
     * Back to wherever the action was taken from, or the list.
     *
     * The masthead panel posts from whatever page the reader happens to be on,
     * and the list itself posts from a page and filter they chose; sending
     * either to the bare inbox path would lose that. backUrlPath() hands back
     * the local path only, so a forged Referer cannot bounce anyone off-site.
     */
    private function backToList(): Response
    {
        $back = $this->backUrlPath();

        // What backUrlPath() answers when there is no usable Referer, and the
        // home page is not where somebody clearing a notification meant to be.
        if ($back === '/') {
            return $this->redirect(lurl($this->listPath));
        }

        return $this->redirect($back);
    }
}
