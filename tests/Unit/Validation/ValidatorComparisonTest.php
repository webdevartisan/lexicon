<?php

declare(strict_types=1);

use Framework\Validation\Validator;

/**
 * Unit test suite for Validator comparison-based rules.
 *
 * Tests rules that compare field values against each other or
 * against predefined sets, and validate boolean representations.
 * All tests run in isolation without database or external dependencies.
 */
describe('Validator Comparison Rules', function () {

    // ==================== Same Rule ====================

    /**
     * Verify same rule passes when compared values match exactly.
     */
    test('same passes when values match', function () {
        $password = faker()->password(12);
        $validator = new Validator([
            'password' => $password,
            'password_repeat' => $password,
        ]);
        $validator->rules(['password_repeat' => 'same:password']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test same rule fails when compared values differ.
     */
    test('same fails when values differ', function () {
        $validator = new Validator([
            'password' => faker()->password(12),
            'password_repeat' => faker()->password(12),
        ]);
        $validator->rules(['password_repeat' => 'same:password']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password_repeat'][0])->toBe('Password repeat must match password.');
    });

    /**
     * Test same rule fails when compared field is missing from data.
     *
     * Prevent validation against undefined fields.
     */
    test('same fails when compared field missing', function () {
        $validator = new Validator(['password_repeat' => faker()->password(12)]);
        $validator->rules(['password_repeat' => 'same:password']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test same rule fails when parameter is not provided.
     *
     * Require explicit field name to compare against.
     */
    test('same fails when parameter not provided', function () {
        $validator = new Validator(['field' => faker()->word()]);
        $validator->rules(['field' => 'same']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify same rule uses strict comparison (===).
     *
     * String '123' and integer 123 are considered different values
     * to prevent type coercion vulnerabilities.
     */
    test('same uses strict comparison', function () {
        $validator = new Validator([
            'number' => '123',
            'number_repeat' => 123,
        ]);
        $validator->rules(['number_repeat' => 'same:number']);

        expect($validator->fails())->toBeTrue();
    });

    // ==================== Confirmed Rule ====================

    /**
     * Verify confirmed rule passes when confirmation field matches.
     *
     * Automatically looks for {field}_confirmation naming convention.
     */
    test('confirmed passes when confirmation matches', function () {
        $password = faker()->password(12);
        $validator = new Validator([
            'password' => $password,
            'password_confirmation' => $password,
        ]);
        $validator->rules(['password' => 'confirmed']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test confirmed rule fails when confirmation value differs.
     */
    test('confirmed fails when confirmation differs', function () {
        $validator = new Validator([
            'password' => faker()->password(12),
            'password_confirmation' => faker()->password(12),
        ]);
        $validator->rules(['password' => 'confirmed']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])->toBe('Password confirmation does not match.');
    });

    /**
     * Test confirmed rule fails when confirmation field is missing.
     */
    test('confirmed fails when confirmation field missing', function () {
        $validator = new Validator(['password' => faker()->password(12)]);
        $validator->rules(['password' => 'confirmed']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify confirmed rule follows {field}_confirmation naming convention.
     */
    test('confirmed automatically looks for field_confirmation', function () {
        $email = faker()->safeEmail();
        $validator = new Validator([
            'email' => $email,
            'email_confirmation' => $email,
        ]);
        $validator->rules(['email' => 'confirmed']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Verify confirmed rule uses strict comparison (===).
     *
     * Prevent type coercion issues in sensitive fields like passwords.
     */
    test('confirmed uses strict comparison', function () {
        $validator = new Validator([
            'count' => '100',
            'count_confirmation' => 100,
        ]);
        $validator->rules(['count' => 'confirmed']);

        expect($validator->fails())->toBeTrue();
    });

    // ==================== In Rule ====================

    /**
     * Test in rule passes when value exists in allowed list.
     */
    test('in passes when value in list', function () {
        $validator = new Validator(['status' => 'active']);
        $validator->rules(['status' => 'in:active,inactive,pending']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test in rule passes for first item in list.
     */
    test('in passes for first item in list', function () {
        $validator = new Validator(['role' => 'admin']);
        $validator->rules(['role' => 'in:admin,editor,viewer']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test in rule passes for last item in list.
     */
    test('in passes for last item in list', function () {
        $validator = new Validator(['role' => 'viewer']);
        $validator->rules(['role' => 'in:admin,editor,viewer']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test in rule fails when value not in allowed list.
     */
    test('in fails when value not in list', function () {
        $validator = new Validator(['status' => 'deleted']);
        $validator->rules(['status' => 'in:active,inactive,pending']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['status'][0])->toBe('Status must be one of: active,inactive,pending.');
    });

    /**
     * Verify in rule casts values to string for comparison.
     *
     * Integer 1 matches string '1' in the allowed list.
     */
    test('in uses strict comparison', function () {
        $validator = new Validator(['number' => 1]);
        $validator->rules(['number' => 'in:1,2,3']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test in rule fails when parameter is missing.
     *
     * Require explicit list of allowed values.
     */
    test('in fails when parameter missing', function () {
        $validator = new Validator(['status' => 'active']);
        $validator->rules(['status' => 'in']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test in rule handles single-value whitelist.
     */
    test('in handles single value list', function () {
        $validator = new Validator(['type' => 'blog']);
        $validator->rules(['type' => 'in:blog']);

        expect($validator->passes())->toBeTrue();
    });

    // ==================== Boolean Rule ====================

    /**
     * Test boolean rule passes for PHP boolean true.
     */
    test('boolean passes for true', function () {
        $validator = new Validator(['active' => true]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for PHP boolean false.
     */
    test('boolean passes for false', function () {
        $validator = new Validator(['active' => false]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for integer 1.
     */
    test('boolean passes for integer 1', function () {
        $validator = new Validator(['active' => 1]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for integer 0.
     */
    test('boolean passes for integer 0', function () {
        $validator = new Validator(['active' => 0]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for string "1".
     *
     * Support HTML form checkbox values (checked = "1").
     */
    test('boolean passes for string "1"', function () {
        $validator = new Validator(['active' => '1']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for string "0".
     *
     * Support HTML form checkbox values (unchecked = "0").
     */
    test('boolean passes for string "0"', function () {
        $validator = new Validator(['active' => '0']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for string "true".
     */
    test('boolean passes for string "true"', function () {
        $validator = new Validator(['active' => 'true']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for string "false".
     */
    test('boolean passes for string "false"', function () {
        $validator = new Validator(['active' => 'false']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for "yes".
     *
     * Support user-friendly boolean representations.
     */
    test('boolean passes for "yes"', function () {
        $validator = new Validator(['active' => 'yes']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for "no".
     */
    test('boolean passes for "no"', function () {
        $validator = new Validator(['active' => 'no']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for "on".
     *
     * Support HTML checkbox default value when checked.
     */
    test('boolean passes for "on"', function () {
        $validator = new Validator(['active' => 'on']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for "off".
     *
     * Support HTML checkbox alternative value when unchecked.
     */
    test('boolean passes for "off"', function () {
        $validator = new Validator(['active' => 'off']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule fails for non-boolean string.
     */
    test('boolean fails for random string', function () {
        $validator = new Validator(['active' => faker()->word()]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['active'][0])->toBe('Active must be true or false.');
    });

    /**
     * Test boolean rule fails for integer 2.
     *
     * Only 0 and 1 are accepted as integer boolean representations.
     */
    test('boolean fails for integer 2', function () {
        $validator = new Validator(['active' => 2]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify boolean rule passes for null when not required.
     *
     * Allow empty values - use 'required' rule to enforce presence.
     */
    test('boolean passes for null when not required', function () {
        $validator = new Validator(['active' => null]);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test boolean rule passes for empty string when not required.
     *
     * HTML forms send empty string when checkbox not sent.
     */
    test('boolean passes for empty string when not required', function () {
        $validator = new Validator(['active' => '']);
        $validator->rules(['active' => 'boolean']);

        expect($validator->passes())->toBeTrue();
    });

    // ==================== Accepted Rule ====================

    /**
     * Test accepted rule passes for PHP boolean true.
     */
    test('accepted passes for true', function () {
        $validator = new Validator(['terms' => true]);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test accepted rule passes for integer 1.
     */
    test('accepted passes for integer 1', function () {
        $validator = new Validator(['terms' => 1]);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test accepted rule passes for string "1".
     *
     * HTML checkbox sends "1" when checked.
     */
    test('accepted passes for string "1"', function () {
        $validator = new Validator(['terms' => '1']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test accepted rule passes for "yes".
     */
    test('accepted passes for "yes"', function () {
        $validator = new Validator(['terms' => 'yes']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test accepted rule passes for "on".
     *
     * HTML checkbox default value when checked.
     */
    test('accepted passes for "on"', function () {
        $validator = new Validator(['terms' => 'on']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test accepted rule passes for "true".
     */
    test('accepted passes for "true"', function () {
        $validator = new Validator(['terms' => 'true']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test accepted rule fails for boolean false.
     *
     * Require explicit acceptance, reject false values.
     */
    test('accepted fails for false', function () {
        $validator = new Validator(['terms' => false]);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['terms'][0])->toBe('Terms must be accepted.');
    });

    /**
     * Test accepted rule fails for integer 0.
     */
    test('accepted fails for integer 0', function () {
        $validator = new Validator(['terms' => 0]);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test accepted rule fails for string "0".
     */
    test('accepted fails for string "0"', function () {
        $validator = new Validator(['terms' => '0']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test accepted rule fails for "no".
     */
    test('accepted fails for "no"', function () {
        $validator = new Validator(['terms' => 'no']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test accepted rule fails for "false".
     */
    test('accepted fails for "false"', function () {
        $validator = new Validator(['terms' => 'false']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test accepted rule fails for null.
     *
     * Unlike boolean rule, accepted requires explicit truthy value.
     */
    test('accepted fails for null', function () {
        $validator = new Validator(['terms' => null]);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test accepted rule fails for empty string.
     *
     * HTML forms send empty when checkbox not checked - reject this.
     */
    test('accepted fails for empty string', function () {
        $validator = new Validator(['terms' => '']);
        $validator->rules(['terms' => 'accepted']);

        expect($validator->fails())->toBeTrue();
    });

});
