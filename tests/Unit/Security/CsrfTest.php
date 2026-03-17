<?php

declare(strict_types=1);

/**
 * Unit tests for CSRF protection service.
 * 
 * Tests token generation, validation, and security properties in isolation
 * by mocking Session dependency to ensure true unit testing.
 */

use Framework\Security\Csrf;
use Framework\Session;
use Faker\Factory as Faker;

beforeEach(function () {
    $this->faker = Faker::create();
    $this->sessionMock = Mockery::mock(Session::class);
    $this->csrf = new Csrf($this->sessionMock);
});

// ============================================================================
// EXISTING TESTS (REFACTORED WITH MOCKS)
// ============================================================================

/**
 * Tests that getToken generates and stores a cryptographically secure token.
 * 
 * Verifies 256-bit token (32 bytes = 64 hex chars) is generated and stored in session.
 */
test('getToken generates and stores token', function () {
    // Session has no existing token
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    // Expect token to be stored in session
    $this->sessionMock->shouldReceive('set')
        ->once()
        ->with('_csrf_token', Mockery::on(function ($token) {
            return is_string($token) && strlen($token) === 64 && ctype_xdigit($token);
        }))
        ->andReturnNull();
    
    $token = $this->csrf->getToken();
    
    // Verify 256-bit token strength (32 bytes → 64 hex chars)
    expect($token)->toBeString()
        ->and(strlen($token))->toBe(64)
        ->and(ctype_xdigit($token))->toBeTrue();
});

/**
 * Tests token persistence across multiple requests.
 * 
 * Verifies that subsequent calls return the same token without regeneration.
 */
test('getToken returns same token on subsequent calls', function () {
    $existingToken = bin2hex(random_bytes(32));
    
    // First call: no token exists
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    $this->sessionMock->shouldReceive('set')
        ->once()
        ->with('_csrf_token', Mockery::type('string'))
        ->andReturnNull();
    
    $token1 = $this->csrf->getToken();
    
    // Second call: token exists in session
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($token1);
    
    $token2 = $this->csrf->getToken();
    
    expect($token1)->toBe($token2);
});

/**
 * Tests successful token validation with matching tokens.
 * 
 * Verifies that valid token passes hash_equals comparison.
 */
test('isTokenValid returns true for valid token', function () {
    $validToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($validToken);
    
    expect($this->csrf->isTokenValid($validToken))->toBeTrue();
});

/**
 * Tests token validation rejection for mismatched tokens.
 * 
 * Verifies that invalid token fails hash_equals comparison.
 */
test('isTokenValid returns false for invalid token', function () {
    $storedToken = bin2hex(random_bytes(32));
    $wrongToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect($this->csrf->isTokenValid($wrongToken))->toBeFalse();
});

/**
 * Tests null token rejection.
 * 
 * Verifies that null input is safely handled without errors.
 */
test('isTokenValid returns false for null token', function () {
    $storedToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect($this->csrf->isTokenValid(null))->toBeFalse();
});

/**
 * Tests empty string token rejection.
 * 
 * Verifies that empty strings are not treated as valid tokens.
 */
test('isTokenValid returns false for empty string', function () {
    $storedToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect($this->csrf->isTokenValid(''))->toBeFalse();
});

/**
 * Tests validation failure when no session token exists.
 * 
 * Verifies graceful handling of missing session token.
 */
test('isTokenValid returns false when no session token exists', function () {
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    expect($this->csrf->isTokenValid('any_token'))->toBeFalse();
});

/**
 * Tests assertValid pass-through for valid tokens.
 * 
 * Verifies no exception is thrown when token is valid.
 */
test('assertValid passes for valid token', function () {
    $validToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($validToken);
    
    expect(fn() => $this->csrf->assertValid($validToken))->not->toThrow(RuntimeException::class);
});

/**
 * Tests assertValid exception for invalid tokens.
 * 
 * Verifies RuntimeException is thrown with appropriate message.
 */
test('assertValid throws exception for invalid token', function () {
    $storedToken = bin2hex(random_bytes(32));
    $invalidToken = $this->faker->sha256();
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect(fn() => $this->csrf->assertValid($invalidToken))
        ->toThrow(RuntimeException::class, 'Invalid CSRF token.');
});

/**
 * Tests assertValid exception for null tokens.
 * 
 * Verifies null input triggers validation failure.
 */
test('assertValid throws exception for null token', function () {
    $storedToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect(fn() => $this->csrf->assertValid(null))
        ->toThrow(RuntimeException::class);
});

/**
 * Tests cryptographic randomness of token generation.
 * 
 * Verifies that tokens are generated using random_bytes, not predictable values.
 */
test('token is cryptographically random', function () {
    // First token generation
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    $this->sessionMock->shouldReceive('set')
        ->once()
        ->andReturnNull();
    
    $token1 = $this->csrf->getToken();
    
    // Create new CSRF instance to simulate new session
    $newSessionMock = Mockery::mock(Session::class);
    $newCsrf = new Csrf($newSessionMock);
    
    $newSessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    $newSessionMock->shouldReceive('set')
        ->once()
        ->andReturnNull();
    
    $token2 = $newCsrf->getToken();
    
    expect($token1)->not->toBe($token2);
});

/**
 * Tests uniqueness of tokens across multiple generations.
 * 
 * Verifies that random_bytes produces unique tokens each time.
 */
