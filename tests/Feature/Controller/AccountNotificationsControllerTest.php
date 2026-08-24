<?php

declare(strict_types=1);

use App\Controllers\AccountNotificationsController;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\NotificationPreferenceScope;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * Two invariants live here. First, the save path only writes a toggle it also
 * showed: an unticked box turns off, but a toggle hidden as irrelevant is left
 * untouched at its default rather than silently zeroed. The default test user is
 * reader-only, so notify_comment_replies is the single toggle in play.
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

    $scope = new NotificationPreferenceScope(new BlogModel($this->db));
    $this->controller = new AccountNotificationsController($this->prefs, $scope);

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

test('unticking the reader-visible toggle persists as off across a reload', function () {
    // notify_comment_replies is the only toggle a reader sees; omitting it unticks it.
    $request = makeRequest('/account/notifications/update', 'POST', [
        '_token' => csrf()->getToken(),
        // notify_comment_replies is absent = unticked
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($this->prefs->notificationPreference($this->userId, 'notify_comment_replies'))->toBeFalse();
});

test('saving as a reader leaves irrelevant toggles untouched, not zeroed', function () {
    // A reader never sees these, so an empty submission must not disable them —
    // otherwise a later promotion to author would start with them forced off.
    $request = makeRequest('/account/notifications/update', 'POST', [
        '_token' => csrf()->getToken(),
    ]);
    setupController($this->controller, $request, $this->viewer);

    callController($this->controller, 'update', $request);

    // The single applicable toggle was shown and omitted, so it turns off.
    expect($this->prefs->notificationPreference($this->userId, 'notify_comment_replies'))->toBeFalse();

    // Everything a reader cannot see keeps its schema default of on.
    foreach (['notify_post_status', 'notify_comments_authored', 'notify_review_requests', 'notify_comments_moderation', 'notify_comments_blog', 'notify_role_changes', 'notify_invites'] as $key) {
        expect($this->prefs->notificationPreference($this->userId, $key))->toBeTrue();
    }
});

test('edit renders the notifications view', function () {
    $request = makeRequest('/account/notifications', 'GET');
    setupController($this->controller, $request, $this->viewer);

    $this->controller->edit();

    expect($this->viewer->capturedTemplate)->toBe('public.Account.notifications');
});
