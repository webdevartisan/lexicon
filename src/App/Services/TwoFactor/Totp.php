<?php

declare(strict_types=1);

namespace App\Services\TwoFactor;

/**
 * Time-based one-time passwords as authenticator apps compute them (RFC 6238:
 * HMAC-SHA1, six digits, thirty second steps).
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const STEP_SECONDS = 30;

    private const DIGITS = 6;

    /** Steps either side of now that still count, for clocks that drift a little. */
    private const WINDOW = 1;

    /**
     * A new random secret, base32 encoded as authenticator apps expect.
     */
    public function generateSecret(): string
    {
        return $this->encode(random_bytes(20));
    }

    /**
     * The step number for a moment in time.
     */
    public function stepAt(int $timestamp): int
    {
        return intdiv($timestamp, self::STEP_SECONDS);
    }

    /**
     * The code an app would show for a secret at one step.
     */
    public function codeAt(string $secret, int $step): string
    {
        $counter = pack('N2', $step >> 32, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counter, $this->decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $number = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3])
        ) % (10 ** self::DIGITS);

        return str_pad((string) $number, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * The step a submitted code belongs to, or null when it matches none near now.
     *
     * Every step in the window is compared so the time taken does not tell which
     * one was close.
     */
    public function matchingStep(string $secret, string $code, int $now): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $current = $this->stepAt($now);
        $found = null;

        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
            if (hash_equals($this->codeAt($secret, $step), $code)) {
                $found = $step;
            }
        }

        return $found;
    }

    /**
     * The address a QR code carries so an app can add the account in one scan.
     */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits='.self::DIGITS.'&period='.self::STEP_SECONDS;
    }

    /**
     * The secret in groups of four, easier to type by hand.
     */
    public function grouped(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    private function encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private function decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(str_replace(' ', '', $secret))) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
