<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Services\NotificationFeed;
use App\Services\ViewerContext;
use Framework\Core\Response;

/**
 * A person's notification inbox: things about them (personal) and activity on
 * the blogs they work on (content), in one list with a tab for each.
 *
 * The front serves it at /notifications. Dashboard\NotificationController is
 * the same inbox under /dashboard/notifications, so nobody is pulled out of
 * the area they are in, and Admin\NotificationController points the same
 * mechanism at the separate admin operations inbox.
 *
 * Every read and write carries the caller's user id and this inbox's scopes in
 * its WHERE clause. That is the whole authorization layer: there is no other
 * user's id to check a policy against, and an id lifted from another inbox, or
 * another person, simply matches nothing.
 */
class NotificationController extends AppController
{
    /** @var array<int, string> The notifications.scope values this inbox reads. */
    protected array $scopes = NotificationModel::INBOX;

    /** Whether the inbox splits into About you and Your blogs tabs. */
    protected bool $hasTabs = true;

    /** Unlocalized path this controller's actions redirect back to. */
    protected string $listPath = '/notifications';

    /** Where the panel's settings button goes, or null for none. */
    protected ?string $settingsPath = '/account/notifications';

    /** How many rows of each scope the panel loads. */
    private const PANEL_LIMIT = 8;

    private const PER_PAGE = 20;

    public function __construct(
        private readonly NotificationModel $notifications,
        private readonly NotificationFeed $feed,
        private readonly ViewerContext $viewerContext,
    ) {}

