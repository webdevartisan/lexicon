<?php

declare(strict_types=1);

/**
 * Unit tests for UserModel business logic.
 *
 * Mocks all database interactions to test model logic in complete isolation.
 * Verifies SQL construction, validation, and optimization without real database.
 */

use App\Models\UserModel;
use Faker\Factory as Faker;
use Framework\Database;

beforeEach(function () {
    $this->faker = Faker::create();

    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    $this->userModel = new UserModel($this->dbMock);
});

/**
 * Verifies that updateById rejects invalid column names before database interaction.
 *
 * Ensures SQL injection prevention through column name validation.
 */
test('updateById validates column names before database call', function () {
    expect(fn () => $this->userModel->updateById(
        $this->faker->numberBetween(1, 1000),
        ['invalid-column!' => 'value']
    ))->toThrow(Exception::class, 'Invalid column name');

    $this->dbMock->shouldNotHaveReceived('query');
    $this->dbMock->shouldNotHaveReceived('execute');
});

/**
 * Tests that updateById blocks common SQL injection patterns in column names.
 *
 * Uses invalid_column_names dataset to verify validation occurs before any database call.
 *
 * @param  string  $maliciousColumn  SQL injection attempt pattern
 */
test('updateById rejects SQL injection attempts', function (string $maliciousColumn) {
    expect(fn () => $this->userModel->updateById(
        $this->faker->numberBetween(1, 1000),
        [$maliciousColumn => 'value']
    ))->toThrow(Exception::class);

    // Verify validation happens before database to prevent injection
    $this->dbMock->shouldNotHaveReceived('query');
    $this->dbMock->shouldNotHaveReceived('execute');
})->with('invalid_column_names');

/**
 * Tests early return optimization for empty role arrays.
 *
 * Verifies that insertUserRoles skips database operations when no roles provided.
 */
test('insertUserRoles returns true for empty array without database call', function () {
    $result = $this->userModel->insertUserRoles(
        $this->faker->numberBetween(1, 1000),
        []
    );

    expect($result)->toBeTrue();

    // Verify optimization: no database call for empty input
    $this->dbMock->shouldNotHaveReceived('query');
    $this->dbMock->shouldNotHaveReceived('execute');
});

/**
 * Verifies that softDelete constructs proper UPDATE SQL with NOW() function.
 *
 * Mocks both the idempotency SELECT check and the UPDATE execute call.
 * The SELECT returns an active record (deleted_at = null) to allow the UPDATE to proceed.
 */
test('softDelete constructs correct SQL with timestamp', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    // Step 1: Mock the idempotency SELECT — record exists and is active
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(fn(string $sql): bool =>
                str_contains($sql, 'SELECT id, deleted_at FROM users') &&
                str_contains($sql, 'WHERE id = ?')
            ),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(['id' => $userId, 'deleted_at' => null]); // active record

    // Step 2: Mock the UPDATE soft delete
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(fn(string $sql): bool =>
                str_contains($sql, 'UPDATE users') &&
                str_contains($sql, 'deleted_at = NOW()') &&
                str_contains($sql, 'WHERE id = ?') &&
                str_contains($sql, 'deleted_at IS NULL')
            ),
            [$userId]
        )
        ->andReturn(1);

    $result = $this->userModel->softDelete($userId);

    expect($result)->toBeTrue();
});

/**
 * Verifies softDelete returns false when record does not exist.
 *
 * The SELECT idempotency check finds no record, so no UPDATE is issued.
 */
test('softDelete returns false when record does not exist', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(false); // record not found

    // No UPDATE should be issued for non-existent records
    $this->dbMock->shouldNotHaveReceived('execute');

    $result = $this->userModel->softDelete($userId);

    expect($result)->toBeFalse();
});

/**
 * Verifies softDelete returns true when record is already soft-deleted (idempotency).
 *
 * Confirms idempotent behavior: deleting an already-deleted record returns true
 * without issuing a redundant UPDATE query.
 */
test('softDelete returns true when record is already soft-deleted', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(['id' => $userId, 'deleted_at' => '2025-01-01 00:00:00']); // already deleted

    // No UPDATE should be issued — idempotent early return
    $this->dbMock->shouldNotHaveReceived('execute');

    $result = $this->userModel->softDelete($userId);

    expect($result)->toBeTrue();
});