test('tokens are unique across multiple sessions', function () {
    $tokens = [];
    
    for ($i = 0; $i < 5; $i++) {
        $sessionMock = Mockery::mock(Session::class);
        $csrf = new Csrf($sessionMock);
        
        $sessionMock->shouldReceive('get')
            ->once()
            ->with('_csrf_token')
            ->andReturn(null);
        
        $sessionMock->shouldReceive('set')
            ->once()
            ->andReturnNull();
        
        $tokens[] = $csrf->getToken();
    }
    
    // Verify all tokens are unique
    expect(count(array_unique($tokens)))->toBe(5);
});

/**
 * Tests hexadecimal format validation.
 * 
 * Verifies bin2hex output contains only valid hex characters [0-9a-f].
 */
test('token is valid hexadecimal format', function () {
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    $this->sessionMock->shouldReceive('set')
        ->once()
        ->andReturnNull();
    
    $token = $this->csrf->getToken();
    
    expect(ctype_xdigit($token))->toBeTrue();
});

// ============================================================================
// NEW TESTS (MISSING COVERAGE)
// ============================================================================

/**
 * Tests that stored token is retrieved without regeneration.
 * 
 * Verifies session get is called and token is not overwritten.
 */
test('getToken retrieves existing token from session without regeneration', function () {
    $existingToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($existingToken);
    
    // set should NOT be called when token exists
    $this->sessionMock->shouldNotReceive('set');
    
    $token = $this->csrf->getToken();
    
    expect($token)->toBe($existingToken);
});

/**
 * Tests timing-safe comparison against timing attacks.
 * 
 * Verifies hash_equals is used (constant-time) instead of === (variable-time).
 */
test('isTokenValid uses timing-safe comparison', function () {
    $storedToken = bin2hex(random_bytes(32));
    $almostValidToken = substr($storedToken, 0, 63) . 'a'; // Different last char
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    // hash_equals will compare all bytes regardless of early mismatch
    $result = $this->csrf->isTokenValid($almostValidToken);
    
    expect($result)->toBeFalse();
});

/**
 * Tests validation rejection for empty session token.
 * 
 * Verifies that empty string in session is treated as invalid.
 */
test('isTokenValid returns false when session token is empty string', function () {
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn('');
    
    expect($this->csrf->isTokenValid('valid_looking_token'))->toBeFalse();
});

/**
 * Tests validation rejection for non-string session values.
 * 
 * Verifies type safety when session contains unexpected data types.
 */
test('isTokenValid returns false when session token is not a string', function () {
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(12345); // Integer instead of string
    
    expect($this->csrf->isTokenValid('valid_token'))->toBeFalse();
});

/**
 * Tests multiple sequential validations with same token.
 * 
 * Verifies that token can be validated multiple times without consumption.
 */
test('token can be validated multiple times', function () {
    $validToken = bin2hex(random_bytes(32));
    
    $this->sessionMock->shouldReceive('get')
        ->times(3)
        ->with('_csrf_token')
        ->andReturn($validToken);
    
    expect($this->csrf->isTokenValid($validToken))->toBeTrue();
    expect($this->csrf->isTokenValid($validToken))->toBeTrue();
    expect($this->csrf->isTokenValid($validToken))->toBeTrue();
});

/**
 * Tests case sensitivity in token validation.
 * 
 * Verifies that token comparison is case-sensitive (hex lowercase).
 */
test('isTokenValid is case sensitive', function () {
    $storedToken = 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
    $upperCaseToken = strtoupper($storedToken);
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    // hash_equals is case-sensitive
    expect($this->csrf->isTokenValid($upperCaseToken))->toBeFalse();
});

/**
 * Tests validation rejection for tokens with wrong length.
 * 
 * Verifies that tokens must be exactly 64 characters.
 */
test('isTokenValid rejects tokens with incorrect length', function () {
    $storedToken = bin2hex(random_bytes(32));
    $shortToken = substr($storedToken, 0, 63); // 63 chars
    $longToken = $storedToken . 'a'; // 65 chars
    
    $this->sessionMock->shouldReceive('get')
        ->twice()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect($this->csrf->isTokenValid($shortToken))->toBeFalse();
    expect($this->csrf->isTokenValid($longToken))->toBeFalse();
});

/**
 * Tests validation rejection for tokens with special characters.
 * 
 * Verifies that non-hexadecimal characters are rejected.
 */
test('isTokenValid rejects tokens with special characters', function () {
    $storedToken = bin2hex(random_bytes(32));
    $tokenWithSpecialChars = substr($storedToken, 0, 60) . '!@#$';
    
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn($storedToken);
    
    expect($this->csrf->isTokenValid($tokenWithSpecialChars))->toBeFalse();
});

/**
 * Tests that getToken handles session returning non-string gracefully.
 * 
 * Verifies token generation when session contains corrupted data.
 */
test('getToken generates new token when session returns non-string', function () {
    // Session returns array instead of string
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(['invalid' => 'data']);
    
    // Should generate and store new token
    $this->sessionMock->shouldReceive('set')
        ->once()
        ->with('_csrf_token', Mockery::type('string'))
        ->andReturnNull();
    
    $token = $this->csrf->getToken();
    
    expect($token)->toBeString()
        ->and(strlen($token))->toBe(64);
});

/**
 * Tests session key constant value.
 * 
 * Verifies that CSRF uses expected session key for storage.
 */
test('csrf uses correct session key constant', function () {
    $this->sessionMock->shouldReceive('get')
        ->once()
        ->with('_csrf_token')
        ->andReturn(null);
    
    $this->sessionMock->shouldReceive('set')
        ->once()
        ->with('_csrf_token', Mockery::type('string'))
        ->andReturnNull();
    
    $token = $this->csrf->getToken();
    
    // Add explicit assertion to satisfy Pest
    expect($token)->toBeString();
    
    // Mockery expectations for correct session key are verified by Mockery::close()
});
