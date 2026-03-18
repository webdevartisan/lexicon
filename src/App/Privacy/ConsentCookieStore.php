<?php

declare(strict_types=1);

namespace App\Privacy;

/**
 * Signed, HttpOnly consent cookie store.
 *
 * The consent cookie is:
 * - Signed with HMAC (SHA-256) using a secret (APP_KEY-backed)
 * - HttpOnly to prevent JavaScript access
 * - Marked SameSite=Lax by default to reduce CSRF risk
 */
final class ConsentCookieStore
{
    public function __construct(
        private readonly string $cookieName,
        private readonly int $ttlDays,
        private readonly string $secret,
    ) {}

    public function read(): ?Consent
    {
        $raw = $_COOKIE[$this->cookieName] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        [$b64, $sig] = array_pad(explode('.', $raw, 2), 2, null);
        if (!is_string($b64) || !is_string($sig) || $b64 === '' || $sig === '') {
            return null;
        }

        $json = base64_decode($b64, true);
        if ($json === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $json, $this->secret);
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        return Consent::fromPayload($payload);
    }

    /**
     * Encode a Consent object into a signed cookie value.
     *
     * Extracted from write() to allow direct testing without relying on
     * headers_list(), which is unavailable in CLI environments.
     *
     * @param  Consent  $consent  Consent object to encode
     * @return string Signed cookie value in format: base64(json).hmac
     */
    public function encodeCookieValue(Consent $consent): string
    {
        $json = json_encode($consent->toPayload(), JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $json, $this->secret);

        return base64_encode($json).'.'.$sig;
    }

    public function write(Consent $consent): void
    {
        $value = $this->encodeCookieValue($consent);
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie($this->cookieName, $value, [
            'expires' => time() + ($this->ttlDays * 86400),
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function clear(): void
    {
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie($this->cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