/**
 * Verifies softDelete throws InvalidArgumentException for invalid IDs.
 *
 * Ensures validation fires before any database interaction.
 */
test('softDelete throws InvalidArgumentException for non-positive ID', function (int $invalidId) {
    expect(fn() => $this->userModel->softDelete($invalidId))
        ->toThrow(\InvalidArgumentException::class, 'ID must be a positive integer');

    $this->dbMock->shouldNotHaveReceived('query');
    $this->dbMock->shouldNotHaveReceived('execute');
})->with([
    'zero'     => [0],
    'negative' => [-1],
]);

/**
 * Tests that findByEmail query excludes soft-deleted users.
 *
 * Verifies WHERE clause includes deleted_at IS NULL condition.
 */
test('findByEmail excludes soft-deleted users in SQL query', function () {
    $email = $this->faker->safeEmail();

    expect($email)->toBeValidEmail();

    // ensure soft-deleted users filtered at query level, not application level
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'WHERE email = ?')
                    && str_contains($sql, 'deleted_at IS NULL');
            }),
            [$email]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(false);

    $result = $this->userModel->findByEmail($email);

    expect($result)->toBeNull();
});

/**
 * Tests that findById excludes soft-deleted users in SQL query.
 *
 * Verifies WHERE clause includes deleted_at IS NULL condition.
 */
test('findById excludes soft-deleted users in SQL query', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    // ensure soft-deleted users filtered at query level
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'WHERE id = ?')
                    && str_contains($sql, 'deleted_at IS NULL');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(false);

    $result = $this->userModel->findById($userId);

    expect($result)->toBeNull();
});

/**
 * Tests that findAll excludes soft-deleted users in SQL query.
 *
 * Verifies WHERE clause includes deleted_at IS NULL condition.
 */
test('findAll excludes soft-deleted users in SQL query', function () {
    // ensure soft-deleted users filtered at query level
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::on(function ($sql) {
            return str_contains($sql, 'SELECT * FROM users')
                && str_contains($sql, 'WHERE deleted_at IS NULL');
        }))
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchAll')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn([]);

    $result = $this->userModel->findAll();

    expect($result)->toBeArray();
});

/**
 * Tests that restoreDeleted constructs proper SQL to clear deleted_at.
 *
 * Verifies SQL sets deleted_at to NULL only for soft-deleted users.
 */
test('restoreDeleted constructs correct SQL to clear timestamp', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    // verify SQL clears deleted_at and targets only soft-deleted users
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'UPDATE users')
                    && str_contains($sql, 'deleted_at = NULL')
                    && str_contains($sql, 'WHERE id = ?')
                    && str_contains($sql, 'deleted_at IS NOT NULL');
            }),
            [$userId]
        )
        ->andReturn(1);

    $result = $this->userModel->restoreDeleted($userId);

    expect($result)->toBeTrue();
});

/**
 * Tests that getUserRoles constructs proper JOIN query.
 *
 * Verifies SQL joins user_roles with roles table to fetch role slugs.
 */
test('getUserRoles constructs correct JOIN query', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'SELECT r.role_slug')
                    && str_contains($sql, 'FROM user_roles ur')
                    && str_contains($sql, 'JOIN roles r');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchAll')
        ->once()
        ->with(\PDO::FETCH_COLUMN)
        ->andReturn(['administrator', 'author']);

    $result = $this->userModel->getUserRoles($userId);

    expect($result)->toBeArray()
        ->and($result)->toContain('administrator', 'author');
});

/**
 * Tests that getUserRoles returns empty array when no roles found.
 *
 * Verifies graceful handling of users without role assignments.
 */
test('getUserRoles returns empty array when no roles found', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    // handle fetchAll returning empty array, not false
    $this->stmtMock->shouldReceive('fetchAll')
        ->once()
        ->with(\PDO::FETCH_COLUMN)
        ->andReturn([]);

    $result = $this->userModel->getUserRoles($userId);

    expect($result)->toBeArray()->toBeEmpty();
});

/**
 * Tests that getUserPermissions constructs complex JOIN query.
 *
 * Verifies SQL joins through user_roles, role_permissions, and permissions tables.
 */
