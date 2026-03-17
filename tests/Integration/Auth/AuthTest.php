<?php

declare(strict_types=1);

use App\Auth;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use Framework\Session;
use Tests\Factories\UserFactory;

/**
 * Integration tests for Auth service.
 *
 * Tests authentication, session management, and user identity verification.
 */
beforeEach(function () {
    $this->session = new Session();
    $this->userModel = new UserModel($this->db);
    $this->profileModel = new UserProfileModel($this->db);

    $this->auth = new Auth($this->session, $this->userModel, $this->profileModel);

    expect($this->db->getConnection())->toHaveActiveTransaction();
});

afterEach(function () {
    $_SESSION = [];
});

// ============================================================================
// AUTHENTICATION
// ============================================================================

/**
 * Test successful authentication with valid credentials.
 *
 * Verifies login() authenticates user and establishes session.
 */
it('authenticates with valid credentials', function () {
    $password = faker()->password(12);
    $email = faker()->unique()->safeEmail();

    $userId = UserFactory::new($this->userModel)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ])
        ->create();

    $result = $this->auth->login($email, $password);

    expect($result)->toBeTrue()
        ->and($this->auth->check())->toBeTrue();
});

/**
 * Test authentication fails with invalid password.
 *
 * Verifies login() rejects incorrect password.
 */
it('rejects invalid password', function () {
    $correctPassword = faker()->password(12);
    $wrongPassword = faker()->password(12);
    $email = faker()->unique()->safeEmail();

    UserFactory::new($this->userModel)
        ->withAttributes([
            'email' => $email,
            'password' => password_hash($correctPassword, PASSWORD_DEFAULT),
        ])
        ->create();

    $result = $this->auth->login($email, $wrongPassword);

    expect($result)->toBeFalse()
        ->and($this->auth->check())->toBeFalse();
});

/**
 * Test authentication fails for non-existent user.
 *
 * Verifies login() rejects unknown email addresses.
 */
it('rejects non-existent user', function () {
    $email = faker()->unique()->safeEmail();
    $password = faker()->password(12);

    $result = $this->auth->login($email, $password);

    expect($result)->toBeFalse();
});

// ============================================================================
// USER IDENTITY
// ============================================================================

/**
 * Test retrieving authenticated user data.
 *
 * Verifies user() returns current user information from session.
 */
it('returns authenticated user data', function () {
    $email = faker()->unique()->safeEmail();

    $userId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => $email])
        ->create();

    $_SESSION['user_id'] = $userId;

    $result = $this->auth->user();

    expect($result)->toBeArray()
        ->and($result['email'])->toBe($email);
});

/**
 * Test checking authentication status.
 *
 * Verifies check() correctly identifies authenticated and unauthenticated states.
 */
it('checks if user is authenticated', function () {
    expect($this->auth->check())->toBeFalse();

    $userId = UserFactory::new($this->userModel)->create();
    $_SESSION['user_id'] = $userId;

    expect($this->auth->check())->toBeTrue();
});

// ============================================================================
// LOGOUT
// ============================================================================

/**
 * Test logout functionality.
 *
 * Verifies logout() clears session and unauthenticates user.
 */
it('logs out and clears session', function () {
    $userId = UserFactory::new($this->userModel)->create();
    $_SESSION['user_id'] = $userId;

    expect($this->auth->check())->toBeTrue();

    $this->auth->logout();

    expect($this->auth->check())->toBeFalse();
});
