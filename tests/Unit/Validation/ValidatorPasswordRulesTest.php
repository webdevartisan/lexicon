<?php

declare(strict_types=1);

use Framework\Validation\Validator;

/**
 * Unit test suite for Validator password strength rules.
 *
 * Tests password complexity requirements including length,
 * character composition, and preset configurations (strong, medium, basic).
 * All tests run in isolation without database or external dependencies.
 */
describe('Validator Password Rules', function () {

    // ==================== Basic Password Validation ====================

    /**
     * Verify password rule passes for empty string when field not required.
     *
     * Allow empty passwords when 'required' rule is absent.
     */
    test('password passes for empty string when not required', function () {
        $validator = new Validator(['password' => '']);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test password rule passes for null when field not required.
     */
    test('password passes for null when not required', function () {
        $validator = new Validator(['password' => null]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->passes())->toBeTrue();
    });

    // ==================== Preset: Strong ====================

    /**
     * Verify 'strong' preset passes for password meeting all requirements.
     *
     * Requirements: min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol.
     */
    test('password strong passes for valid password', function () {
        $password = faker()->regexify('[A-Z][a-z]{4}[0-9]{2}[!@#$]');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test 'strong' preset fails when uppercase letter missing.
     */
    test('password strong fails for missing uppercase', function () {
        $password = faker()->regexify('[a-z]{5}[0-9]{2}[!@#$]');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('1 uppercase letter');
    });

    /**
     * Test 'strong' preset fails when lowercase letter missing.
     */
    test('password strong fails for missing lowercase', function () {
        $password = faker()->regexify('[A-Z]{5}[0-9]{2}[!@#$]');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('1 lowercase letter');
    });

    /**
     * Test 'strong' preset fails when number missing.
     */
    test('password strong fails for missing number', function () {
        $password = faker()->regexify('[A-Z][a-z]{5}[!@#$]');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('1 number');
    });

    /**
     * Test 'strong' preset fails when special character missing.
     */
    test('password strong fails for missing symbol', function () {
        $password = faker()->regexify('[A-Z][a-z]{4}[0-9]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('1 special character');
    });

    /**
     * Test 'strong' preset fails when password too short.
     */
    test('password strong fails for too short', function () {
        $password = faker()->regexify('[A-Z][a-z][0-9][!@#$]'); // Only 4 chars
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('at least 8 characters');
    });

    /**
     * Verify error message lists all requirements for weak passwords.
     */
    test('password strong error message lists all requirements', function () {
        $validator = new Validator(['password' => 'weak']);
        $validator->rules(['password' => 'password:strong']);
        $validator->fails();

        $error = $validator->errors()['password'][0];

        expect($error)->toContain('8 characters')
            ->and($error)->toContain('uppercase')
            ->and($error)->toContain('lowercase')
            ->and($error)->toContain('number')
            ->and($error)->toContain('special character');
    });

    // ==================== Preset: Medium ====================

    /**
     * Verify 'medium' preset passes for password with basic requirements.
     *
     * Requirements: min 8 chars, 1 uppercase, 1 lowercase, 1 number (no symbol required).
     */
    test('password medium passes for valid password', function () {
        $password = faker()->regexify('[A-Z][a-z]{5}[0-9]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:medium']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test 'medium' preset passes without special characters.
     */
    test('password medium passes without symbol', function () {
        $password = faker()->regexify('[A-Z][a-z]{6}[0-9]');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:medium']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test 'medium' preset fails when uppercase missing.
     */
    test('password medium fails for missing uppercase', function () {
        $password = faker()->regexify('[a-z]{6}[0-9]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:medium']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test 'medium' preset fails when number missing.
     */
    test('password medium fails for missing number', function () {
        $password = faker()->regexify('[A-Z][a-z]{7}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:medium']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test 'medium' preset fails when password too short.
     */
    test('password medium fails for too short', function () {
        $password = faker()->regexify('[A-Z][a-z]{3}[0-9]'); // Only 5 chars
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:medium']);

        expect($validator->fails())->toBeTrue();
    });

    // ==================== Preset: Basic ====================

    /**
     * Verify 'basic' preset passes for simple password.
     *
     * Requirements: min 6 chars only (no composition requirements).
     */
    test('password basic passes for simple password', function () {
        $password = faker()->regexify('[a-z]{6}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:basic']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test 'basic' preset passes for exactly 6 characters.
     */
    test('password basic passes for 6 characters', function () {
        $password = faker()->regexify('[a-z]{6}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:basic']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test 'basic' preset fails when password too short.
     */
    test('password basic fails for too short', function () {
        $password = faker()->regexify('[a-z]{5}'); // Only 5 chars
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:basic']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('at least 6 characters');
    });

    // ==================== Custom Parameters ====================

    /**
     * Test custom minimum length requirement.
     */
    test('password custom min length', function () {
        $password = faker()->regexify('[a-z0-9]{10}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:10']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test custom minimum length requirement fails.
     */
    test('password custom min length fails', function () {
        $password = faker()->regexify('[a-z0-9]{9}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:10']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('10 characters');
    });

    /**
     * Test custom uppercase requirement (multiple letters).
     */
    test('password custom uppercase requirement', function () {
        $password = faker()->regexify('[A-Z]{2}[a-z]{6}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,uppercase:2']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test custom uppercase requirement fails when insufficient.
     */
    test('password custom uppercase requirement fails', function () {
        $password = faker()->regexify('[A-Z][a-z]{7}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,uppercase:2']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('2 uppercase letters');
    });

    /**
     * Test custom lowercase requirement (multiple letters).
     */
    test('password custom lowercase requirement', function () {
        $password = faker()->regexify('[A-Z]{4}[a-z]{4}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,lowercase:3']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test custom lowercase requirement fails when insufficient.
     */
    test('password custom lowercase requirement fails', function () {
        $password = faker()->regexify('[A-Z]{6}[a-z]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,lowercase:3']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('3 lowercase letters');
    });

    /**
     * Test custom numbers requirement (multiple digits).
     */
    test('password custom numbers requirement', function () {
        $password = faker()->regexify('[a-z]{4}[0-9]{4}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,numbers:3']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test custom numbers requirement fails when insufficient.
     */
    test('password custom numbers requirement fails', function () {
        $password = faker()->regexify('[a-z]{6}[0-9]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,numbers:3']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('3 numbers');
    });

    /**
     * Test custom symbols requirement (multiple special characters).
     */
    test('password custom symbols requirement', function () {
        $password = faker()->regexify('[a-z]{4}[!@#$]{4}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,symbols:3']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test custom symbols requirement fails when insufficient.
     */
    test('password custom symbols requirement fails', function () {
        $password = faker()->regexify('[a-z]{6}[!@]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,symbols:3']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('3 special characters');
    });

    /**
     * Test combining multiple custom requirements simultaneously.
     */
    test('password combines multiple custom requirements', function () {
        $password = faker()->regexify('[A-Z][a-z][0-9]{2}[!@]{2}[a-z]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8,uppercase:1,lowercase:1,numbers:2,symbols:2']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test password with no composition requirements (length only).
     */
    test('password custom with no requirements', function () {
        $password = faker()->regexify('[0-9]{8}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:min:8']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Verify default minimum length is 8 characters when no parameter provided.
     */
    test('password uses default min 8 when no parameter', function () {
        $password = faker()->regexify('[a-z]{7}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])
            ->toContain('8 characters');
    });

    /**
     * Test empty parameter uses default configuration.
     */
    test('password empty parameter uses defaults', function () {
        $password = faker()->regexify('[a-z0-9]{8}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'password:']);

        expect($validator->passes())->toBeTrue();
    });

    test('password fails for common weak patterns', function ($weakPassword) {
        $validator = new Validator(['password' => $weakPassword]);
        $validator->rules(['password' => 'password:strong']);

        expect($validator->fails())->toBeTrue();
    })->with('invalid_passwords');

});