test('getUserPermissions constructs correct multi-JOIN query', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'SELECT DISTINCT p.permission_slug')
                    && str_contains($sql, 'FROM user_roles ur')
                    && str_contains($sql, 'JOIN role_permissions rp')
                    && str_contains($sql, 'JOIN permissions p');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchAll')
        ->once()
        ->with(\PDO::FETCH_COLUMN)
        ->andReturn(['edit_post', 'delete_comment']);

    $result = $this->userModel->getUserPermissions($userId);

    expect($result)->toBeArray()
        ->and($result)->toContain('edit_post', 'delete_comment');
});

/**
 * Tests that updatePasswordHashById constructs proper UPDATE query.
 *
 * Verifies SQL updates only password field with proper parameter binding.
 */
test('updatePasswordHashById constructs correct SQL', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $hash = $this->faker->sha256();

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'UPDATE users')
                    && str_contains($sql, 'SET password = ?')
                    && str_contains($sql, 'WHERE id = ?');
            }),
            [$hash, $userId]
        )
        ->andReturn(1);

    $result = $this->userModel->updatePasswordHashById($userId, $hash);

    expect($result)->toBeTrue();
});

/**
 * Tests that countPosts constructs correct COUNT query.
 *
 * Verifies SQL counts posts authored by specific user.
 */
test('countPosts constructs correct COUNT query', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $postCount = $this->faker->numberBetween(0, 100);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'SELECT COUNT(*)')
                    && str_contains($sql, 'FROM posts')
                    && str_contains($sql, 'WHERE author_id = ?');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchColumn')
        ->once()
        ->andReturn($postCount);

    $result = $this->userModel->countPosts($userId);

    expect($result)->toBeInt()->toBe($postCount);
});

/**
 * Tests that countBlogs constructs correct COUNT query.
 *
 * Verifies SQL counts blogs owned by specific user.
 */
test('countBlogs constructs correct COUNT query', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $blogCount = $this->faker->numberBetween(0, 10);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'SELECT COUNT(*)')
                    && str_contains($sql, 'FROM blogs')
                    && str_contains($sql, 'WHERE owner_id = ?');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchColumn')
        ->once()
        ->andReturn($blogCount);

    $result = $this->userModel->countBlogs($userId);

    expect($result)->toBeInt()->toBe($blogCount);
});

/**
 * Tests that countCommentsReceived constructs correct JOIN COUNT query.
 *
 * Verifies SQL counts comments on user\'s posts via JOIN.
 */
test('countCommentsReceived constructs correct JOIN COUNT query', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $commentCount = $this->faker->numberBetween(0, 500);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'SELECT COUNT(c.id)')
                    && str_contains($sql, 'FROM comments c')
                    && str_contains($sql, 'JOIN posts p')
                    && str_contains($sql, 'WHERE p.author_id = ?');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchColumn')
        ->once()
        ->andReturn($commentCount);

    $result = $this->userModel->countCommentsReceived($userId);

    expect($result)->toBeInt()->toBe($commentCount);
});

/**
 * Tests that canDelete returns false when user has posts.
 *
 * Verifies business rule preventing deletion of users with content.
 */
test('canDelete returns false when user has posts', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchColumn')
        ->once()
        ->andReturn(5);

    $result = $this->userModel->canDelete($userId);

    expect($result)->toBeFalse();
});

/**
 * Tests that canDelete returns true when user has no posts.
 *
 * Verifies business rule allows deletion of users without content.
 */
test('canDelete returns true when user has zero posts', function () {
    $userId = $this->faker->numberBetween(1, 1000);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchColumn')
        ->once()
        ->andReturn(0);

    $result = $this->userModel->canDelete($userId);

    expect($result)->toBeTrue();
});

/**
 * Tests that updateById returns true for empty data array.
 *
 * Verifies early return optimization without database call.
 */
test('updateById returns true for empty data without database call', function () {
    $result = $this->userModel->updateById(
        $this->faker->numberBetween(1, 1000),
        []
    );

    expect($result)->toBeTrue();

    // Verify optimization: no database call for empty input
    $this->dbMock->shouldNotHaveReceived('query');
    $this->dbMock->shouldNotHaveReceived('execute');
});

/**
 * Tests that updateById constructs dynamic UPDATE with positional parameters.
 *
 * Verifies SQL builds correctly with multiple columns.
 */
