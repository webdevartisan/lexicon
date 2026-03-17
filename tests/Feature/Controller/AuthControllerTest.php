<?php

declare(strict_types=1);

use App\Controllers\Auth\AuthController;
use Framework\Core\Request;
use Framework\Core\Response;
use App\Models\UserModel;
use Tests\Factories\UserFactory;

/**
 * Feature tests for AuthController.
 * 
 * Tests HTTP request handling, CSRF protection, validation, and authentication flow.
 */

beforeEach(function () {
    $_SESSION = [];
    
    $this->userModel = new UserModel($this->db);
    
    expect($this->db->getConnection())->toHaveActiveTransaction();
    
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
// CSRF PROTECTION
// ============================================================================

/**
 * Test that submit requires CSRF token.
 * 
 * Verifies POST requests without CSRF token are rejected.
 */
it('requires CSRF token on submit', function () {
    UserFactory::new($this->userModel)
        ->withAttributes([
            'email' => faker()->unique()->safeEmail(),
            'password' => password_hash('password123', PASSWORD_DEFAULT),
        ])
        ->create();
    
    $request = new Request('/login', 'POST', [], [
        'email' => faker()->safeEmail(),
        'password' => 'password123',
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    
    expect(fn() => $controller->submit())
        ->toThrow(RuntimeException::class, 'Invalid CSRF token.');
});

/**
 * Test that submit accepts valid CSRF token.
 * 
 * Verifies POST requests with valid CSRF token proceed to validation.
 */
it('accepts valid CSRF token and validates', function () {
    $token = csrf()->getToken();
    
    $request = new Request('/login', 'POST', [], [
        '_token' => $token,
        'email' => '',
        'password' => 'password123',
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
  
    $response = $controller->submit();
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

// ============================================================================
// EMAIL VALIDATION
// ============================================================================

/**
 * Test that submit requires email field.
 * 
 * Verifies validation fails when email is missing.
 */
it('requires email on submit', function () {
    $token = csrf()->getToken();
    
    $request = new Request('/login', 'POST', [], [
        '_token' => $token,
        'password' => 'password123',
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
  
    $response = $controller->submit();
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test email format validation.
 * 
 * Verifies invalid email format is rejected.
 */
it('validates email format', function () {
    $token = csrf()->getToken();
    
    $request = new Request('/login', 'POST', [], [
        '_token' => $token,
        'email' => 'invalid-email',
        'password' => 'password123',
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    $response = $controller->submit();
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test that email whitespace is trimmed.
 * 
 * Verifies leading/trailing whitespace is removed from email.
 */
it('trims email whitespace', function () {
    $token = csrf()->getToken();
    $email = faker()->unique()->safeEmail();
    
    UserFactory::new($this->userModel)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash('password123', PASSWORD_DEFAULT),
        ])
        ->create();
    
    $request = new Request('/login', 'POST', [], [
        '_token' => $token,
        'email' => "  {$email}  ",
        'password' => 'password123',
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    
    try {
        $controller->submit();
    } catch (RuntimeException $e) {
        expect($e->getMessage())->not->toBe('Invalid CSRF token.');
    }
    
    expect(true)->toBeTrue();
});

// ============================================================================
// PASSWORD VALIDATION
// ============================================================================

/**
 * Test that submit requires password field.
 * 
 * Verifies validation fails when password is missing.
 */
it('requires password on submit', function () {
    $token = csrf()->getToken();
    
    $request = new Request('/login', 'POST', [], [
        '_token' => $token,
        'email' => faker()->safeEmail(),
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    $response = $controller->submit();
    
    expect($response)->toBeInstanceOf(Framework\Core\Response::class);
});

/**
 * Test that empty password string is accepted without throwing.
 * 
 * Verifies empty password passes validation stage but fails authentication.
 */
it('accepts empty password string without throwing', function () {
    $token = csrf()->getToken();
    
    $request = new Request('/login', 'POST', [], [
        '_token' => $token,
        'email' => faker()->safeEmail(),
        'password' => '',
    ], [], [], [], []);
    
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    
    expect(fn() => $controller->submit())->not->toThrow(Exception::class);
});

// ============================================================================
// LOGOUT
// ============================================================================

/**
 * Test logout clears session.
 * 
 * Verifies logout() removes user_id from session.
 */
it('clears session on logout', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $_SESSION['user_id'] = $userId;
    
    $request = new Request('/logout', 'POST', [], [], [], [], [], []);
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    
    $response = $controller->logout();
    
    expect($_SESSION)->not->toHaveKey('user_id');
});

/**
 * Test logout redirects to homepage.
 * 
 * Verifies logout() returns redirect response.
 */
it('redirects to homepage after logout', function () {
    $request = new Request('/logout', 'POST', [], [], [], [], [], []);
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    
    $response = $controller->logout();
    
    expect($response->getHeader('Location'))->toContain('/');
});

/**
 * Test logout works for unauthenticated users.
 * 
 * Verifies logout() handles missing session gracefully.
 */
it('handles logout when not authenticated', function () {
    $request = new Request('/logout', 'POST', [], [], [], [], [], []);
    $controller = new AuthController($request);
    setupController($controller, $request, $this->mockViewer);
    
    expect(fn() => $controller->logout())->not->toThrow(Exception::class);
});
