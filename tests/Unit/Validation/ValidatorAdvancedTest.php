<?php

declare(strict_types=1);

use Framework\Validation\Validator;

/**
 * Unit test suite for Validator advanced rules.
 *
 * Tests complex validation patterns including custom regex
 * and specialized input sanitization rules.
 * All tests run in isolation without database or external dependencies.
 */
describe('Validator Advanced Rules', function () {

    // ==================== Regex Rule ====================

    /**
     * Test regex rule passes when value matches custom pattern.
     */
    test('regex passes for matching pattern', function () {
        $phone = faker()->numerify('###-###-####');
        $validator = new Validator(['phone' => $phone]);
        $validator->rules(['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule fails when value doesn't match pattern.
     */
    test('regex fails for non-matching pattern', function () {
        $phone = faker()->numerify('##########'); // No dashes
        $validator = new Validator(['phone' => $phone]);
        $validator->rules(['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['phone'][0])->toBe('Phone format is invalid.');
    });

    /**
     * Test regex rule validates postal code format.
     */
    test('regex passes for postal code pattern', function () {
        $postalCode = faker()->numerify('#####');
        $validator = new Validator(['postal_code' => $postalCode]);
        $validator->rules(['postal_code' => 'regex:/^\d{5}$/']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule validates alphanumeric pattern.
     */
    test('regex passes for alphanumeric pattern', function () {
        $code = faker()->bothify('???###');
        $validator = new Validator(['code' => strtoupper($code)]);
        $validator->rules(['code' => 'regex:/^[A-Z0-9]+$/']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule validates complex password pattern with lookaheads.
     *
     * Pattern requires: uppercase, lowercase, digit, special char, min 8 length.
     */
    test('regex passes for complex password pattern', function () {
        $password = faker()->regexify('[A-Z][a-z]{3}[0-9]{2}[!@#$]{2}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#]).{8,}$/']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule fails when pattern parameter is missing.
     *
     * Require explicit regex pattern to prevent ambiguous validation.
     */
    test('regex fails when parameter missing', function () {
        $validator = new Validator(['field' => faker()->word()]);
        $validator->rules(['field' => 'regex']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify regex rule passes for empty string when not required.
     *
     * Allow empty values - use 'required' rule to enforce presence.
     */
    test('regex passes for empty string when not required', function () {
        $validator = new Validator(['field' => '']);
        $validator->rules(['field' => 'regex:/^\d+$/']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule passes for null when not required.
     */
    test('regex passes for null when not required', function () {
        $validator = new Validator(['field' => null]);
        $validator->rules(['field' => 'regex:/^\d+$/']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule supports Unicode character classes.
     *
     * Use \p{L} to match any Unicode letter for international support.
     */
    test('regex works with Unicode patterns', function () {
        $validator = new Validator(['name' => 'Jöhn']);
        $validator->rules(['name' => 'regex:/^[\p{L}]+$/u']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test regex rule fails when Unicode pattern doesn't match.
     */
    test('regex fails for invalid Unicode', function () {
        $name = faker()->firstName().faker()->numberBetween(1, 999);
        $validator = new Validator(['name' => $name]);
        $validator->rules(['name' => 'regex:/^[\p{L}]+$/u']);

        expect($validator->fails())->toBeTrue();
    });

    // ==================== Search Query Rule ====================

    /**
     * Test search_query rule passes for simple text queries.
     */
    test('search_query passes for simple text', function () {
        $validator = new Validator(['q' => faker()->words(3, true)]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for text with numbers.
     */
    test('search_query passes for text with numbers', function () {
        $query = faker()->word().' '.faker()->randomFloat(1, 1, 10).' '.faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for email-like text.
     */
    test('search_query passes for text with special characters', function () {
        $validator = new Validator(['q' => faker()->email()]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for questions.
     */
    test('search_query passes for text with punctuation', function () {
        $query = faker()->sentence().'?';
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule supports Unicode characters.
     *
     * Allow international characters for multilingual search.
     */
    test('search_query passes for Unicode text', function () {
        $validator = new Validator(['q' => 'Überraschung café']);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for quoted phrases.
     *
     * Support exact phrase matching in search queries.
     */
    test('search_query passes for quotes', function () {
        $query = '"'.faker()->words(2, true).'"';
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for slug-like text.
     */
    test('search_query passes for dashes and underscores', function () {
        $query = faker()->slug(3);
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for text with parentheses.
     */
    test('search_query passes for parentheses', function () {
        $query = faker()->word().' ('.faker()->word().')';
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for text with currency symbols.
     */
    test('search_query passes for symbols', function () {
        $query = 'price: $'.faker()->numberBetween(10, 1000).' & more';
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for multiline text.
     *
     * Allow newlines and tabs as they are legitimate search characters.
     */
    test('search_query passes for newlines and tabs', function () {
        $query = faker()->word()."\n".faker()->word()."\t".faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule fails for null byte injection.
     *
     * Block null bytes as they can bypass security filters or terminate strings.
     */
    test('search_query fails for null byte', function () {
        $query = faker()->word()."\x00".faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['q'][0])->toBe('Q contains invalid characters.');
    });

    /**
     * Test search_query rule fails for control characters.
     *
     * Block ASCII control characters (0x01-0x08) that have no legitimate use.
     */
    test('search_query fails for control characters', function () {
        $query = faker()->word()."\x01".faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test search_query rule fails for vertical tab character.
     *
     * Block 0x0B as it can interfere with string processing.
     */
    test('search_query fails for vertical tab', function () {
        $query = faker()->word()."\x0B".faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test search_query rule fails for form feed character.
     *
     * Block 0x0C as it can interfere with output rendering.
     */
    test('search_query fails for form feed', function () {
        $query = faker()->word()."\x0C".faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Test search_query rule fails for delete character.
     *
     * Block 0x7F as it can cause terminal/display issues.
     */
    test('search_query fails for delete character', function () {
        $query = faker()->word()."\x7F".faker()->word();
        $validator = new Validator(['q' => $query]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->fails())->toBeTrue();
    });

    /**
     * Verify search_query rule passes for empty string when not required.
     *
     * Allow empty search - use 'required' rule to enforce presence.
     */
    test('search_query passes for empty string when not required', function () {
        $validator = new Validator(['q' => '']);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query rule passes for whitespace-only string when not required.
     */
    test('search_query passes for whitespace-only string when not required', function () {
        $validator = new Validator(['q' => '   ']);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    });

    /**
     * Test search_query combined with required rule enforces presence.
     */
    test('search_query with required rule enforces presence', function () {
        $validator = new Validator(['q' => '']);
        $validator->rules(['q' => 'required|search_query']);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['q'][0])->toContain('required');
    });

    /**
     * Test search_query rule allows SQL injection attempts.
     *
     * Accept SQL syntax because injection prevention happens at database
     * layer via parameterized queries, not input validation.
     */
    test('search_query allows SQL injection attempts', function ($sqlInjection) {
        $validator = new Validator(['q' => $sqlInjection]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    })->with('sql_injection_attempts');

    /**
     * Test search_query rule allows XSS payloads.
     *
     * Accept HTML/JS syntax because XSS prevention happens at output
     * layer via proper escaping, not input validation.
     */
    test('search_query allows XSS payloads', function ($xssPayload) {
        $validator = new Validator(['q' => $xssPayload]);
        $validator->rules(['q' => 'search_query']);

        expect($validator->passes())->toBeTrue();
    })->with('xss_payloads');

});
