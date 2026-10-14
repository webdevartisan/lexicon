<?php

declare(strict_types=1);

use App\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Controllers\Dashboard\NotificationController;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\NotificationFeed;
use App\Services\ViewerContext;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * Feature tests for the unified notification inbox.
 *
 * Runs the dashboard skin of the inbox, and the admin inbox for separation,
 * against the real database and a real Auth session. The view layer is
 * stubbed so the data passed to render() can be inspected directly.
 */
beforeEach(function () {
    // Roll back the connection Pest opened — we need to run on the container's
    // shared Database so that auth() (which resolves UserModel through the
    // container) sees rows inserted by this test.
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = App::container()->get(\Framework\Database::class);

    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    expect($this->db->getConnection())->toHaveActiveTransaction();

    $this->userModel = new UserModel($this->db);
    $this->notifications = new NotificationModel($this->db);

    $password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->userModel)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($email, $password))->toBeTrue();

    $this->controller = new NotificationController(
        $this->notifications,
        App::container()->get(NotificationFeed::class),
        App::container()->get(ViewerContext::class),
    );

    $this->mockViewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        /** @var array<string,mixed> */
        public array $capturedData = [];

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;
            $this->capturedData = $data;

            return 'mocked view';
        }

        public function addGlobals(array $vars): void {}

        public function compiledViewStats(): array
        {
            return [];
        }

        public function pruneCompiledViews(int $maxAgeSeconds): int
        {
            return 0;
        }

        public function clearCompiledViews(): array
        {
            return [];
        }
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
});

// ============================================================================
// index(): one inbox, narrowed by tab
// ============================================================================

it('renders the inbox with personal and content rows together', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'post.submitted', ['post_id' => 5, 'post_title' => 'Hi'], 'content');

    setupController($this->controller, makeRequest('/dashboard/notifications', 'GET'), $this->mockViewer);
    $response = $this->controller->index();

    expect($response->getStatusCode())->toBe(200)
        ->and($this->mockViewer->capturedTemplate)->toBe('notifications.index')
        ->and($this->mockViewer->capturedData['tab'])->toBe('all')
        ->and($this->mockViewer->capturedData['total'])->toBe(2)
        ->and($this->mockViewer->capturedData['tabUnread'])->toBe(2)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(2);
});

it('never lists an admin row in the inbox', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    setupController($this->controller, makeRequest('/dashboard/notifications', 'GET'), $this->mockViewer);
    $this->controller->index();

    expect($this->mockViewer->capturedData['total'])->toBe(1)
        ->and(array_column($this->mockViewer->capturedData['notificationRows'], 'scope'))->toBe(['personal']);
});

it('only shows notifications belonging to the authenticated user', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 99], 'personal');

    setupController($this->controller, makeRequest('/dashboard/notifications', 'GET'), $this->mockViewer);
    $this->controller->index();

    expect($this->mockViewer->capturedData['total'])->toBe(1);
});

it('narrows to one tab, and All equals both tabs together', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 2], 'personal');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');

    $totals = [];
    foreach (['all', 'personal', 'content'] as $tab) {
        setupController($this->controller, makeRequest('/notifications', 'GET', [], ['tab' => $tab]), $this->mockViewer);
        $this->controller->index();
        $totals[$tab] = $this->mockViewer->capturedData['total'];
        expect($this->mockViewer->capturedData['tab'])->toBe($tab);
    }

    expect($totals)->toBe(['all' => 3, 'personal' => 2, 'content' => 1]);
});

it('falls back to All for an unknown, forged or array tab', function (mixed $tab) {
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    setupController($this->controller, makeRequest('/notifications', 'GET', [], ['tab' => $tab]), $this->mockViewer);
    $this->controller->index();

    expect($this->mockViewer->capturedData['tab'])->toBe('all')
        ->and($this->mockViewer->capturedData['total'])->toBe(1);
})->with(['garbage', 'admin', [['personal']]]);

it('gives a reader with nothing about their blogs a plain list without tabs', function () {
    $this->notifications->create($this->userId, 'comment.reply', ['post_title' => 'x'], 'personal');

    setupController($this->controller, makeRequest('/notifications', 'GET'), $this->mockViewer);
    $this->controller->index();

    expect($this->mockViewer->capturedData['tabs'])->toBe([]);
});

