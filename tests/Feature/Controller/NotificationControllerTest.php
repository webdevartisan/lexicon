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
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 5, 'post_title' => 'Hi']);

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

    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 99]);

    $request = makeRequest('/dashboard/notifications', 'GET');
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->index();

    expect($this->mockViewer->capturedData['total'])->toBe(1)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(1);
});

it('paginates the index using the ?page query parameter', function () {
    for ($i = 0; $i < 25; $i++) {
        $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => $i]);
    }

    $request = makeRequest('/dashboard/notifications', 'GET', [], ['page' => '2']);
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->index();

    expect($this->mockViewer->capturedData['page'])->toBe(2)
        ->and($this->mockViewer->capturedData['total'])->toBe(25)
        ->and($this->mockViewer->capturedData['notificationRows'])->toHaveCount(5);
});

// ============================================================================
// markRead()
// ============================================================================

it('marks the notification as read and redirects to a local target', function () {
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 42]);
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    $target = '/dashboard/post/42/edit';
    $request = makeRequest('/dashboard/notifications/'.$id.'/read', 'POST', [
        'target' => $target,
    ]);
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->markRead((string) $id);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain($target)
        ->and($this->notifications->unreadCount($this->userId))->toBe(0);
});

it('rejects a non-local target and falls back to the notifications list', function () {
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 42]);
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    foreach (['https://evil.example/x', '//evil.example/x', 'javascript:alert(1)'] as $bad) {
        $request = makeRequest('/dashboard/notifications/'.$id.'/read', 'POST', [
            'target' => $bad,
        ]);
        setupController($this->controller, $request, $this->mockViewer);

        $response = $this->controller->markRead((string) $id);

        expect($response->getStatusCode())->toBe(302)
            ->and($response->getHeader('Location'))->toContain('/dashboard/notifications')
            ->and($response->getHeader('Location'))->not->toContain('evil.example')
            ->and($response->getHeader('Location'))->not->toContain('javascript');
    }
});

it('redirects to the notifications list when no target is provided', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $id = (int) $this->notifications->findForUser($this->userId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$id.'/read', 'POST');
    setupController($this->controller, $request, $this->mockViewer);

    $response = $this->controller->markRead((string) $id);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/dashboard/notifications');
});

it('cannot mark a notification belonging to another user', function () {
    $otherUserId = UserFactory::new($this->userModel)->create();
    $this->notifications->create($otherUserId, 'blog.invite', ['blog_id' => 1]);
    $foreignId = (int) $this->notifications->findForUser($otherUserId)[0]['id'];

    $request = makeRequest('/dashboard/notifications/'.$foreignId.'/read', 'POST', [
        'target' => '/dashboard/notifications',
    ]);
    setupController($this->controller, $request, $this->mockViewer);

    $this->controller->markRead((string) $foreignId);

    // Owner's notification stays unread.
    expect($this->notifications->unreadCount($otherUserId))->toBe(1);
});

// ============================================================================
// markAllRead()
// ============================================================================

it('marks every unread notification for the user as read', function () {
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 2]);
    $this->notifications->create($this->userId, 'post.published', ['post_id' => 3]);

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
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->notifications->create($otherUserId, 'post.approved', ['post_id' => 2]);

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
    $this->notifications->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->notifications->create($this->userId, 'post.approved', ['post_id' => 2]);

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
