<?php

declare(strict_types=1);

use App\Controllers\AccountSecurityController;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\PasswordConfirmRateLimiter;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Tests\Factories\UserFactory;
use Tests\Helpers\ThrottleTestHelper;

/**
 * The password change keeps its two guarantees from the dashboard: the current
 * password is required (now also throttled), and the session id is rotated on
 * success to close session fixation. The last-login stamp is formatted, never
 * compared against PHP's clock.
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
    $this->password = 'password123';
    $email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($this->password, PASSWORD_DEFAULT),
        ])
        ->create();

    expect(auth()->login($email, $this->password))->toBeTrue();

    $this->throttle = new PasswordConfirmRateLimiter(new RateLimiter(ThrottleTestHelper::fakeCache()));
    $c = \Framework\Core\App::container();
    $this->controller = new AccountSecurityController(
        $this->users,
        new UserPreferencesModel($this->db),
        $this->throttle,
    );

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

test('a correct current password changes the hash and rotates the session', function () {
    $request = makeRequest('/account/security/password', 'POST', [
        '_token' => csrf()->getToken(),
        'current_password' => $this->password,
        'new_password' => 'brandnew456',
        'new_password_confirm' => 'brandnew456',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = callController($this->controller, 'updatePassword', $request);

    expect($response->getStatusCode())->toBe(302);
    expect($response->getHeader('Location'))->toContain('/account/security');
    // The new password now verifies; the old one no longer does.
    expect($this->users->verifyPassword($this->userId, 'brandnew456'))->toBeTrue();
    expect($this->users->verifyPassword($this->userId, $this->password))->toBeFalse();

    // The success path calls session->regenerate() to close session fixation.
    $source = file_get_contents(ROOT_PATH.'/src/App/Controllers/AccountSecurityController.php');
    expect($source)->toContain('$this->session->regenerate()');
});

test('a wrong current password changes nothing and is recorded by the throttle', function () {
    $request = makeRequest('/account/security/password', 'POST', [
        '_token' => csrf()->getToken(),
        'current_password' => 'not-my-password',
        'new_password' => 'brandnew456',
        'new_password_confirm' => 'brandnew456',
    ]);
    setupController($this->controller, $request, $this->viewer);

    callController($this->controller, 'updatePassword', $request);

    // Password unchanged, and the throttle counted the miss.
    expect($this->users->verifyPassword($this->userId, $this->password))->toBeTrue();
    expect($this->users->verifyPassword($this->userId, 'brandnew456'))->toBeFalse();
});

test('once throttled, even the correct current password is refused', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->throttle->hit($this->userId);
    }

    $request = makeRequest('/account/security/password', 'POST', [
        '_token' => csrf()->getToken(),
        'current_password' => $this->password, // correct, but throttled
        'new_password' => 'brandnew456',
        'new_password_confirm' => 'brandnew456',
    ]);
    setupController($this->controller, $request, $this->viewer);

    callController($this->controller, 'updatePassword', $request);

    expect($this->users->verifyPassword($this->userId, $this->password))->toBeTrue();
    expect($this->users->verifyPassword($this->userId, 'brandnew456'))->toBeFalse();
});

test('the last-login stamp is formatted, never compared against the clock', function () {
    $source = file_get_contents(ROOT_PATH.'/src/App/Controllers/AccountSecurityController.php');
    // No arithmetic or comparison against time() on last_login: DB is GMT+3,
    // PHP is UTC, so a comparison would be three hours out.
    expect($source)->not->toContain('time()');
    expect($source)->toContain("->format('M j, Y H:i')");
});
