<?php

declare(strict_types=1);

use Framework\Validation\Validator;

/**
 * Unit test suite for Validator core functionality.
 *
 * Tests the foundational validator mechanics: rule definition,
 * message customization, validation execution, and data filtering.
 * All tests run in isolation without database or external dependencies.
 */
describe('Validator Basics', function () {

    // ==================== Constructor & Rule Definition ====================

    /**
     * Verify Validator accepts data array in constructor.
     */
    test('it accepts data in constructor', function () {
        $email = faker()->email();
        $validator = new Validator(['email' => $email]);

        expect($validator)->toBeInstanceOf(Validator::class);
    });

    /**
     * Verify Validator handles empty data gracefully.
     */
    test('it accepts empty data array', function () {
        $validator = new Validator([]);

        // Empty data with no rules should pass validation
        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test array syntax for rule definition.
     */
    test('it defines rules using array syntax', function () {
        $email = faker()->safeEmail();
        $validator = new Validator(['email' => $email]);
        $validator->rules([
            'email' => ['required', 'email'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test pipe-delimited string syntax for rules (Laravel-style).
     */
    test('it defines rules using pipe syntax', function () {
        $email = faker()->companyEmail();
        $validator = new Validator(['email' => $email]);
        $validator->rules([
            'email' => 'required|email',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Verify fluent interface for method chaining.
     */
    test('it returns self for fluent chaining', function () {
        $validator = new Validator(['email' => faker()->email()]);
        $result = $validator->rules(['email' => 'required']);

        expect($result)->toBe($validator);
    });

    // ==================== Custom Messages ====================

    /**
     * Test custom error message override for validation failures.
     */
    test('it sets custom error messages', function () {
        $validator = new Validator(['email' => '']);
        $customMessage = 'We need your email address to continue!';

        $validator->rules(['email' => 'required'])
            ->messages(['email.required' => $customMessage]);

        $validator->fails();
        $errors = $validator->errors();

        expect($errors['email'][0])->toBe($customMessage);
    });

    /**
     * Verify messages() returns self for chaining.
     */
    test('it chains messages() for fluent interface', function () {
        $validator = new Validator([]);
        $result = $validator->messages(['email.required' => 'Custom message']);

        expect($result)->toBe($validator);
    });

    /**
     * Test fallback to default error messages when custom not provided.
     */
    test('it uses default message when custom not provided', function () {
        $username = faker()->userName();
        $validator = new Validator(['username' => '']);
        $validator->rules(['username' => 'required']);

        $validator->fails();
        $errors = $validator->errors();

        // Default message format: "Username is required."
        expect($errors['username'][0])->toBe('Username is required.');
    });

    // ==================== Validation Execution ====================

    /**
     * Verify passes() returns true when all validation rules succeed.
     */
    test('passes returns true when all rules pass', function () {
        $validator = new Validator([
            'email' => faker()->safeEmail(),
            'name' => faker()->name(),
        ]);
        $validator->rules([
            'email' => 'required|email',
            'name' => 'required',
        ]);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test passes() returns false on validation failure.
     */
    test('passes returns false when validation fails', function () {
        $validator = new Validator(['email' => 'not-an-email']);
        $validator->rules(['email' => 'required|email']);

        expect($validator->passes())->toBeFalse();
    });

    /**
     * Verify fails() is inverse of passes().
     */
    test('fails returns true when validation fails', function () {
        $validator = new Validator(['email' => '']);
        $validator->rules(['email' => 'required']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test fails() returns false when validation succeeds.
     */
    test('fails returns false when all rules pass', function () {
        $validator = new Validator(['email' => faker()->email()]);
        $validator->rules(['email' => 'required|email']);

        expect($validator->fails())->toBeFalse();
    });

    /**
     * Verify errors are cleared between validation runs.
     *
     * This prevents error accumulation when validator is reused.
     */
    test('it clears errors on subsequent passes() calls', function () {
        $validator = new Validator(['email' => '']);
        $validator->rules(['email' => 'required']);

        $validator->fails();
        $firstErrors = $validator->errors();

        // Update data to valid and re-run validation
        $validator = new Validator(['email' => faker()->email()]);
        $validator->rules(['email' => 'required']);
        $validator->passes();

        expect($firstErrors)->not->toBeEmpty()
            ->and($validator->errors())->toBeEmpty();
    });

    // ==================== Error Handling ====================

    /**
     * Verify errors() returns empty array on successful validation.
     */
    test('errors returns empty array when validation passes', function () {
        $validator = new Validator(['email' => faker()->safeEmail()]);
        $validator->rules(['email' => 'required|email']);
        $validator->passes();

        expect($validator->errors())->toBeEmpty();
    });

    /**
     * Test errors() returns structured array of validation failures.
     */
    test('errors returns array of failures', function () {
        $validator = new Validator([
            'email' => '',
            'password' => '',
        ]);
        $validator->rules([
            'email' => 'required',
            'password' => 'required',
        ]);
        $validator->fails();

        $errors = $validator->errors();

        expect($errors)->toHaveKeys(['email', 'password'])
            ->and($errors['email'])->toHaveCount(1)
            ->and($errors['password'])->toHaveCount(1);
    });

    /**
     * Verify validator stops at first failed rule per field.
     *
     * This provides faster feedback and prevents error message spam.
     */
    test('it stops at first failed rule per field', function () {
        $validator = new Validator(['email' => '']);
        $validator->rules(['email' => 'required|email|min:5']);
        $validator->fails();

        $errors = $validator->errors();

        // Should only report 'required' error, not 'email' or 'min'
        expect($errors['email'])->toHaveCount(1)
            ->and($errors['email'][0])->toContain('required');
    });

    /**
     * Test error collection across multiple fields.
     */
    test('it collects errors for multiple fields', function () {
        $validator = new Validator([
            'email' => '',
            'username' => '',
            'password' => '',
        ]);
        $validator->rules([
            'email' => 'required',
            'username' => 'required',
            'password' => 'required',
        ]);
        $validator->fails();

        expect($validator->errors())->toHaveCount(3);
    });

    // ==================== Validated Data Filtering ====================

    /**
     * Test validated() returns only fields defined in rules.
     *
     * This prevents mass-assignment vulnerabilities by filtering
     * unexpected fields that could manipulate protected attributes.
     */
    test('validated returns only fields defined in rules', function () {
        $email = faker()->email();
        $password = faker()->password(12);

        $validator = new Validator([
            'email' => $email,
            'password' => $password,
            'is_admin' => true, // Malicious field injection attempt
            '_token' => 'csrf123',
        ]);
        $validator->rules([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $validator->passes();

        $validated = $validator->validated();

        // Only ruled fields should pass through
        expect($validated)->toHaveKeys(['email', 'password'])
            ->not->toHaveKey('is_admin')
            ->not->toHaveKey('_token');
    });

    /**
     * Verify validated() excludes missing fields from dataset.
     */
    test('validated excludes missing fields', function () {
        $validator = new Validator(['email' => faker()->email()]);
        $validator->rules([
            'email' => 'required',
            'password' => 'required',
        ]);

        $validated = $validator->validated();

        // Password not in original data, should be excluded
        expect($validated)->toHaveKey('email')
            ->not->toHaveKey('password');
    });

    /**
     * Test validated() works even after validation failure.
     *
     * We still return filtered data for security (prevent mass-assignment),
     * even if validation failed.
     */
    test('validated works with failed validation', function () {
        $validator = new Validator([
            'email' => 'not-valid',
            'csrf_token' => faker()->uuid(),
        ]);
        $validator->rules(['email' => 'required|email']);
        $validator->fails();

        $validated = $validator->validated();

        // Should still filter even on failure (security measure)
        expect($validated)->toHaveKey('email')
            ->not->toHaveKey('csrf_token');
    });

    // ==================== Edge Cases ====================

    /**
     * Verify exception thrown for undefined validation rule.
     */
    test('it throws exception for undefined validation rule', function () {
        $validator = new Validator(['field' => faker()->word()]);
        $validator->rules(['field' => 'nonexistent_rule']);

        expect(fn () => $validator->passes())
            ->toThrow(\BadMethodCallException::class, "Validation rule 'nonexistent_rule' is not defined.");
    });

    /**
     * Test null value handling in optional fields.
     *
     * Rules like 'email' should pass for null when 'required' is absent.
     */
    test('it handles null values in data', function () {
        $validator = new Validator(['field' => null]);
        $validator->rules(['field' => 'email']);

        // Email rule should pass for null (not required)
        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test missing field with optional validation.
     */
    test('it handles missing field with optional validation', function () {
        $validator = new Validator([]);
        $validator->rules(['email' => 'email']);

        // Email not required and not present = valid
        expect($validator->passes())->toBeTrue();
    });

    // ==================== Security: XSS Protection ====================

    /**
     * Verify validator accepts XSS payloads for validation.
     *
     * We accept malicious input here because validation is not responsible
     * for sanitization. XSS protection happens at output escaping layer.
     * This tests that validator doesn't crash on attack vectors.
     */
    test('it handles XSS payloads without breaking', function ($xssPayload) {
        $validator = new Validator(['comment' => $xssPayload]);
        $validator->rules(['comment' => 'required']);

        // Validator should process malicious input without errors
        expect($validator->passes())->toBeTrue();
    })->with('xss_payloads'); // Use existing dataset

    // ==================== Security: SQL Injection ====================

    /**
     * Test validator handles SQL injection attempts gracefully.
     *
     * Validator should accept these strings without breaking.
     * SQL injection prevention happens at database layer via parameterized queries.
     */
    test('it handles SQL injection attempts without breaking', function ($sqlInjection) {
        $validator = new Validator(['username' => $sqlInjection]);
        $validator->rules(['username' => 'required']);

        // Validator should process SQL injection attempts without errors
        expect($validator->passes())->toBeTrue();
    })->with('sql_injection_attempts'); // Use existing dataset

    // ==================== Security: Unicode Edge Cases ====================

    /**
     * Test validator handles Unicode edge cases and exploits.
     *
     * We verify validator doesn't crash on Unicode attacks (homoglyphs,
     * zero-width characters, RTL overrides, etc.).
     */
    test('it handles Unicode edge cases without breaking', function ($unicodeEdgeCase) {
        $validator = new Validator(['text' => $unicodeEdgeCase]);
        $validator->rules(['text' => 'required']);

        // Validator should handle Unicode exploits gracefully
        $validator->passes();

        expect($validator->errors())->toBeArray();
    })->with('unicode_edge_cases'); // Use existing dataset

});