    /**
     * One page of the inbox, narrowed by tab and optionally to unread.
     *
     * GET /notifications?tab=all|personal|content&unread=1&page=N
     */
    public function index(): Response
    {
        $userId = $this->userId();
        $counts = $this->notifications->countsByScope($userId, $this->scopes);
        $showTabs = $this->showTabs($counts);
        $tab = $this->tab($this->request->getParam('tab'), $showTabs);
        $onlyUnread = $this->request->getParam('unread') === '1';
        $page = max(1, (int) ($this->request->getParam('page') ?? 1));

        $result = $this->notifications->findPageForUser(
            $userId,
            perPage: self::PER_PAGE,
            page: $page,
            scope: $this->tabScopes($tab),
            onlyUnread: $onlyUnread
        );

        return $this->view('notifications.index', [
            'notificationRows' => $this->feed->present($result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'onlyUnread' => $onlyUnread,
            'tab' => $tab,
            'tabs' => $showTabs ? $this->tabList($counts) : [],
            'tabUnread' => $this->unreadIn($counts, $tab),
            'listPath' => $this->listPath,
            // The id to scroll to and outline, set when a notification with
            // nowhere to go was opened and the message itself is the payload.
            'focusId' => (int) ($this->request->getParam('focus') ?? 0),
        ]);
    }

    /**
     * The bell's panel: the newest rows of each tab, rendered on their own.
     *
     * Fetched when the bell is first opened rather than rendered into every
     * page, so a full-page-cached page never carries anyone's notifications and
     * a reader who never opens it never pays for the query.
     *
     * GET /notifications/panel
     */
    public function panel(): Response
    {
        $userId = $this->userId();
        $counts = $this->notifications->countsByScope($userId, $this->scopes);
        $showTabs = $this->showTabs($counts);
        $rows = $this->notifications->latestPerScope($userId, $this->scopes, self::PANEL_LIMIT);

        return $this->view('partials/notifications/_panel.lex.php', [
            'panelItems' => $this->feed->present($rows),
            'panelLimit' => self::PANEL_LIMIT,
            'tabs' => $showTabs ? $this->tabList($counts) : [],
            'unreadCount' => $this->unreadIn($counts, 'all'),
            'listPath' => $this->listPath,
            'settingsPath' => $this->settingsPath,
        ]);
    }

    /**
     * Open one notification: mark it read, then go where it points.
     *
     * A GET because this is a link in a list, which is what makes middle-click
     * and "open in new tab" work. The target comes from the row's own payload,
     * so the page cannot hand the server a URL to redirect to.
     *
     * GET /notifications/{id}/open
     */
    public function open(string $id): Response
    {
        $userId = $this->userId();
        $row = $this->notifications->findOneForUser((int) $id, $userId, $this->scopes);

        if ($row === null) {
            $this->logMiss('open', (int) $id);
            $this->flash('error', $this->t('notifications.gone'));

            return $this->redirect(lurl($this->listPath));
        }

        $this->notifications->markRead((int) $id, $userId, $this->scopes);
        $href = (string) $this->feed->present([$row])[0]['href'];

        // Nothing to open means the notification is the message, so the list is
        // where it can be read in full.
        if ($href === '') {
            return $this->redirect(lurl($this->listPath).'?focus='.(int) $id.'#notification-'.(int) $id);
        }

        return $this->redirect(lurl($href));
    }

    /**
     * Mark one notification read without going anywhere.
     *
     * POST /notifications/{id}/read
     */
    public function markRead(string $id): Response
    {
        $userId = $this->userId();

        // False also means "already read", so only a row that is not in this
        // inbox at all counts as a miss worth logging.
        if (!$this->notifications->markRead((int) $id, $userId, $this->scopes)
            && $this->notifications->findOneForUser((int) $id, $userId, $this->scopes) === null) {
            $this->logMiss('markRead', (int) $id);
        }

        return $this->backToList();
    }

    /**
     * Mark everything in the current tab read.
     *
     * POST /notifications/read-all
     */
    public function markAllRead(): Response
    {
        $tab = $this->tab($this->request->postParam('tab'), $this->hasTabs);
        $this->notifications->markAllRead($this->userId(), $this->tabScopes($tab));

        $this->flash('success', $this->t('notifications.markedAllRead'));

        return $this->backToList();
    }

    /**
     * Delete one notification.
     *
     * POST /notifications/{id}/delete
     */
    public function destroy(string $id): Response
    {
        if ($this->notifications->deleteForUser((int) $id, $this->userId(), $this->scopes)) {
            $this->flash('success', $this->t('notifications.removed'));
        } else {
            $this->logMiss('destroy', (int) $id);
        }

        return $this->backToList();
    }

    /**
     * Delete everything in the current tab.
     *
     * POST /notifications/clear-all
     */
    public function clearAll(): Response
    {
        $tab = $this->tab($this->request->postParam('tab'), $this->hasTabs);
        $deleted = $this->notifications->deleteAllForUser($this->userId(), $this->tabScopes($tab));

        $this->flash('success', $this->t('notifications.cleared', ['count' => $deleted]));

        return $this->backToList();
    }

    /**
     * The tab a request asked for, or All for anything else.
     *
     * An unknown or forged value is not worth an error page: the reader asked
     * to see their notifications, and All is what that means.
     */
    protected function tab(mixed $requested, bool $tabsAllowed): string
    {
        if (!$tabsAllowed || !is_string($requested) || !in_array($requested, $this->scopes, true)) {
            return 'all';
        }

        return $requested;
    }

    /**
     * @return array<int, string>
     */
    protected function tabScopes(string $tab): array
    {
        return $tab === 'all' ? $this->scopes : [$tab];
    }

    /**
     * Tabs are shown unless they would only ever be empty.
     *
     * A reader with no blog has nothing that could land in Your blogs, so they
     * get a plain list. Anyone with content rows keeps the tabs, even after
     * leaving their last blog, or those rows would have no tab to live in.
     *
     * @param  array<string, array{total: int, unread: int}>  $counts
     */
    private function showTabs(array $counts): bool
    {
        if (!$this->hasTabs) {
            return false;
        }

        return empty($this->viewerContext->current()['is_reader']) || ($counts['content']['total'] ?? 0) > 0;
    }

    /**
     * @param  array<string, array{total: int, unread: int}>  $counts
     * @return array<int, array{key: string, label: string, unread: int}>
     */
    private function tabList(array $counts): array
    {
        $tabs = [['key' => 'all', 'label' => 'notifications.tabAll', 'unread' => $this->unreadIn($counts, 'all')]];
        foreach ($this->scopes as $scope) {
            $tabs[] = ['key' => $scope, 'label' => 'notifications.tab.'.$scope, 'unread' => $counts[$scope]['unread'] ?? 0];
        }

        return $tabs;
    }

    /**
     * @param  array<string, array{total: int, unread: int}>  $counts
     */
    private function unreadIn(array $counts, string $tab): int
    {
        return array_sum(array_map(
            static fn (string $scope): int => $counts[$scope]['unread'] ?? 0,
            $this->tabScopes($tab)
        ));
    }

    private function userId(): int
    {
        return (int) auth()->user()['id'];
    }

    /**
     * Record an id that matched nothing in this inbox: someone else's, one from
     * another inbox, or one already gone. The response is the same either way.
     */
    private function logMiss(string $action, int $id): void
    {
        error_log(sprintf(
            'Notification %s refused: id %d is not in user %d\'s %s inbox',
            $action,
            $id,
            $this->userId(),
            implode('+', $this->scopes)
        ));
    }

    /**
     * Back to wherever the action was taken from, or the list.
     *
     * The panel posts from whatever page the reader is on, and the list posts
     * from a tab and page they chose; sending either to the bare inbox would
     * lose that. backUrlPath() returns a local path only, so a forged Referer
     * cannot bounce anyone off-site.
     */
    private function backToList(): Response
    {
        $back = $this->backUrlPath();

        // What backUrlPath() answers when there is no usable Referer.
        if ($back === '/') {
            return $this->redirect(lurl($this->listPath));
        }

        return $this->redirect($back);
    }
}
