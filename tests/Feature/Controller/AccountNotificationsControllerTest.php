<?php

declare(strict_types=1);

use App\Controllers\AccountNotificationsController;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * The regression the NOTIFY_KEYS loop prevents: because the upsert leaves absent
 * columns unchanged, a toggle can only be turned off by writing 0 for it. Uncheck
 * a box, save, and it must stay off.
 */
beforeEach(function () {
    if ($this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->rollBack();
    }
    $this->db = \Framework\Core\App::container()->get(\Framework\Database::class);
    if (!$this->db->getConnection()->inTransaction()) {
        $this->db->getConnection()->beginTransaction();
    }

    $_SESSION = [];
    auth()->logout();

    $users = new UserModel($this->db);
    $this->prefs = new UserPreferencesModel($this->db);
    $password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($users)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($email, $password))->toBeTrue();

    $this->controller = new AccountNotificationsController($this->prefs);

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public ?string $capturedTemplate = null;

        public function render(string $template, array $data = []): string
        {
            $this->capturedTemplate = $template;

            return 'mocked';
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

test('unticking a notification persists as off across a reload', function () {
    // It starts on (schema default), so send a form that omits it.
    $request = makeRequest('/account/notifications/update', 'POST', [
        '_token' => csrf()->getToken(),
        'notify_comment_replies' => '1', // some other box stays ticked
        // notify_comments_blog is absent = unticked
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($this->prefs->notificationPreference($this->userId, 'notify_comments_blog'))->toBeFalse();
    expect($this->prefs->notificationPreference($this->userId, 'notify_comment_replies'))->toBeTrue();
});

test('an empty submission writes every toggle off', function () {
    $request = makeRequest('/account/notifications/update', 'POST', [
        '_token' => csrf()->getToken(),
    ]);
    setupController($this->controller, $request, $this->viewer);

    callController($this->controller, 'update', $request);

    foreach (UserPreferencesModel::NOTIFY_KEYS as $key) {
        expect($this->prefs->notificationPreference($this->userId, $key))->toBeFalse();
    }
});

test('edit renders the notifications view', function () {
    $request = makeRequest('/account/notifications', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $this->controller->edit();

    expect($this->viewer->capturedTemplate)->toBe('public.Account.notifications');
});
