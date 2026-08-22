<?php

declare(strict_types=1);

use App\Controllers\AccountPreferencesController;
use App\Mail\EmailChangedMail;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\DisplayNameService;
use App\Services\LocaleRegistry;
use App\Services\MailQueueService;
use App\Services\PasswordConfirmRateLimiter;
use App\Services\SessionLocaleSync;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;
use Tests\Helpers\ThrottleTestHelper;

/**
 * Email is a credential here: the change is gated behind the current password,
 * that check is throttled, nothing mutates on a wrong password, and the old
 * address is notified on a successful change. Real database and Auth session.
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
    $this->password = 'password123';
    $this->email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $this->email,
            'password' => password_hash($this->password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($this->email, $this->password))->toBeTrue();

    $this->throttle = new PasswordConfirmRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));

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

    $this->makeController = function ($mailQueue) {
        $c = \Framework\Core\App::container();

        return new AccountPreferencesController(
            $this->users,
            $this->prefs,
            $c->get(DisplayNameService::class),
            $c->get(LocaleRegistry::class),
            $c->get(SessionLocaleSync::class),
            $this->throttle,
            $mailQueue,
        );
    };
});

afterEach(function () {
    $_SESSION = [];
    auth()->logout();
    Mockery::close();
});

test('an email change with the wrong password mutates nothing', function () {
    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => 'attacker@evil.test',
        'current_password' => 'wrong-password',
        'timezone' => 'Europe/Athens',
        'display_name' => 'name',
        'default_visibility' => 'private',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    $fresh = $this->users->findById($this->userId);
    // Nothing changed: not the email, and not the other fields in the same post.
    expect($fresh['email'])->toBe($this->email);
    $prefs = $this->prefs->findOrCreate($this->userId);
    expect($prefs['timezone'] ?? null)->not->toBe('Europe/Athens');
});

test('an email change with the correct password succeeds and notifies the old address', function () {
    $newEmail = faker()->unique()->safeEmail();
    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldReceive('enqueue')
        ->once()
        ->with(Mockery::type(EmailChangedMail::class), 'account', $this->userId)
        ->andReturn(1);
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => $newEmail,
        'current_password' => $this->password,
        // in: rules reject absent fields, so the form always posts all of them.
        'display_name' => 'username',
        'default_visibility' => 'public',
        'timezone' => 'UTC',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($this->users->findById($this->userId)['email'])->toBe($newEmail);
});

test('a preferences-only save needs no password and queues no mail', function () {
    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => $this->email, // unchanged
        'timezone' => 'Europe/Athens',
        'display_name' => 'username',
        'default_visibility' => 'unlisted',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    $response = callController($controller, 'update', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($this->prefs->findOrCreate($this->userId)['timezone'])->toBe('Europe/Athens');
});

test('once throttled, even the correct password cannot change the email', function () {
    // Spend the window.
    for ($i = 0; $i < 5; $i++) {
        $this->throttle->hit($this->userId);
    }

    $mail = Mockery::mock(MailQueueService::class);
    $mail->shouldNotReceive('enqueue');
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => faker()->unique()->safeEmail(),
        'current_password' => $this->password, // correct, but throttled
    ]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'update', $request);

    expect($this->users->findById($this->userId)['email'])->toBe($this->email);
});

test('changing the interface language moves the redirect to that locale', function () {
    $mail = Mockery::mock(MailQueueService::class);
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => $this->email, // unchanged, so no password gate
        'display_name' => 'username',
        'default_visibility' => 'public',
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

    $mail = Mockery::mock(MailQueueService::class);
    $controller = ($this->makeController)($mail);

    $request = makeRequest('/account/preferences/update', 'POST', [
        '_token' => csrf()->getToken(),
        'email' => $this->email,
        'timezone' => 'UTC',
        'display_name' => 'username',
        'default_visibility' => 'public',
        'locale' => 'auto',
    ]);
    setupController($controller, $request, $this->viewer);

    callController($controller, 'update', $request);

    // The preferences save must not have flipped the notification toggle back on.
    expect($this->prefs->notificationPreference($this->userId, 'notify_comments_blog'))->toBeFalse();
});