it('keeps the tabs, with per-tab unread counts, once there is blog activity', function () {
    $this->notifications->create($this->userId, 'comment.reply', ['post_title' => 'x'], 'personal');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');

    setupController($this->controller, makeRequest('/notifications', 'GET'), $this->mockViewer);
    $this->controller->index();

    expect(array_column($this->mockViewer->capturedData['tabs'], 'unread', 'key'))
        ->toBe(['all' => 3, 'personal' => 1, 'content' => 2]);
});

it('paginates the index using the ?page query parameter', function () {
    for ($i = 0; $i < 25; $i++) {
        $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => $i], 'personal');
    }

    setupController($this->controller, makeRequest('/notifications', 'GET', [], ['page' => '2']), $this->mockViewer);
    $this->controller->index();

    expect($this->mockViewer->capturedData['page'])->toBe(2)
        ->and($this->mockViewer->capturedData['total'])->toBe(25)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(5);
});

it('narrows the list to unread when asked', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 5], 'content');
    $read = (int) $this->notifications->findForUser($this->userId)[0]['id'];
    $this->notifications->markRead($read, $this->userId);

    setupController($this->controller, makeRequest('/notifications', 'GET', [], ['unread' => '1']), $this->mockViewer);
    $this->controller->index();

    expect($this->mockViewer->capturedData['onlyUnread'])->toBeTrue()
        ->and($this->mockViewer->capturedData['total'])->toBe(1);
});

// ============================================================================
// Row actions: ownership and scope guards
// ============================================================================

it('marks a notification read and returns to the list', function () {
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 42], 'personal');
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    setupController($this->controller, makeRequest('/dashboard/notifications/'.$id.'/read', 'POST'), $this->mockViewer);
    $response = $this->controller->markRead((string) $id);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/dashboard/notifications')
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);
});

it('cannot mark, open or delete a notification belonging to another user', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 9], 'personal');
    $foreignId = (int) $this->notifications->findForUser($otherUserId)[0]['id'];

    setupController($this->controller, makeRequest('/notifications/'.$foreignId, 'POST'), $this->mockViewer);
    $this->controller->markRead((string) $foreignId);
    $this->controller->destroy((string) $foreignId);
    $open = $this->controller->open((string) $foreignId);

    expect($open->getHeader('Location'))->not->toContain('/dashboard/post/9')
        ->and($this->notifications->unreadCount($otherUserId))->toBe(1)
        ->and($this->notifications->findForUser($otherUserId))->toHaveCount(1);
});

it('cannot reach an admin row through the inbox, even one of the caller\'s own', function () {
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');
    $adminId = (int) $this->notifications->findForUser($this->userId, scope: 'admin')[0]['id'];

    setupController($this->controller, makeRequest('/notifications/'.$adminId, 'POST'), $this->mockViewer);
    $this->controller->markRead((string) $adminId);
    $this->controller->destroy((string) $adminId);
    $open = $this->controller->open((string) $adminId);

    expect($open->getHeader('Location'))->not->toContain('/admin/scheduled-tasks')
        ->and($this->notifications->unreadCount($this->userId, 'admin'))->toBe(1);
});

it('opens a notification by marking it read and going where it points', function () {
    $this->notifications->create($this->userId, 'comment.reply', [
        'blog_slug' => 'field-notes',
        'post_slug' => 'on-rivers',
        'comment_id' => 77,
    ], 'personal');
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    setupController($this->controller, makeRequest('/dashboard/notifications/'.$id.'/open', 'GET'), $this->mockViewer);
    $response = $this->controller->open((string) $id);

    expect($response->getHeader('Location'))->toContain('/blog/field-notes/on-rivers#comment-77')
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);
});

it('sends a notification with nowhere to go back to the list, focused on itself', function () {
    $this->notifications->create($this->userId, 'moderation.warning', [
        'subject_type' => 'comment',
        'subject_label' => 'a comment',
        'message' => 'Please keep it civil.',
    ], 'personal');
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    setupController($this->controller, makeRequest('/dashboard/notifications/'.$id.'/open', 'GET'), $this->mockViewer);
    $response = $this->controller->open((string) $id);

    expect($response->getHeader('Location'))->toContain('/dashboard/notifications?focus='.$id)
        ->and($response->getHeader('Location'))->toContain('#notification-'.$id);
});

