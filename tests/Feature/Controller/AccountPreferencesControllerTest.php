<?php

declare(strict_types=1);

use App\Controllers\AccountPreferencesController;
use App\Models\PendingEmailChangeModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\AccountZone;
use App\Services\LocaleRegistry;
use App\Services\SessionLocaleSync;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\Session;
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
            new AccountZone($this->prefs, $c->get(Session::class)),
        );
    };

    $this->reportDeviceZone = function (string $zone) {
        $controller = ($this->makeController)();
        $request = makeRequest('/account/device-zone', 'POST', ['zone' => $zone]);
        setupController($controller, $request, $this->viewer);

        return callController($controller, 'rememberDeviceZone', $request);
    };

    $this->switchZone = function (string $zone, string $returnTo) {
        $controller = ($this->makeController)();
        $request = makeRequest('/account/preferences/timezone', 'POST', [
            '_token' => csrf()->getToken(),
            'timezone' => $zone,
            'return_to' => $returnTo,
        ]);
        setupController($controller, $request, $this->viewer);

        return callController($controller, 'updateTimezone', $request);
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

test('choosing Automatic clears the picked zone', function () {
    $this->prefs->upsert($this->userId, ['timezone' => 'Europe/Athens']);
    $controller = ($this->makeController)();

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'timezone' => 'auto',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302)
        ->and($this->prefs->getTimezone($this->userId))->toBeNull();
});

test('the zone the browser reports is kept for the session under its current name', function () {
    $response = ($this->reportDeviceZone)('Asia/Calcutta');

    expect($response->getStatusCode())->toBe(200)
        ->and(App::container()->get(Session::class)->get('device_zone'))->toBe('Asia/Kolkata');
});

test('a reported zone that is not a zone is turned away', function () {
    $response = ($this->reportDeviceZone)('Somewhere/Else');

    expect($response->getStatusCode())->toBe(422)
        ->and(App::container()->get(Session::class)->get('device_zone'))->toBeNull();
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

test('switching to the device zone changes only the zone and returns to the page', function () {
    $this->prefs->upsert($this->userId, ['timezone' => 'Europe/Athens', 'locale' => 'el', 'notify_comments_blog' => 0]);

    $response = ($this->switchZone)('America/New_York', '/en/dashboard/post');

    $saved = $this->prefs->findOrCreate($this->userId);
    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toBe('/en/dashboard/post')
        ->and($saved['timezone'])->toBe('America/New_York')
        ->and($saved['locale'])->toBe('el')
        ->and($this->prefs->notificationPreference($this->userId, 'notify_comments_blog'))->toBeFalse()
        ->and(App::container()->get(Session::class)->get('_flash', [])['success'] ?? [])->toBe([chrome_translate('account.flash.zoneSwitched', ['zone' => 'America/New York'])]);
});

test('an older zone name from the browser is saved under its current name', function () {
    ($this->switchZone)('Asia/Calcutta', '/en/dashboard');

    expect($this->prefs->findOrCreate($this->userId)['timezone'])->toBe('Asia/Kolkata');
});

test('a zone that cannot be matched leaves the account zone alone', function () {
    $this->prefs->upsert($this->userId, ['timezone' => 'Europe/Athens']);

    $response = ($this->switchZone)('Etc/GMT+5', '/en/dashboard');

    expect($response->getHeader('Location'))->toBe('/en/dashboard')
        ->and($this->prefs->findOrCreate($this->userId)['timezone'])->toBe('Europe/Athens')
        ->and(App::container()->get(Session::class)->get('_flash', [])['error'] ?? [])->toBe([chrome_translate('account.flash.zoneUnknown')]);
});

test('the switch only returns to pages on this site', function (string $returnTo) {
    $response = ($this->switchZone)('America/New_York', $returnTo);

    expect($response->getHeader('Location'))->toBe(lurl('/dashboard'));
})->with([
    'another site' => 'https://example.org/en/dashboard',
    'a protocol-relative address' => '//example.org/en/dashboard',
    'nothing' => '',
]);
