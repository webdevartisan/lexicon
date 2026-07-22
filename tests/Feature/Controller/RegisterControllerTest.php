<?php

declare(strict_types=1);

use App\Controllers\Auth\RegisterController;
use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\NotificationModel;
use App\Models\ReservedSlugModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Services\InvitationService;
use App\Services\MailQueueService;
use App\Services\NotificationService;
use App\Services\UsernameValidationService;
use Framework\Core\Response;
use Tests\Factories\UserFactory;

/**
 * Feature tests for RegisterController.
 *
 * Registration is email + password only: the username is generated from the
 * email local part, new accounts get the reader role, and a validated
 * return_to sends the reader back to the page they came from.
 */
beforeEach(function () {
    $_SESSION = [];
    // clear stale Auth singleton cache from previous test
    auth()->logout();

    $this->csrfToken = csrf()->getToken();

    expect($this->db->getConnection())->toHaveActiveTransaction();

    $this->userModel = new UserModel($this->db);
    $this->profileModel = new UserProfileModel($this->db);
    $this->preferencesModel = new UserPreferencesModel($this->db);
    $this->roleModel = new RoleModel($this->db);
    $this->auth = auth();

    $this->usernameValidator = new UsernameValidationService(
        $this->userModel,
        new ReservedSlugModel($this->db)
    );

    // Invitation plumbing: real models against the test DB, mocked mail queue so
    // registration (and its pending-invite consumption path) enqueues nothing.
    $mailQueue = Mockery::mock(MailQueueService::class);
    $mailQueue->shouldReceive('enqueue')->andReturn(1)->byDefault();

    $invitationModel = new BlogInvitationModel($this->db);
    $invitationService = new InvitationService(
        $invitationModel,
        new BlogModel($this->db),
        $this->userModel,
        new NotificationService(
            new NotificationModel($this->db),
            $this->userModel,
            $this->preferencesModel,
            $mailQueue,
        ),
        $mailQueue,
        $this->preferencesModel
    );

    $this->controller = new RegisterController(
        $this->auth,
        $this->userModel,
        $this->profileModel,
        $this->preferencesModel,
        $this->usernameValidator,
        $invitationModel,
        $invitationService,
        $this->roleModel
    );

    $this->mockViewer = new class() implements \Framework\Interfaces\TemplateViewerInterface
    {
        public function render(string $template, array $data = []): string
        {
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
});

// ============================================================================
// SUCCESSFUL REGISTRATION
// ============================================================================

/**
 * Test successful registration with just email and password.
 *
 * Verifies the complete flow: user creation, generated username, reader role,
 * profile creation, preferences, and auto-login.
 */
it('registers user with email and password only', function () {
    $local = 'reader'.bin2hex(random_bytes(4));
    $email = $local.'@example.test';
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);
    expect($user)->toBeArray()
        ->and($user['email'])->toBe($email)
        ->and($user['username'])->toBe($local);

    expect(password_verify($password, $user['password']))->toBeTrue();

    $roles = $this->userModel->getUserRoles($user['id']);
    expect($roles)->toContain('reader')
        ->and($roles)->not->toContain('blog_owner');

    $profile = $this->profileModel->findOrCreate($user['id']);
    expect($profile)->toBeArray()
        ->and($profile['slug'])->not->toBeNull()
        ->and($profile['is_public'])->toBe(1);

    $preferences = $this->preferencesModel->findOrCreate($user['id']);
    expect($preferences)->toBeArray();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeader('Location'))->toContain('/dashboard');
});

/**
 * Test that a safe return_to sends the new reader back where they came from.
 */
it('redirects to a safe return_to after registration', function () {
    $email = 'reader'.bin2hex(random_bytes(4)).'@example.test';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => 'SecurePass123!',
        'return_to' => '/blog/some-blog/some-post',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeader('Location'))->toContain('/blog/some-blog/some-post');
});

/**
 * Test that external return_to values are ignored (open redirect guard).
 */
