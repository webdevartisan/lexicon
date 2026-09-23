<?php

declare(strict_types=1);

use App\Controllers\Dashboard\NotificationController;
use App\Models\NotificationModel;
use App\Models\UserModel;
use Framework\Core\Response;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * Feature tests for NotificationController.
 *
 * Verifies the four endpoints — index, markRead, markAllRead, unreadCount —
 * against the real database and a real Auth session. The view layer is
 * stubbed so we can inspect the data passed to render() without a templating
 * engine.
 */
beforeEach(function () {
    // Roll back the connection Pest opened — we need to run on the container's
    // shared Database so that auth() (which resolves UserModel through the
    // container) sees rows inserted by this test.
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }

    $this->db = \Framework\Core\App::container()->get(\Framework\Database::class);

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

    $this->controller = new NotificationController($this->notifications);

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
// index()
// ============================================================================

it('renders the notifications index for the authenticated user', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 5, 'post_title' => 'Hi'], 'content');

    $request = makeRequest('/dashboard/notifications', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->index();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($this->mockViewer->capturedTemplate)->toBe('notifications.index')
        ->and($this->mockViewer->capturedData['total'])->toBe(2)
        ->and($this->mockViewer->capturedData['unreadCount'])->toBe(2)
        ->and($this->mockViewer->capturedData['page'])->toBe(1)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(2);
});

it('only shows notifications belonging to the authenticated user', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();

    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'content');
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 99], 'content');

    $request = makeRequest('/dashboard/notifications', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->index();

    expect($this->mockViewer->capturedData['total'])->toBe(1)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(1);
});

it('paginates the index using the ?page query parameter', function () {
    for ($i = 0; $i < 25; $i++) {
        $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => $i], 'content');
    }

    $request = makeRequest('/dashboard/notifications', 'GET', [], ['page' => '2']);
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->index();

    expect($this->mockViewer->capturedData['page'])->toBe(2)
        ->and($this->mockViewer->capturedData['total'])->toBe(25)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(5);
});

it('narrows the list to unread when asked', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 5], 'content');
    $read = (int) $this->notifications->findForUser($this->userId)[0]['id'];
    $this->notifications->markRead($read, $this->userId, 'content');

    $request = makeRequest('/dashboard/notifications', 'GET', [], ['filter' => 'unread']);
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->index();

    expect($this->mockViewer->capturedData['onlyUnread'])->toBeTrue()
        ->and($this->mockViewer->capturedData['total'])->toBe(1)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(1)
        // The count beside the tab stays the real one, not the filtered total.
        ->and($this->mockViewer->capturedData['unreadCount'])->toBe(1);
});

// ============================================================================
// markRead()
// ============================================================================

it('marks the notification as read and returns to the list', function () {
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 42], 'content');
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$id.'/read', 'POST');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->markRead((string) $id);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/dashboard/notifications')
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);
});

it('cannot mark a notification belonging to another user', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($otherUserId, 'blog.invite', ['blog_id' => 1], 'content');
    $foreignId = (int) $this->notifications->findForUser($otherUserId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$foreignId.'/read', 'POST');
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->markRead((string) $foreignId);

    // Owner's notification stays unread.
    expect($this->notifications->unreadCount($otherUserId))->toBe(1);
});

// ============================================================================
// open()
// ============================================================================

it('opens a notification by marking it read and going where it points', function () {
    $this->notifications->create($this->userId, 'comment.reply', [
        'blog_slug' => 'field-notes',
        'post_slug' => 'on-rivers',
        'comment_id' => 77,
    ], 'content');
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$id.'/open', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->open((string) $id);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/blog/field-notes/on-rivers#comment-77')
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);
});

it('sends a notification with nowhere to go back to the list, focused on itself', function () {
    // A moderator's warning is the message; there is no page behind it.
    $this->notifications->create($this->userId, 'moderation.warning', [
        'subject_type' => 'comment',
        'subject_label' => 'a comment',
        'message' => 'Please keep it civil.',
    ], 'content');
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$id.'/open', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->open((string) $id);

    expect($response->getHeader('Location'))->toContain('/dashboard/notifications?focus='.$id)
        ->and($response->getHeader('Location'))->toContain('#notification-'.$id)
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);
});

it('will not open, or read, a notification belonging to someone else', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 9], 'content');
    $foreignId = (int) $this->notifications->findForUser($otherUserId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$foreignId.'/open', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->open((string) $foreignId);

    expect($response->getHeader('Location'))->toContain('/dashboard/notifications')
        ->and($response->getHeader('Location'))->not->toContain('/dashboard/post/9')
        ->and($this->notifications->unreadCount($otherUserId))->toBe(1);
});

it('will not open an id that belongs to one of the other inboxes', function () {
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 9], 'personal');
    $personalId = (int) $this->notifications->findForUser($this->userId, 20, false, 'personal')[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$personalId.'/open', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->open((string) $personalId);

    expect($response->getHeader('Location'))->not->toContain('/dashboard/post/9')
        ->and($this->notifications->unreadCount($this->userId, 'personal'))->toBe(1);
});

// ============================================================================
// panel()
// ============================================================================

it('renders the masthead panel with the latest notifications and the unread count', function () {
    foreach (range(1, 10) as $i) {
        $this->notifications->create($this->userId, 'post.approved', ['post_id' => $i, 'post_title' => 'Post '.$i], 'content');
    }

    $request = makeRequest('/dashboard/notifications/panel', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->panel();

    expect($response->getStatusCode())->toBe(200)
        ->and($this->mockViewer->capturedTemplate)->toBe('partials/public/_notification_panel.lex.php')
        // Eight of the ten, newest first, with everything the row needs to draw.
        ->and($this->mockViewer->capturedData['panelItems'])->toHaveCount(8)
        ->and($this->mockViewer->capturedData['unreadCount'])->toBe(10)
        ->and($this->mockViewer->capturedData['panelItems'][0]['title'])->toContain('Post 10')
        ->and($this->mockViewer->capturedData['panelItems'][0]['isUnread'])->toBeTrue();
});

// ============================================================================
// markAllRead()
// ============================================================================

it('marks every unread notification for the user as read', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 2], 'content');
    $this->notifications->create($this->userId, 'post.published', ['post_id' => 3], 'content');

    $request = makeRequest('/dashboard/notifications/read-all', 'POST');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->markAllRead();

    expect($response->getStatusCode())->toBe(302)
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);

    $flash = $_SESSION['_flash'] ?? [];
    expect($flash['success'] ?? [])->toContain('All notifications marked as read.');
});

it('does not touch another user\'s notifications when marking all read', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'content');
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 2], 'content');

    $request = makeRequest('/dashboard/notifications/read-all', 'POST');
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->markAllRead();

    expect($this->notifications->unreadCount($this->userId))->toBe(0)
        ->and($this->notifications->unreadCount($otherUserId))->toBe(1);
});

// ============================================================================
// unreadCount()
// ============================================================================

it('returns the unread count as JSON', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1], 'content');
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 2], 'content');

    $request = makeRequest('/dashboard/notifications/unread-count', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->unreadCount();

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getHeader('Content-Type'))->toContain('application/json');

    $decoded = json_decode($response->getBody(), true);
    expect($decoded)->toBe(['count' => 2]);
});

it('returns zero when the user has no unread notifications', function () {
    $request = makeRequest('/dashboard/notifications/unread-count', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->unreadCount();

    $decoded = json_decode($response->getBody(), true);
    expect($decoded)->toBe(['count' => 0]);
});
