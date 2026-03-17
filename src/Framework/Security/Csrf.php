<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Session;
use RuntimeException;

/**
 * CSRF protection service.
 *
 * Generates and validates CSRF tokens backed by the Session service.
 * Controllers or middleware should call assertValid() early in unsafe
 * HTTP method handlers (POST/PUT/PATCH/DELETE).
 */
final class Csrf
{
    /**
     * Session key used to store the CSRF token.
     */
    private const SESSION_KEY = '_csrf_token';

    /**
     * Session abstraction is injected so the CSRF service
     * benefits from hardened cookie flags and strict session mode.
     */
    public function __construct(
        private Session $session
    ) {}

    /**
     * Get the current CSRF token for this session.
     *
     * If no token is present yet, generate a new 256-bit random value,
     * store it in session, and return it.
     */
    public function getToken(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            // 32 bytes of randomness → 64 hex characters.
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Check whether the provided token matches the session token.
     *
     * Returns false if either the stored token or the provided token
     * is missing or invalid.
     */
    public function isTokenValid(?string $token): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        // Constant-time comparison mitigates timing side-channel leaks.
        return hash_equals($stored, $token);
    }

    /**
     * Assert that a token is valid, or throw an exception.
     *
     * Controllers can call this at the very start of an action before
     * reading or mutating any user-specific state.
     *
     * @throws RuntimeException When the token is invalid or missing.
     */
    public function assertValid(?string $token): void
    {
        if (!$this->isTokenValid($token)) {
            // This can later become a framework-specific HTTP exception
            // with a 419/403 status code and a dedicated error page.
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}
