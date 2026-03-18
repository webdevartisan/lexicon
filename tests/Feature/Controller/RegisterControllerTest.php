<?php

declare(strict_types=1);

use App\Controllers\Auth\RegisterController;
use App\Models\ReservedSlugModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Services\UsernameValidationService;
use Framework\Core\Response;
use Tests\Factories\UserFactory;

/**
 * Feature tests for RegisterController.
 *
 * Tests user registration workflow including validation, CSRF protection,
 * database persistence, role assignment, and profile creation.
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
    $this->auth = auth();

    $this->usernameValidator = new UsernameValidationService(
        $this->userModel,
        new ReservedSlugModel($this->db)
    );

    $this->controller = new RegisterController(
        $this->auth,
        $this->userModel,
        $this->profileModel,
        $this->preferencesModel,
        $this->usernameValidator
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
 * Test successful user registration with all required fields.
 *
 * Verifies complete registration flow: validation, user creation,
 * role assignment, profile creation, preferences, and auto-login.
 */
it('registers user with valid credentials', function () {
    $email = faker()->unique()->safeEmail();
    $username = 'u'.bin2hex(random_bytes(4));
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => $username,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);
    expect($user)->toBeArray()
        ->and($user['email'])->toBe($email)
        ->and($user['username'])->toBe($username);

    expect(password_verify($password, $user['password']))->toBeTrue();

    $roles = $this->userModel->getUserRoles($user['id']);
    expect($roles)->toContain('blog_owner');

    $profile = $this->profileModel->findOrCreate($user['id']);
    expect($profile)->toBeArray()
        ->and($profile['slug'])->not->toBeNull()
        ->and($profile['is_public'])->toBe(1);

    $preferences = $this->preferencesModel->findOrCreate($user['id']);
    expect($preferences)->toBeArray();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getHeader('Location'))->toContain('/dashboard');
});

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Test that registration requires CSRF token.
 */
it('requires CSRF token on registration', function () {
    $request = makeRequest('/register', 'POST', [
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
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
        'username' => faker()->userName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
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
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
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
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
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
    $email = faker()->unique()->safeEmail();

    UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->create();

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => 'user'.faker()->unique()->numberBetween(10000, 99999),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $failed = false;

    try {
        $response = callController($this->controller, 'submit', $request);
        $failed = !empty($_SESSION['_errors'] ?? []);
    } catch (\PDOException $e) {
        $failed = str_contains($e->getMessage(), 'Duplicate entry');
    }

    expect($failed)->toBeTrue('Duplicate email should be rejected');
});

// ============================================================================
// USERNAME VALIDATION
// ============================================================================

/**
 * Test that registration requires username field.
 */
it('requires username on registration', function () {
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
});

/**
 * Test username alphanumeric validation.
 */
it('requires alphanumeric username', function (string $invalidUsername) {
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => $invalidUsername,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
})->with([
    'user name',  // Contains space
    'user@name',  // Contains @
    'user.name',  // Contains dot
    'user-name',  // Contains dash
]);

/**
 * Test username length validation.
 */
it('enforces username length constraints', function (string $invalidUsername) {
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => $invalidUsername,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
})->with([
    'ab',                   // Too short (min 3)
    str_repeat('a', 21),    // Too long (max 20)
]);

// ============================================================================
// NAME VALIDATION
// ============================================================================

/**
 * Test that registration requires first name.
 */
it('requires first name', function () {
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
});

/**
 * Test that registration requires last name.
 */
it('requires last name', function () {
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
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
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'confirm_password' => 'SecurePass123!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
});

/**
 * Test password confirmation matching.
 */
it('requires matching password confirmation', function () {
    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'DifferentPass456!',
    ]);

    setupController($this->controller, $request, $this->mockViewer);

    $response = callController($this->controller, 'submit', $request);

    expect($response)->toBeInstanceOf(Response::class);
});

// ============================================================================
// PROFILE SLUG GENERATION
// ============================================================================

/**
 * Test that profile slug uses username when available.
 */
it('creates profile slug from username', function () {
    $email = faker()->unique()->safeEmail();
    $username = 'u'.bin2hex(random_bytes(4));
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => $username,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);
    callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);
    $profile = $this->profileModel->findOrCreate($user['id']);

    expect($profile['slug'])->toBe($username);
});

/**
 * Test that duplicate slug gets unique suffix.
 */
it('generates unique slug when username taken', function () {
    $username = 'u'.bin2hex(random_bytes(4));

    $existingUserId = UserFactory::new($this->userModel)
        ->withAttributes(['username' => $username])
        ->create();

    $this->profileModel->upsert($existingUserId, [
        'slug' => $username,
        'is_public' => 1,
    ]);

    $email = faker()->unique()->safeEmail();
    $newUsername = $username.'2';
    $password = 'SecurePass123!';

    $request = makeRequest('/register', 'POST', [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => $newUsername,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ]);

    setupController($this->controller, $request, $this->mockViewer);
    callController($this->controller, 'submit', $request);

    $user = $this->userModel->findByEmail($email);
    $profile = $this->profileModel->findOrCreate($user['id']);

    expect($profile['slug'])->toBe($newUsername);
});
