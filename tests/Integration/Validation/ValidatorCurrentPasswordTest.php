<?php

declare(strict_types=1);

use App\Models\UserModel;
use Framework\Database;
use Framework\Validation\Validator;

/**
 * Integration test suite for Validator current_password rule.
 *
 * Tests password verification against authenticated user's stored password hash.
 * Uses real database with transaction rollback for test isolation.
 */
describe('Validator Current Password Rule', function () {

    beforeEach(function () {
        $this->db = \Framework\Core\App::container()->get(Database::class);
        $this->userModel = new UserModel($this->db);

        // Start transaction for test isolation
        $this->db->getConnection()->beginTransaction();
    });

    afterEach(function () {
        // Only rollback if transaction is still active
        if ($this->db->getConnection()->inTransaction()) {
            $this->db->getConnection()->rollBack();
        }

        // Clear session
        unset($_SESSION['user_id']);
    });

    /**
     * Verify current_password rule passes when provided password matches authenticated user's hash.
     *
     * Use password_verify for constant-time comparison to prevent timing attacks.
     */
    test('current_password passes for correct password', function () {
        // Create test user with known password
        $plainPassword = faker()->password(12, 20);
        $userId = \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes([
                'email' => faker()->unique()->safeEmail(),
                'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            ])
            ->create();

        // Authenticate user
        $_SESSION['user_id'] = $userId;

        $validator = new Validator(['current_password' => $plainPassword]);
        $validator->rules(['current_password' => 'current_password']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test current_password rule fails when provided password doesn't match user's hash.
     */
    test('current_password fails for incorrect password', function () {
        // Create test user with known password
        $correctPassword = faker()->password(12, 20);
        $wrongPassword = faker()->password(12, 20);

        $userId = \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes([
                'email' => faker()->unique()->safeEmail(),
                'password' => password_hash($correctPassword, PASSWORD_DEFAULT),
            ])
            ->create();

        // Authenticate user
        $_SESSION['user_id'] = $userId;

        $validator = new Validator(['current_password' => $wrongPassword]);
        $validator->rules(['current_password' => 'current_password']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify current_password rule fails when user is not authenticated.
     *
     * Prevent password verification attempts without valid session.
     */
    test('current_password fails when not authenticated', function () {
        // Ensure no authenticated user
        unset($_SESSION['user_id']);

        $validator = new Validator(['current_password' => faker()->password()]);
        $validator->rules(['current_password' => 'current_password']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test current_password rule fails when session user_id is invalid.
     *
     * Prevent verification when user_id doesn't exist in database.
     */
    test('current_password fails for non-existent user', function () {
        // Set invalid user ID
        $_SESSION['user_id'] = 999999;

        $validator = new Validator(['current_password' => faker()->password()]);
        $validator->rules(['current_password' => 'current_password']);

        expect($validator->fails())->toBeTrue();
    });

});
