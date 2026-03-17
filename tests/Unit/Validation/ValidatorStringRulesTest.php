<?php

use Framework\Validation\Validator;

/**
 * Unit test suite for Validator string-based rules.
 *
 * Tests rules that validate string content: length constraints,
 * character composition, and format requirements for text fields.
 * All tests run in isolation without database or external dependencies.
 */

describe('Validator String Rules', function () {
    
    // ==================== Required Rule ====================
    
    /**
     * Verify required rule passes for non-empty string values.
     */
    test('required passes for non-empty string', function () {
        $validator = new Validator(['name' => faker()->name()]);
        $validator->rules(['name' => 'required']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test required rule fails for empty string.
     */
    test('required fails for empty string', function () {
        $validator = new Validator(['name' => '']);
        $validator->rules(['name' => 'required']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['name'][0])->toBe('Name is required.');
    });
    
    /**
     * Verify required rule trims whitespace before validation.
     * 
     * Reject whitespace-only input to prevent users bypassing
     * required field validation with spaces.
     */
    test('required fails for whitespace-only string', function () {
        $validator = new Validator(['name' => '   ']);
        $validator->rules(['name' => 'required']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test required rule handles null values.
     */
    test('required fails for null', function () {
        $validator = new Validator(['name' => null]);
        $validator->rules(['name' => 'required']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify required rule fails when field is missing from data.
     */
    test('required fails for missing field', function () {
        $validator = new Validator([]);
        $validator->rules(['name' => 'required']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test required rule passes for string '0'.
     * 
     * String zero is a valid value (e.g., quantity fields, postal codes).
     */
    test('required passes for string zero', function () {
        $validator = new Validator(['count' => '0']);
        $validator->rules(['count' => 'required']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test required rule passes for numeric zero.
     * 
     * Numeric zero is a valid value (e.g., price, rating fields).
     */
    test('required passes for numeric zero', function () {
        $validator = new Validator(['count' => 0]);
        $validator->rules(['count' => 'required']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Min Rule ====================
    
    /**
     * Verify min rule passes when string meets exact minimum length.
     */
    test('min passes for string meeting length', function () {
        $password = faker()->regexify('[A-Za-z0-9]{8}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'min:8']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test min rule passes when string exceeds minimum length.
     */
    test('min passes for string exceeding length', function () {
        $password = faker()->regexify('[A-Za-z0-9]{15}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'min:8']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test min rule fails when string is below minimum length.
     */
    test('min fails for string below length', function () {
        $password = faker()->regexify('[A-Za-z0-9]{5}');
        $validator = new Validator(['password' => $password]);
        $validator->rules(['password' => 'min:8']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['password'][0])->toBe('Password must be at least 8 characters.');
    });
    
    /**
     * Verify min rule uses multibyte string length (mb_strlen).
     * 
     * Use mb_strlen to correctly count Unicode characters.
     * Example: 'Ñoño' = 4 characters, not 6 bytes.
     */
    test('min uses multibyte string length', function () {
        $validator = new Validator(['name' => 'Ñoño']);
        $validator->rules(['name' => 'min:4']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test min rule skips validation for non-string types.
     * 
     * Only validate string length, not numeric values.
     */
    test('min passes for non-string values', function () {
        $validator = new Validator(['count' => 123]);
        $validator->rules(['count' => 'min:5']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Max Rule ====================
    
    /**
     * Verify max rule passes when string is within limit.
     */
    test('max passes for string within limit', function () {
        $bio = faker()->sentence(5);
        $validator = new Validator(['bio' => $bio]);
        $validator->rules(['bio' => 'max:100']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test max rule passes when string is at exact limit.
     */
    test('max passes for string at exact limit', function () {
        $code = faker()->regexify('[A-Z0-9]{5}');
        $validator = new Validator(['code' => $code]);
        $validator->rules(['code' => 'max:5']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test max rule fails when string exceeds limit.
     */
    test('max fails for string exceeding limit', function () {
        $code = faker()->regexify('[A-Z0-9]{10}');
        $validator = new Validator(['code' => $code]);
        $validator->rules(['code' => 'max:5']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['code'][0])->toBe('Code must not exceed 5 characters.');
    });
    
    /**
     * Verify max rule uses multibyte string length (mb_strlen).
     * 
     * Correctly count Unicode characters, not bytes.
     */
    test('max uses multibyte string length', function () {
        $validator = new Validator(['name' => 'Ñoño']);
        $validator->rules(['name' => 'max:3']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test max rule skips validation for non-string types.
     */
    test('max passes for non-string values', function () {
        $validator = new Validator(['count' => 123]);
        $validator->rules(['count' => 'max:2']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Alpha Rule ====================
    
    /**
     * Verify alpha rule passes for letters only (no spaces).
     */
    test('alpha passes for letters only', function () {
        $validator = new Validator(['name' => faker()->regexify('[A-Za-z]{8}')]);
        $validator->rules(['name' => 'alpha']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test alpha rule fails when string contains numbers.
     */
    test('alpha fails for letters with numbers', function () {
        $name = faker()->firstName() . faker()->numberBetween(1, 999);
        $validator = new Validator(['name' => $name]);
        $validator->rules(['name' => 'alpha']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['name'][0])->toBe('Name may only contain letters.');
    });
    
    /**
     * Test alpha rule fails when string contains spaces.
     */
    test('alpha fails for letters with spaces', function () {
        $validator = new Validator(['name' => faker()->name()]);
        $validator->rules(['name' => 'alpha']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test alpha rule fails for special characters.
     */
    test('alpha fails for special characters', function () {
        $name = faker()->firstName() . '-' . faker()->lastName();
        $validator = new Validator(['name' => $name]);
        $validator->rules(['name' => 'alpha']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify alpha rule passes for empty string (not required).
     * 
     * Allow empty values - use 'required' rule to enforce presence.
     */
    test('alpha passes for empty string', function () {
        $validator = new Validator(['name' => '']);
        $validator->rules(['name' => 'alpha']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== AlphaNum Rule ====================
    
    /**
     * Test alpha_num rule passes for letters and numbers.
     */
    test('alpha_num passes for letters and numbers', function () {
        $username = faker()->userName();
        $validator = new Validator(['username' => str_replace(['-', '_', '.'], '', $username)]);
        $validator->rules(['username' => 'alpha_num']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test alpha_num rule passes for letters only.
     */
    test('alpha_num passes for letters only', function () {
        $validator = new Validator(['username' => faker()->regexify('[a-z]{8}')]);
        $validator->rules(['username' => 'alpha_num']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test alpha_num rule passes for numbers only.
     */
    test('alpha_num passes for numbers only', function () {
        $validator = new Validator(['username' => (string) faker()->numberBetween(10000, 99999)]);
        $validator->rules(['username' => 'alpha_num']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test alpha_num rule fails when string contains spaces.
     */
    test('alpha_num fails for spaces', function () {
        $username = faker()->firstName() . ' ' . faker()->numberBetween(1, 999);
        $validator = new Validator(['username' => $username]);
        $validator->rules(['username' => 'alpha_num']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['username'][0])->toBe('Username may only contain letters and numbers.');
    });
    
    /**
     * Test alpha_num rule fails for special characters.
     */
    test('alpha_num fails for special characters', function () {
        $username = faker()->userName(). '_'; // May contain hyphens/underscores
        $validator = new Validator(['username' => $username]);
        $validator->rules(['username' => 'alpha_num']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify alpha_num rule passes for empty string (not required).
     */
    test('alpha_num passes for empty string', function () {
        $validator = new Validator(['username' => '']);
        $validator->rules(['username' => 'alpha_num']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Slug Rule ====================
    
    /**
     * Test slug rule passes for valid lowercase slug with hyphens.
     */
    test('slug passes for valid lowercase slug', function () {
        $validator = new Validator(['slug' => faker()->slug(3)]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test slug rule passes for slug with numbers.
     */
    test('slug passes for slug with numbers', function () {
        $slug = faker()->slug(2) . '-' . faker()->numberBetween(1, 999);
        $validator = new Validator(['slug' => $slug]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test slug rule passes for single word (no hyphens).
     */
    test('slug passes for single word', function () {
        $validator = new Validator(['slug' => faker()->word()]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Verify slug rule fails for uppercase letters.
     * 
     * Enforce lowercase for URL consistency and SEO best practices.
     */
    test('slug fails for uppercase letters', function () {
        $slug = ucfirst(faker()->slug(2));
        $validator = new Validator(['slug' => $slug]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test slug rule fails when string contains spaces.
     */
    test('slug fails for spaces', function () {
        $slug = str_replace('-', ' ', faker()->slug(3));
        $validator = new Validator(['slug' => $slug]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test slug rule fails for underscores.
     * 
     * Only allow hyphens for URL-safe slugs (RFC 3986).
     */
    test('slug fails for underscores', function () {
        $slug = str_replace('-', '_', faker()->slug(3));
        $validator = new Validator(['slug' => $slug]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test slug rule fails for leading hyphen.
     * 
     * Prevent malformed URLs and ambiguous routing.
     */
    test('slug fails for leading hyphen', function () {
        $validator = new Validator(['slug' => '-' . faker()->slug(2)]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test slug rule fails for trailing hyphen.
     */
    test('slug fails for trailing hyphen', function () {
        $validator = new Validator(['slug' => faker()->slug(2) . '-']);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test slug rule fails for consecutive hyphens.
     * 
     * Prevent ugly URLs and potential routing issues.
     */
    test('slug fails for consecutive hyphens', function () {
        $validator = new Validator(['slug' => faker()->word() . '--' . faker()->word()]);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test slug rule requires non-empty value.
     * 
     * Unlike other rules, slug is required (can't be empty).
     */
    test('slug fails for empty string', function () {
        $validator = new Validator(['slug' => '']);
        $validator->rules(['slug' => 'slug']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    
    // ==================== Title Rule ====================
    
    /**
     * Test title rule passes for regular title with spaces.
     */
    test('title passes for regular title', function () {
        $validator = new Validator(['title' => faker()->sentence(4)]);
        $validator->rules(['title' => 'title']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test title rule passes for title containing numbers.
     */
    test('title passes for title with numbers', function () {
        $title = 'Top ' . faker()->numberBetween(5, 20) . ' ' . faker()->words(2, true);
        $validator = new Validator(['title' => $title]);
        $validator->rules(['title' => 'title']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test title rule passes for punctuation marks.
     */
    test('title passes for title with punctuation', function () {
        $title = faker()->words(3, true) . ': ' . faker()->sentence(3);
        $validator = new Validator(['title' => $title]);
        $validator->rules(['title' => 'title']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test title rule passes for special characters (currency, symbols).
     */
    test('title passes for title with special characters', function () {
        $validator = new Validator(['title' => 'Cost: $100 & Benefits']);
        $validator->rules(['title' => 'title']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Verify title rule supports Unicode characters.
     * 
     * Allow international characters for multilingual blog support.
     */
    test('title passes for Unicode characters', function () {
        $validator = new Validator(['title' => 'Überraschung für René']);
        $validator->rules(['title' => 'title']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test title rule requires non-empty value.
     * 
     * Unlike other rules, title is required (can't be empty).
     */
    test('title fails for empty string', function () {
        $validator = new Validator(['title' => '']);
        $validator->rules(['title' => 'title']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test title rule fails for whitespace-only input.
     * 
     * Trim and reject whitespace to prevent empty titles in database.
     */
    test('title fails for whitespace only', function () {
        $validator = new Validator(['title' => '   ']);
        $validator->rules(['title' => 'title']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    
    // ==================== Security: Unicode Edge Cases ====================
    
    /**
     * Test string rules handle Unicode edge cases gracefully.
     * 
     * Verify validator doesn't crash on Unicode exploits:
     * - Homoglyphs (look-alike characters)
     * - Zero-width characters
     * - RTL override attacks
     * - Emoji and surrogate pairs
     * - Combining diacritical marks
     * 
     * These inputs should be processed without errors, though they
     * may fail validation rules (which is expected behavior).
     */
    test('it handles Unicode edge cases in string validation', function ($unicodeEdgeCase) {
        $validator = new Validator(['text' => $unicodeEdgeCase]);
        $validator->rules(['text' => 'required']);
        
        // Should process without crashing
        $validator->passes();
        
        expect($validator->errors())->toBeArray();
    })->with('unicode_edge_cases');
    
});