// ============================================================================
// Bulk actions: the current tab only
// ============================================================================

it('marks all read in the current tab and leaves the other tab alone', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    setupController($this->controller, makeRequest('/notifications/read-all', 'POST', ['tab' => 'personal']), $this->mockViewer);
    $this->controller->markAllRead();

    expect($this->notifications->unreadCount($this->userId, 'personal'))->toBe(0)
        ->and($this->notifications->unreadCount($this->userId, 'content'))->toBe(1)
        ->and($this->notifications->unreadCount($this->userId, 'admin'))->toBe(1);
});

it('marks all read across both tabs on All, never the admin inbox', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    setupController($this->controller, makeRequest('/notifications/read-all', 'POST', ['tab' => 'all']), $this->mockViewer);
    $this->controller->markAllRead();

    expect($this->notifications->unreadCount($this->userId, NotificationModel::INBOX))->toBe(0)
        ->and($this->notifications->unreadCount($this->userId, 'admin'))->toBe(1);
});

it('clears the current tab only', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    setupController($this->controller, makeRequest('/notifications/clear-all', 'POST', ['tab' => 'content']), $this->mockViewer);
    $this->controller->clearAll();

    expect(array_column($this->notifications->findForUser($this->userId), 'scope'))
        ->toEqualCanonicalizing(['personal', 'admin']);
});

it('does not touch another user\'s notifications when marking all read', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 2], 'personal');

    setupController($this->controller, makeRequest('/notifications/read-all', 'POST'), $this->mockViewer);
    $this->controller->markAllRead();

    expect($this->notifications->unreadCount($otherUserId))->toBe(1);
});

// ============================================================================
// panel()
// ============================================================================

it('renders the panel with each tab\'s newest rows from one fetch', function () {
    foreach (range(1, 10) as $i) {
        $this->notifications->create($this->userId, 'post.submitted', ['post_id' => $i, 'post_title' => 'Post '.$i], 'content');
    }
    $this->notifications->create($this->userId, 'comment.reply', ['post_title' => 'Mine'], 'personal');

    setupController($this->controller, makeRequest('/notifications/panel', 'GET'), $this->mockViewer);
    $response = $this->controller->panel();
    $items = $this->mockViewer->capturedData['panelItems'];

    expect($response->getStatusCode())->toBe(200)
        ->and($this->mockViewer->capturedTemplate)->toBe('partials/notifications/_panel.lex.php')
        // Eight content rows and the one personal row: a busy tab cannot crowd out the other.
        ->and(array_count_values(array_column($items, 'scope')))->toBe(['personal' => 1, 'content' => 8])
        ->and($this->mockViewer->capturedData['unreadCount'])->toBe(11)
        ->and($this->mockViewer->capturedData['settingsPath'])->toBe('/account/notifications');
});

// ============================================================================
// The admin inbox stays separate
// ============================================================================

it('reads and clears only admin rows in the admin inbox, without tabs or settings', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->notifications->create($this->userId, 'comment.on_blog', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    $admin = App::container()->get(AdminNotificationController::class);
    setupController($admin, makeRequest('/admin/notifications/panel', 'GET'), $this->mockViewer);
    $admin->panel();

    expect(array_column($this->mockViewer->capturedData['panelItems'], 'scope'))->toBe(['admin'])
        ->and($this->mockViewer->capturedData['tabs'])->toBe([])
        ->and($this->mockViewer->capturedData['settingsPath'])->toBeNull()
        ->and($this->mockViewer->capturedData['unreadCount'])->toBe(1);

    // A forged tab cannot widen the admin inbox to another scope.
    setupController($admin, makeRequest('/admin/notifications/clear-all', 'POST', ['tab' => 'personal']), $this->mockViewer);
    $admin->clearAll();

    expect(array_column($this->notifications->findForUser($this->userId), 'scope'))
        ->toEqualCanonicalizing(['personal', 'content']);
});