it('ignores unsafe return_to values', function (string $unsafe) {
    $email = 'reader'.bin2hex(random_bytes(4)).'@example.test';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => 'SecurePass123!',
        'return_to' => $unsafe,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeader('Location'))->toContain('/dashboard');
})->with([
    'https://evil.example.com/phish',
    '//evil.example.com',
    '/\\evil.example.com',
    'javascript://alert(1)',
]);

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Test that registration requires CSRF token.
 */
it('requires CSRF token on registration', function () {
    $request = makeRequest('/register', 'POST', [
        'email' => faker()->safeEmail(),
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    expect(fn () => $this->controller->submit())
        ->toThrow(RuntimeException::class, 'Invalid CSRF token.');
});

/**
 * Test that invalid CSRF token is rejected.
 */
it('rejects invalid CSRF token', function () {
    $request = makeRequest('/register', 'POST', [
        '_token' => 'invalid-token-12345',
        'email' => faker()->safeEmail(),
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    expect(fn () => $this->controller->submit())
        ->toThrow(RuntimeException::class, 'Invalid CSRF token.');
});

// ============================================================================
// EMAIL VALIDATION
// ============================================================================

/**
 * Test that registration requires email field.
 */
it('requires email on registration', function () {
    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
});

/**
 * Test email format validation.
 */
it('validates email format', function (string $invalidEmail) {
    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $invalidEmail,
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
})->with([
    'invalid-email',
    'not-an-email',
    '@example.com',
    'user@',
]);

/**
 * Test that email must be unique.
 */
it('rejects duplicate email', function () {
    // Random local part instead of faker: unique() only dedupes within this
    // run, and the test DB can hold residue from earlier runs.
    $email = 'dup-'.bin2hex(random_bytes(6)).'@example.test';

    UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->create();

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $failed = false;

    try {
        $response = callController($this->controller, 'submit', $request);
        $failed = !empty($_SESSION['_errors'] ?? []);
    } catch (\PDOException|\RuntimeException $e) {
        // Framework\Database wraps PDO errors in RuntimeException; either way
        // the users.email unique index rejected the duplicate.
        $failed = str_contains($e->getMessage(), 'Duplicate entry');
    }

    expect($failed)->toBeTrue('Duplicate email should be rejected');
});

// ============================================================================
// PASSWORD VALIDATION
// ============================================================================

/**
 * Test that registration requires password field.
 */
it('requires password on registration', function () {
    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
});

// ============================================================================
// USERNAME GENERATION
// ============================================================================

/**
 * Test that the generated username gets a suffix when the local part is taken.
 */
it('suffixes the generated username on collision', function () {
    $local = 'taken'.bin2hex(random_bytes(3));

    UserFactory::new($this->userModel)
        ->withAttributes(['username' => $local])
        ->create();

    $email = $local.'@example.test';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);
    callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);

    expect($user)->toBeArray()
        ->and($user['username'])->not->toBe($local)
        ->and($user['username'])->toStartWith(substr($local, 0, 15))
        ->and(strlen($user['username']))->toBeLessThanOrEqual(20);
});

/**
 * Test that non-alphanumeric characters in the local part are stripped.
 */
it('sanitizes the email local part into the username', function () {
    $suffix = bin2hex(random_bytes(3));
    $email = 'first.last+tag'.$suffix.'@example.test';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);
    callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);

    expect($user)->toBeArray()
        ->and($user['username'])->toMatch('/^[a-z0-9]{3,20}$/');
});

// ============================================================================
// PROFILE SLUG GENERATION
// ============================================================================

/**
 * Test that profile slug uses the generated username when available.
 */
it('creates profile slug from generated username', function () {
    $local = 'slugger'.bin2hex(random_bytes(3));
    $email = $local.'@example.test';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);
    callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);
    $profile = $this->profileModel->findOrCreate($user['id']);

    expect($profile['slug'])->toBe($local);
});
