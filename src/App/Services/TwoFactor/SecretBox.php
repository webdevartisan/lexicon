<?php

declare(strict_types=1);

namespace App\Services\TwoFactor;

/**
 * Seals authenticator secrets before they reach the database, so a copy of the
 * users table alone cannot generate anyone's codes. The key is derived from
 * APP_KEY with a purpose label, the same way the sign-in check derives its own.
 */
final class SecretBox
{
    private const CIPHER = 'aes-256-gcm';

    private string $key;

    public function __construct(string $appKey)
    {
        $this->key = hash_hkdf('sha256', $appKey, 32, 'two-factor-secret');
    }

    public function seal(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv.$tag.$cipher);
    }

    /**
     * The original text, or null when the value was not sealed with this key.
     */
    public function open(string $sealed): ?string
    {
        $raw = base64_decode($sealed, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }

        $plain = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? null : $plain;
    }
}
