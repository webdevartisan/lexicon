<?php

declare(strict_types=1);

/**
 * Reusable datasets for user testing.
 * We define common test scenarios to eliminate duplication.
 */
dataset('invalid_emails', [
    'missing @' => 'notanemail',
    'missing domain' => 'test@',
    'missing local' => '@example.com',
    'spaces' => 'test @example.com',
    'double @' => 'test@@example.com',
    'consecutive dots' => 'test..name@example.com',
    'leading dot' => '.test@example.com',
    'trailing dot' => 'test.@example.com',
]);

dataset('invalid_passwords', [
    'too short' => 'abc',
    'no uppercase' => 'password123',
    'no lowercase' => 'PASSWORD123',
    'no numbers' => 'PasswordABC',
    'only numbers' => '123456789',
]);

dataset('invalid_column_names', [
    'SQL injection DROP' => 'email; DROP TABLE users;--',
    'special characters' => 'invalid-column!',
    'script tag' => '<script>alert(1)</script>',
    'null byte' => "email\0",
    'semicolon' => 'email;',
]);

dataset('user_counts', [0, 1, 5, 10, 50]);

dataset('valid_user_roles', [
    'admin' => [1],
    'editor' => [2],
    'author' => [3],
    'multiple roles' => [2, 3],
]);
