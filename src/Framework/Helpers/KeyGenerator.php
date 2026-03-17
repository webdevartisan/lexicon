<?php

declare(strict_types=1);

namespace Framework\Helpers;

/**
 * Application Key Generator
 *
 * We generate cryptographically secure application keys for encryption,
 * session management, and CSRF token generation.
 */
class KeyGenerator
{
    /**
     * Generate a random application key.
     *
     * We use random_bytes() for cryptographically secure randomness,
     * then encode it as base64 for storage in .env files.
     *
     * @param  int  $length  Key length in bytes (default: 32 for AES-256)
     * @return string Base64-encoded key
     */
    public static function generate(int $length = 32): string
    {
        return base64_encode(random_bytes($length));
    }

    /**
     * Generate a formatted key ready for .env file.
     *
     * @param  int  $length  Key length in bytes
     * @return string Key with base64: prefix
     */
    public static function generateForEnv(int $length = 32): string
    {
        return 'base64:'.self::generate($length);
    }
}
