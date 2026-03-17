<?php

declare(strict_types=1);

use App\Models\UserModel;
use Framework\Database;
use Framework\Validation\DatabaseValidator;

/**
 * Integration test suite for DatabaseValidator rules.
 *
 * Tests validation rules that query the database to check
 * uniqueness constraints and foreign key existence.
 * Uses real database with transaction rollback for test isolation.
 */
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
});

describe('DatabaseValidator Unique Rule', function () {

    /**
     * Verify unique rule passes when value doesn't exist in database.
     */
    test('unique passes when value does not exist', function () {
        $email = faker()->unique()->safeEmail();
        $validator = new DatabaseValidator(['email' => $email], $this->db);
        $validator->rules(['email' => 'unique:users,email']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test unique rule fails when value already exists in database.
     */
    test('unique fails when value exists', function () {
        $email = faker()->unique()->safeEmail();

        // Create existing user
        \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => $email])
            ->create();

        $validator = new DatabaseValidator(['email' => $email], $this->db);
        $validator->rules(['email' => 'unique:users,email']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['email'][0])->toBe('Email is already taken.');
    });

    /**
     * Test unique rule defaults to field name as column when not specified.
     *
     * When column parameter is omitted, use field name ('email') as column.
     */
    test('unique uses field name as column when not specified', function () {
        $email = faker()->unique()->safeEmail();

        \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => $email])
            ->create();

        $validator = new DatabaseValidator(['email' => $email], $this->db);
        $validator->rules(['email' => 'unique:users']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test unique rule passes with exceptId parameter for update scenarios.
     *
     * Allow updating a record without triggering uniqueness violation
     * on its own current value (exclude current record ID).
     */
    test('unique passes with exceptId parameter', function () {
        $email = faker()->unique()->safeEmail();

        // Create user
        $userId = \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => $email])
            ->create();

        // Validate same email but exclude this user's ID
        $validator = new DatabaseValidator(['email' => $email], $this->db);
        $validator->rules(['email' => "unique:users,email,{$userId}"]);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test unique rule fails with exceptId when other record has the value.
     *
     * Verify that excluding one ID doesn't allow taking another record's value.
     */
    test('unique fails with exceptId when other record exists', function () {
        $email1 = faker()->unique()->safeEmail();
        $email2 = faker()->unique()->safeEmail();

        // Create two users
        $user1Id = \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => $email1])
            ->create();

        $user2Id = \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => $email2])
            ->create();

        // Try to update user2 to user1's email (should fail)
        $validator = new DatabaseValidator(['email' => $email1], $this->db);
        $validator->rules(['email' => "unique:users,email,{$user2Id}"]);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify unique rule passes for empty string when not required.
     *
     * Allow empty values - use 'required' rule to enforce presence.
     */
    test('unique passes for empty string when not required', function () {
        $validator = new DatabaseValidator(['email' => ''], $this->db);
        $validator->rules(['email' => 'unique:users,email']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test unique rule passes for null when not required.
     */
    test('unique passes for null when not required', function () {
        $validator = new DatabaseValidator(['email' => null], $this->db);
        $validator->rules(['email' => 'unique:users,email']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test unique rule throws exception for invalid table name.
     *
     * Prevent SQL injection by validating table names against
     * identifier naming rules before query construction.
     */
    test('unique throws exception for invalid table name', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'unique:invalid-table,field']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test unique rule throws exception for invalid column name.
     *
     * Prevent SQL injection by validating column names.
     */
    test('unique throws exception for invalid column name', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'unique:users,invalid-column']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test unique rule behavior with different case variations.
     *
     * Result depends on database collation settings (typically case-insensitive
     * for email fields in MySQL with utf8_general_ci).
     */
    test('unique case sensitivity depends on database collation', function () {
        $email = faker()->unique()->safeEmail();

        \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => strtoupper($email)])
            ->create();

        // Check lowercase version
        $validator = new DatabaseValidator(['email' => strtolower($email)], $this->db);
        $validator->rules(['email' => 'unique:users,email']);

        // Verify it doesn't crash (result depends on DB collation)
        expect($validator->passes())->toBeBool();
    });

});

describe('DatabaseValidator Exists Rule', function () {

    /**
     * Test exists rule passes when value exists in database.
     */
    test('exists passes when value exists', function () {
        // Create user
        $userId = \Tests\Factories\UserFactory::new($this->userModel)->create();

        $validator = new DatabaseValidator(['user_id' => $userId], $this->db);
        $validator->rules(['user_id' => 'exists:users,id']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test exists rule fails when value doesn't exist in database.
     */
    test('exists fails when value does not exist', function () {
        $validator = new DatabaseValidator(['user_id' => 99999], $this->db);
        $validator->rules(['user_id' => 'exists:users,id']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['user_id'][0])->toBe('The selected User id is invalid.');
    });

    /**
     * Test exists rule defaults to 'id' column when not specified.
     *
     * Common convention: foreign keys reference primary key 'id' by default.
     */
    test('exists defaults to id column when not specified', function () {
        $userId = \Tests\Factories\UserFactory::new($this->userModel)->create();

        $validator = new DatabaseValidator(['user_id' => $userId], $this->db);
        $validator->rules(['user_id' => 'exists:users']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test exists rule works with non-id columns.
     *
     * Support validation against unique columns like email or username.
     */
    test('exists works with non-id column', function () {
        $email = faker()->unique()->safeEmail();

        \Tests\Factories\UserFactory::new($this->userModel)
            ->withAttributes(['email' => $email])
            ->create();

        $validator = new DatabaseValidator(['email' => $email], $this->db);
        $validator->rules(['email' => 'exists:users,email']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Verify exists rule passes for empty string when not required.
     *
     * Allow empty values - use 'required' rule to enforce presence.
     */
    test('exists passes for empty string when not required', function () {
        $validator = new DatabaseValidator(['user_id' => ''], $this->db);
        $validator->rules(['user_id' => 'exists:users,id']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test exists rule passes for null when not required.
     */
    test('exists passes for null when not required', function () {
        $validator = new DatabaseValidator(['user_id' => null], $this->db);
        $validator->rules(['user_id' => 'exists:users,id']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test exists rule throws exception for invalid table name.
     *
     * Prevent SQL injection by validating table names.
     */
    test('exists throws exception for invalid table name', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'exists:invalid-table,id']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test exists rule throws exception for invalid column name.
     *
     * Prevent SQL injection by validating column names.
     */
    test('exists throws exception for invalid column name', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'exists:users,invalid-column']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test exists rule validates foreign key relationships.
     *
     * Ensure referenced record exists before creating relationships
     * (e.g., verify user exists before creating their blog).
     */
    test('exists validates foreign key relationship', function () {
        // Create user
        $userId = \Tests\Factories\UserFactory::new($this->userModel)->create();

        // Validate that user_id exists before creating related record
        $validator = new DatabaseValidator(['user_id' => $userId], $this->db);
        $validator->rules(['user_id' => 'exists:users,id']);

        expect($validator->passes())->toBeTrue();
    });

});

describe('DatabaseValidator Combined Rules', function () {

    /**
     * Test combining unique rule with other validation rules.
     *
     * Verify rules execute in order and all must pass for validation success.
     */
    test('it combines unique with other rules', function () {
        $email = faker()->unique()->safeEmail();
        $validator = new DatabaseValidator(['email' => $email], $this->db);
        $validator->rules(['email' => 'required|email|unique:users,email']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test validation stops at first failed rule before checking unique.
     *
     * Avoid unnecessary database queries when earlier rules fail.
     */
    test('it fails on first rule before checking unique', function () {
        $validator = new DatabaseValidator(['email' => 'invalid-email'], $this->db);
        $validator->rules(['email' => 'required|email|unique:users,email']);

        $validator->fails();
        $errors = $validator->errors();

        // Should fail on email format, not reach unique check
        expect($errors['email'][0])->toContain('valid email address');
    });

    /**
     * Test combining exists rule with other validation rules.
     */
    test('it combines exists with other rules', function () {
        $userId = \Tests\Factories\UserFactory::new($this->userModel)->create();

        $validator = new DatabaseValidator(['user_id' => $userId], $this->db);
        $validator->rules(['user_id' => 'required|integer|exists:users,id']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test exists rule fails when combined with type validation.
     *
     * Verify type rules execute before database queries.
     */
    test('it validates type before checking exists', function () {
        $validator = new DatabaseValidator(['user_id' => 'not-a-number'], $this->db);
        $validator->rules(['user_id' => 'required|integer|exists:users,id']);

        $validator->fails();
        $errors = $validator->errors();

        // Should fail on integer validation
        expect($errors['user_id'][0])->toContain('integer');
    });

});

describe('DatabaseValidator SQL Injection Protection', function () {

    /**
     * Test unique rule throws exception for column names with SQL injection attempts.
     *
     * Prevent SQL injection by validating column names against identifier rules.
     */
    test('unique throws exception for column with semicolon', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'unique:users,email;DROP TABLE users']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test unique rule throws exception for column names with special characters.
     */
    test('unique throws exception for column with special chars', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'unique:users,invalid-column!']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test unique rule throws exception for column names with HTML tags.
     */
    test('unique throws exception for column with HTML tags', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'unique:users,<script>alert(1)</script>']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

    /**
     * Test exists rule throws exception for table names with SQL injection attempts.
     *
     * Prevent SQL injection by validating table names.
     */
    test('exists throws exception for table with SQL injection', function () {
        $validator = new DatabaseValidator(['field' => faker()->word()], $this->db);
        $validator->rules(['field' => 'exists:users;DROP TABLE posts,id']);

        expect(fn () => $validator->passes())
            ->toThrow(\InvalidArgumentException::class, 'Invalid table or column name');
    });

});