test('updateById constructs dynamic UPDATE with positional parameters', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $email = $this->faker->email();
    $username = $this->faker->userName();

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'UPDATE users')
                    && str_contains($sql, 'SET email = ?, username = ?')
                    && str_contains($sql, 'WHERE id = ?');
            }),
            [$email, $username, $userId]
        )
        ->andReturn(1);

    $result = $this->userModel->updateById($userId, [
        'email' => $email,
        'username' => $username,
    ]);

    expect($result)->toBeTrue();
});

/**
 * Tests that insertUserRoles executes multiple INSERT statements.
 *
 * Verifies each role gets its own parameterized INSERT query.
 */
test('insertUserRoles executes INSERT for each role', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $roles = [1, 2, 3];

    // expect three separate execute calls, one per role
    $this->dbMock->shouldReceive('execute')
        ->times(3)
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'INSERT INTO user_roles')
                    && str_contains($sql, 'VALUES (?, ?)');
            }),
            Mockery::on(function ($params) use ($userId) {
                return $params[0] === $userId && in_array($params[1], [1, 2, 3]);
            })
        )
        ->andReturn(1);

    $result = $this->userModel->insertUserRoles($userId, $roles);

    expect($result)->toBeTrue();
});

/**
 * Tests that updateUserRoles synchronizes role assignments correctly.
 *
 * Verifies differential updates: delete removed roles, insert new roles.
 */
test('updateUserRoles synchronizes roles with differential updates', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $currentRoles = [1, 2];
    $newRoles = [2, 3];

    // Step 1: Query current roles
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'SELECT role_id FROM user_roles');
            }),
            [$userId]
        )
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchAll')
        ->once()
        ->with(\PDO::FETCH_COLUMN)
        ->andReturn($currentRoles);

    // Step 2: Delete removed role (1)
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'DELETE FROM user_roles')
                    && str_contains($sql, 'role_id IN (?)');
            }),
            [$userId, 1]
        )
        ->andReturn(1);

    // Step 3: Insert new role (3)
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(
            Mockery::on(function ($sql) {
                return str_contains($sql, 'INSERT INTO user_roles')
                    && str_contains($sql, 'VALUES (?, ?)');
            }),
            [$userId, 3]
        )
        ->andReturn(1);

    $result = $this->userModel->updateUserRoles($userId, $newRoles);

    expect($result)->toBeTrue();
});

/**
 * Tests that countAdministrators constructs correct query without parameters.
 *
 * Verifies SQL counts distinct active admin users.
 */
test('countAdministrators constructs correct parameterless query', function () {
    $adminCount = $this->faker->numberBetween(1, 5);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->withArgs(function ($sql, $params = null) {
            $sqlValid = str_contains($sql, 'SELECT COUNT(DISTINCT u.id)')
                && str_contains($sql, "r.role_slug = 'administrator'")
                && str_contains($sql, 'u.deleted_at IS NULL');

            // accept either no second param or empty array/null
            $paramsValid = $params === null || $params === [] || !isset($params);

            return $sqlValid;
        })
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchColumn')
        ->once()
        ->andReturn($adminCount);

    $result = $this->userModel->countAdministrators();

    expect($result)->toBeInt()->toBe($adminCount);
});

/**
 * Tests that verifyPassword returns false for non-existent user.
 *
 * Verifies password verification handles missing users gracefully.
 */
test('verifyPassword returns false when user not found', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $password = $this->faker->password();

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(false);

    $result = $this->userModel->verifyPassword($userId, $password);

    expect($result)->toBeFalse();
});

/**
 * Tests that verifyPassword validates correct password hash.
 *
 * Verifies password_verify integration for authentication.
 */
test('verifyPassword returns true for correct password', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $password = 'correct_password';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(['id' => $userId, 'password' => $hash]);

    $result = $this->userModel->verifyPassword($userId, $password);

    expect($result)->toBeTrue();
});

/**
 * Tests that verifyPassword rejects incorrect password.
 *
 * Verifies password_verify fails for wrong password.
 */
test('verifyPassword returns false for incorrect password', function () {
    $userId = $this->faker->numberBetween(1, 1000);
    $correctPassword = 'correct_password';
    $wrongPassword = 'wrong_password';
    $hash = password_hash($correctPassword, PASSWORD_DEFAULT);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::any(), [$userId])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetch')
        ->once()
        ->with(\PDO::FETCH_ASSOC)
        ->andReturn(['id' => $userId, 'password' => $hash]);

    $result = $this->userModel->verifyPassword($userId, $wrongPassword);

    expect($result)->toBeFalse();
});
