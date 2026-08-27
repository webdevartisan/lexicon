<?php

declare(strict_types=1);

use App\Controllers\AccountPreferencesController;
use App\Models\PendingEmailChangeModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\LocaleRegistry;
use App\Services\SessionLocaleSync;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;

/**
 * Preferences now owns only the interface settings. The sign-in address is
 * displayed here but changed through AccountEmailController, so this form must
 * not be able to touch users.email however it is posted to.
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

    $this->users = new UserModel($this->db);
    $this->prefs = new UserPreferencesModel($this->db);
    $this->pending = new PendingEmailChangeModel($this->db);
    $this->password = 'password123';
    $this->email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $this->email,
            'password' => password_hash($this->password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($this->email, $this->password))->toBeTrue();

    $this->viewer = new class() implements TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
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

    $this->makeController = function () {
        $c = \Framework\Core\App::container();

        return new AccountPreferencesController(
            $this->users,
            $this->prefs,
            $this->pending,
            $c->get(LocaleRegistry::class),
            $c->get(SessionLocaleSync::class),
        );
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
    Mockery::close();
});

test('saving preferences stores the timezone', function () {
    $controller = ($this->makeController)();

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'timezone' => 'Europe/Athens',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->prefs->findOrCreate($this->userId)['timezone'])->toBe('Europe/Athens');
});

test('the preferences form cannot change the email even when one is posted', function () {
    $controller = ($this->makeController)();

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => 'attacker@evil.test',
        'timezone' => 'UTC',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'update', $request);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email);
});

test('changing the interface language moves the redirect to that locale', function () {
    $controller = ($this->makeController)();

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'timezone' => 'UTC',
        'locale' => 'el',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'update', $request);

    // The choice has to move the URL, or the redirect lands on the old locale
    // and the language switch looks like it did nothing.
    expect($response->getHeader('Location'))->toContain('/el/account/preferences');
});

test('saving preferences does not disturb notification toggles', function () {
    // Turn a notification off first.
    $this->prefs->upsert($this->userId, ['notify_comments_blog' => 0]);

    $controller = ($this->makeController)();

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'timezone' => 'UTC',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'update', $request);

    expect($this->prefs->notificationPreference($this->userId, 'notify_comments_blog'))->toBeFalse();
});

test('saving preferences leaves the display-name preference alone', function () {
    // Owned by the profile form now; a partial upsert here must not reset it.
    $this->prefs->upsert($this->userId, ['display_name_preference' => 'name']);

    $controller = ($this->makeController)();

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'timezone' => 'UTC',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'update', $request);

    expect($this->prefs->findOrCreate($this->userId)['display_name_preference'])->toBe('name');
});
