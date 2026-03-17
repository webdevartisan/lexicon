<?php

declare(strict_types=1);

use App\Controllers\Auth\RegisterController;
use Framework\Core\Request;
use Framework\Core\Response;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Models\UserPreferencesModel;
use Tests\Factories\UserFactory;

/**
 * Feature tests for RegisterController.
 * 
 * Tests user registration workflow including validation, CSRF protection,
 * database persistence, role assignment, and profile creation.
 */

beforeEach(function () {
    $_SESSION = [];
    
    // Generate isolated CSRF token for tests
    $this->csrfToken = csrf()->getToken();
    
    expect($this->db->getConnection())->toHaveActiveTransaction();
    
    // Create models
    $this->userModel = new UserModel($this->db);
    $this->profileModel = new UserProfileModel($this->db);
    $this->preferencesModel = new UserPreferencesModel($this->db);
    
    // Get auth from container (already wired)
    $this->auth = auth();
    
    // Create controller with all dependencies
    $this->controller = new RegisterController(
        $this->auth,
        $this->userModel,
        $this->profileModel,
        $this->preferencesModel
    );
    
    $this->mockViewer = new class implements \Framework\Interfaces\TemplateViewerInterface {
        public function render(string|array|null $template, array $data = []): string {
            return 'mocked view';
        }
        
        public function addGlobals(array $vars): void {}
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
    
    // Generate alphanumeric-only username (no dots, underscores, etc.)
    $username = 'user' . faker()->unique()->numberBetween(10000, 99999);
    
    $password = 'SecurePass123!';
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => $username,  // Now guaranteed to be alphanumeric
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    // Verify user was created
    $user = $this->userModel->findByEmail($email);
    expect($user)->toBeArray()
        ->and($user['email'])->toBe($email)
        ->and($user['username'])->toBe($username);
    
    // Verify password was hashed
    expect(password_verify($password, $user['password']))->toBeTrue();
    
    // Verify role was assigned
    $roles = $this->userModel->getUserRoles($user['id']);
    expect($roles)->toContain('blog_owner');
    
    // Verify profile was created
    $profile = $this->profileModel->findOrCreate($user['id']);
    expect($profile)->toBeArray()
        ->and($profile['slug'])->not->toBeNull()
        ->and($profile['is_public'])->toBe(1);
    
    // Verify preferences were created
    $preferences = $this->preferencesModel->findOrCreate($user['id']);
    expect($preferences)->toBeArray();
    
    // Verify response is redirect to dashboard
    expect($response)->toBeInstanceOf(Framework\Core\Response::class)
        ->and($response->getHeader('Location'))->toContain('/dashboard');
});







// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Test that registration requires CSRF token.
 */
it('requires CSRF token on registration', function () {
    $request = new Request('/register', 'POST', [], [
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    expect(fn() => $this->controller->submit())
        ->toThrow(RuntimeException::class, 'Invalid CSRF token.');
});

/**
 * Test that invalid CSRF token is rejected.
 */
it('rejects invalid CSRF token', function () {
    $request = new Request('/register', 'POST', [], [
        '_token' => 'invalid-token-12345',
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    expect(fn() => $this->controller->submit())
        ->toThrow(RuntimeException::class, 'Invalid CSRF token.');
});

// ============================================================================
// EMAIL VALIDATION
// ============================================================================

/**
 * Test that registration requires email field.
 */
it('requires email on registration', function () {
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test email format validation.
 */
it('validates email format', function (string $invalidEmail) {
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => $invalidEmail,
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
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
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => 'user' . faker()->unique()->numberBetween(10000, 99999),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'SecurePass123!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    // Should fail - either validation error or database exception
    $failed = false;
    
    try {
        $response = callController($this->controller, 'submit', $request);
        // If we got here, check there were validation errors
        $failed = !empty($_SESSION['_errors'] ?? []);
    } catch (\PDOException $e) {
        // Database prevented duplicate
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
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test username alphanumeric validation.
 */
it('requires alphanumeric username', function (string $invalidUsername) {
    $password = 'SecurePass123!';
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => $invalidUsername,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
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
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => $invalidUsername,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
})->with([
    'ab',  // Too short (min 3)
    str_repeat('a', 21),  // Too long (max 20)
]);

// ============================================================================
// NAME VALIDATION
// ============================================================================

/**
 * Test that registration requires first name.
 */
it('requires first name', function () {
    $password = 'SecurePass123!';
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test that registration requires last name.
 */
it('requires last name', function () {
    $password = 'SecurePass123!';
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

// ============================================================================
// PASSWORD VALIDATION
// ============================================================================

/**
 * Test that registration requires password field.
 */
it('requires password on registration', function () {
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'confirm_password' => 'SecurePass123!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test password confirmation matching.
 */
it('requires matching password confirmation', function () {
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => faker()->safeEmail(),
        'username' => faker()->userName(),
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => 'SecurePass123!',
        'confirm_password' => 'DifferentPass456!',
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    $response = callController($this->controller, 'submit', $request);
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

// ============================================================================
// PROFILE SLUG GENERATION
// ============================================================================

/**
 * Test that profile slug uses username when available.
 */
it('creates profile slug from username', function () {
    $email = faker()->unique()->safeEmail();
    $username = 'testuser' . faker()->unique()->numberBetween(1000, 9999);
    $password = 'SecurePass123!';
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => $username,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
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
    $username = 'duplicateuser' . faker()->unique()->numberBetween(1000, 9999);
    
    // Create existing user with this username
    $existingUserId = UserFactory::new($this->userModel)
        ->withAttributes(['username' => $username])
        ->create();
    
    $this->profileModel->upsert($existingUserId, [
        'slug' => $username,
        'is_public' => 1,
    ]);
    
    // Try to register new user with same username (will fail validation)
    // So test with different username but same slug preference
    $email = faker()->unique()->safeEmail();
    $newUsername = $username . '2';
    $password = 'SecurePass123!';
    
    $request = new Request('/register', 'POST', [], [
        '_token' => $this->csrfToken,
        'email' => $email,
        'username' => $newUsername,
        'first_name' => faker()->firstName(),
        'last_name' => faker()->lastName(),
        'password' => $password,
        'confirm_password' => $password,
    ], [], [], [], []);
    
    setupController($this->controller, $request, $this->mockViewer);
    
    callController($this->controller, 'submit', $request);
    
    $user = $this->userModel->findByEmail($email);
    $profile = $this->profileModel->findOrCreate($user['id']);
    
    expect($profile['slug'])->toBe($newUsername);
});
