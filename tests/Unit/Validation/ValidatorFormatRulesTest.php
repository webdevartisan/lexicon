<?php

use Framework\Validation\Validator;

/**
 * Unit test suite for Validator format-based rules.
 *
 * Tests rules that validate specific data formats: emails, URLs,
 * numeric values, dates, and timezone identifiers.
 * All tests run in isolation without database or external dependencies.
 */

describe('Validator Format Rules', function () {
    
    // ==================== Email Rule ====================
    
    /**
     * Verify email rule passes for standard email format.
     */
    test('email passes for valid email', function () {
        $validator = new Validator(['email' => faker()->email()]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test email rule passes for email with subdomain.
     */
    test('email passes for email with subdomain', function () {
        $validator = new Validator(['email' => faker()->safeEmail()]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test email rule passes for email with plus sign (RFC 5233).
     * 
     * Plus addressing is used for email filtering and tracking.
     */
    test('email passes for email with plus', function () {
        $email = faker()->userName() . '+tag@' . faker()->freeEmailDomain();
        $validator = new Validator(['email' => $email]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test email rule passes for email with dots in local part.
     */
    test('email passes for email with dots', function () {
        $email = faker()->firstName() . '.' . faker()->lastName() . '@' . faker()->freeEmailDomain();
        $validator = new Validator(['email' => strtolower($email)]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test email rule fails for various invalid formats using dataset.
     * 
     * Use comprehensive dataset to catch edge cases and attack vectors.
     */
    test('email fails for invalid formats', function ($invalidEmail) {
        $validator = new Validator(['email' => $invalidEmail]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['email'][0])->toBe('Email must be a valid email address.');
    })->with('invalid_emails');
    
    /**
     * Test email rule fails for missing at symbol.
     */
    test('email fails for missing at symbol', function () {
        $email = faker()->userName() . faker()->freeEmailDomain();
        $validator = new Validator(['email' => $email]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test email rule fails for missing domain.
     */
    test('email fails for missing domain', function () {
        $email = faker()->userName() . '@';
        $validator = new Validator(['email' => $email]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify email rule passes for empty string when not required.
     * 
     * Allow empty values - use 'required' rule to enforce presence.
     */
    test('email passes for empty string when not required', function () {
        $validator = new Validator(['email' => '']);
        $validator->rules(['email' => 'email']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test email rule passes for null when not required.
     */
    test('email passes for null when not required', function () {
        $validator = new Validator(['email' => null]);
        $validator->rules(['email' => 'email']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== URL Rule ====================
    
    /**
     * Test URL rule passes for valid HTTP URL.
     */
    test('url passes for valid http URL', function () {
        $validator = new Validator(['website' => 'http://' . faker()->domainName()]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test URL rule passes for valid HTTPS URL.
     */
    test('url passes for valid https URL', function () {
        $validator = new Validator(['website' => faker()->url()]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test URL rule passes for URL with path segments.
     */
    test('url passes for URL with path', function () {
        $url = faker()->url() . '/' . faker()->slug(3);
        $validator = new Validator(['website' => $url]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test URL rule passes for URL with query string parameters.
     */
    test('url passes for URL with query string', function () {
        $url = faker()->url() . '?key=' . faker()->word() . '&value=' . faker()->numberBetween(1, 100);
        $validator = new Validator(['website' => $url]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test URL rule passes for URL with custom port.
     * 
     * Support non-standard ports for development and internal services.
     */
    test('url passes for URL with port', function () {
        $port = faker()->numberBetween(3000, 9999);
        $url = 'http://' . faker()->domainName() . ':' . $port;
        $validator = new Validator(['website' => $url]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test URL rule fails for invalid format.
     */
    test('url fails for invalid format', function () {
        $validator = new Validator(['website' => faker()->word()]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['website'][0])->toBe('Website must be a valid URL.');
    });
    
    /**
     * Test URL rule fails for URL without protocol.
     * 
     * Require explicit protocol to prevent ambiguity and security issues.
     */
    test('url fails for URL without protocol', function () {
        $validator = new Validator(['website' => faker()->domainName()]);
        $validator->rules(['website' => 'url']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify URL rule passes for empty string when not required.
     */
    test('url passes for empty string when not required', function () {
        $validator = new Validator(['website' => '']);
        $validator->rules(['website' => 'url']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Numeric Rule ====================
    
    /**
     * Test numeric rule passes for integer values.
     */
    test('numeric passes for integer', function () {
        $validator = new Validator(['price' => faker()->numberBetween(1, 1000)]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test numeric rule passes for float values.
     */
    test('numeric passes for float', function () {
        $validator = new Validator(['price' => faker()->randomFloat(2, 0, 999)]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test numeric rule passes for numeric string representation.
     */
    test('numeric passes for numeric string', function () {
        $price = (string) faker()->randomFloat(2, 0, 999);
        $validator = new Validator(['price' => $price]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test numeric rule passes for negative numbers.
     */
    test('numeric passes for negative number', function () {
        $validator = new Validator(['price' => faker()->numberBetween(-100, -1)]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test numeric rule passes for zero value.
     */
    test('numeric passes for zero', function () {
        $validator = new Validator(['price' => 0]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test numeric rule fails for non-numeric string.
     */
    test('numeric fails for non-numeric string', function () {
        $validator = new Validator(['price' => faker()->word()]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['price'][0])->toBe('Price must be a number.');
    });
    
    /**
     * Test numeric rule fails for alphanumeric string.
     */
    test('numeric fails for alphanumeric string', function () {
        $value = faker()->numberBetween(1, 999) . faker()->randomLetter();
        $validator = new Validator(['price' => $value]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify numeric rule passes for empty string when not required.
     */
    test('numeric passes for empty string when not required', function () {
        $validator = new Validator(['price' => '']);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test numeric rule passes for null when not required.
     */
    test('numeric passes for null when not required', function () {
        $validator = new Validator(['price' => null]);
        $validator->rules(['price' => 'numeric']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Integer Rule ====================
    
    /**
     * Test integer rule passes for positive integer.
     */
    test('integer passes for positive integer', function () {
        $validator = new Validator(['age' => faker()->numberBetween(1, 100)]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test integer rule passes for negative integer.
     */
    test('integer passes for negative integer', function () {
        $validator = new Validator(['age' => faker()->numberBetween(-100, -1)]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test integer rule passes for zero value.
     */
    test('integer passes for zero', function () {
        $validator = new Validator(['age' => 0]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test integer rule passes for integer string representation.
     */
    test('integer passes for integer string', function () {
        $age = (string) faker()->numberBetween(1, 100);
        $validator = new Validator(['age' => $age]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test integer rule fails for float values.
     * 
     * Reject decimals to ensure strict integer validation.
     */
    test('integer fails for float', function () {
        $validator = new Validator(['age' => faker()->randomFloat(1, 1, 100)]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['age'][0])->toBe('Age must be an integer.');
    });
    
    /**
     * Test integer rule fails for float string.
     */
    test('integer fails for float string', function () {
        // Generate a float with guaranteed non-zero decimal part
        $floatValue = faker()->randomFloat(1, 1, 99) + 0.5;
        $age = (string) $floatValue;
        $validator = new Validator(['age' => $age]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test integer rule fails for non-numeric string.
     */
    test('integer fails for non-numeric string', function () {
        $validator = new Validator(['age' => faker()->word()]);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify integer rule passes for empty string when not required.
     */
    test('integer passes for empty string when not required', function () {
        $validator = new Validator(['age' => '']);
        $validator->rules(['age' => 'integer']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Datetime Rule ====================
    
    /**
     * Test datetime rule passes for valid date with custom format.
     */
    test('datetime passes for valid date with format', function () {
        $date = faker()->dateTimeBetween('-1 year', 'now');
        $formatted = $date->format('d.m.Y H:i');
        $validator = new Validator(['published_at' => $formatted]);
        $validator->rules(['published_at' => 'datetime:d.m.Y H:i']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test datetime rule passes for ISO 8601 format.
     * 
     * Support standard datetime format for API compatibility.
     */
    test('datetime passes for ISO format', function () {
        $date = faker()->dateTimeBetween('-1 year', 'now');
        $formatted = $date->format('Y-m-d H:i:s');
        $validator = new Validator(['created_at' => $formatted]);
        $validator->rules(['created_at' => 'datetime:Y-m-d H:i:s']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test datetime rule passes for date-only format.
     */
    test('datetime passes for date only', function () {
        $date = faker()->dateTimeBetween('-50 years', '-18 years');
        $formatted = $date->format('Y-m-d');
        $validator = new Validator(['birth_date' => $formatted]);
        $validator->rules(['birth_date' => 'datetime:Y-m-d']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test datetime rule fails when format doesn't match expected pattern.
     */
    test('datetime fails for wrong format', function () {
        $date = faker()->date('Y-m-d');
        $validator = new Validator(['published_at' => $date]);
        $validator->rules(['published_at' => 'datetime:d.m.Y H:i']);
        
        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()['published_at'][0])->toContain('d.m.Y H:i');
    });
    
    /**
     * Test datetime rule fails for invalid date component values.
     * 
     * Reject impossible dates like month 13 or hour 25.
     */
    test('datetime fails for invalid date values', function () {
        $validator = new Validator(['published_at' => '32.13.2024 25:70']);
        $validator->rules(['published_at' => 'datetime:d.m.Y H:i']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test datetime rule fails for date overflow.
     * 
     * Detect invalid dates like February 31st via strict validation.
     */
    test('datetime fails for date overflow', function () {
        $validator = new Validator(['published_at' => '31.02.2024 12:00']);
        $validator->rules(['published_at' => 'datetime:d.m.Y H:i']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test datetime rule fails when format parameter is missing.
     * 
     * Require explicit format to prevent ambiguous date parsing.
     */
    test('datetime fails when format parameter missing', function () {
        $date = faker()->date();
        $validator = new Validator(['published_at' => $date]);
        $validator->rules(['published_at' => 'datetime']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify datetime rule passes for empty string when not required.
     */
    test('datetime passes for empty string when not required', function () {
        $validator = new Validator(['published_at' => '']);
        $validator->rules(['published_at' => 'datetime:Y-m-d']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    
    // ==================== Timezone Rule ====================
    
    /**
     * Test timezone rule passes for valid IANA timezone identifier.
     */
    test('timezone passes for valid timezone', function () {
        $validator = new Validator(['timezone' => faker()->timezone()]);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test timezone rule passes for UTC timezone.
     */
    test('timezone passes for UTC', function () {
        $validator = new Validator(['timezone' => 'UTC']);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test timezone rule passes for US timezone.
     */
    test('timezone passes for America/New_York', function () {
        $validator = new Validator(['timezone' => 'America/New_York']);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test timezone rule passes for European timezone.
     */
    test('timezone passes for Europe/Athens', function () {
        $validator = new Validator(['timezone' => 'Europe/Athens']);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->passes())->toBeTrue();
    });
    
    /**
     * Test timezone rule fails for invalid timezone identifier.
     */
    test('timezone fails for invalid timezone', function () {
        $validator = new Validator(['timezone' => 'Invalid/Timezone']);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Test timezone rule fails for timezone abbreviation.
     * 
     * Reject abbreviations as they are ambiguous (EST can be multiple timezones).
     */
    test('timezone fails for abbreviation', function () {
        $validator = new Validator(['timezone' => 'EST']);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->fails())->toBeTrue();
    });
    
    /**
     * Verify timezone rule passes for empty string when not required.
     */
    test('timezone passes for empty string when not required', function () {
        $validator = new Validator(['timezone' => '']);
        $validator->rules(['timezone' => 'timezone']);
        
        expect($validator->passes())->toBeTrue();
    });
    
});
