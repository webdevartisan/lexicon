<?php

declare(strict_types=1);

namespace Framework\Security;

/**
 * Matches IPv4 and IPv6 addresses against single addresses and CIDR ranges.
 */
final class IpRange
{
    /**
     * Check whether an address falls inside a single address or CIDR range.
     *
     * @param  string  $ip  Address to test
     * @param  string  $range  Plain address ("10.0.0.1") or CIDR ("10.0.0.0/8", "fd00::/8")
     */
    public static function contains(string $ip, string $range): bool
    {
        $parsed = self::parse($range);
        $address = self::toBytes($ip);
        if ($parsed === null || $address === null) {
            return false;
        }

        [$network, $prefix] = $parsed;
        if (strlen($network) !== strlen($address)) {
            return false;
        }

        return self::sharesPrefix($address, $network, $prefix);
    }

    /**
     * Check whether a range string is well formed and not the match-everything /0.
     *
     * Rejecting /0 keeps a typo from trusting the whole internet.
     */
    public static function isValidRange(string $range): bool
    {
        $parsed = self::parse($range);

        return $parsed !== null && $parsed[1] > 0;
    }

    /**
     * Check whether a string is a valid IPv4 or IPv6 address.
     */
    public static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * @return array{0: string, 1: int}|null Network bytes and prefix length
     */
    private static function parse(string $range): ?array
    {
        $parts = explode('/', $range, 2);
        $network = self::toBytes($parts[0]);
        if ($network === null) {
            return null;
        }

        $maxPrefix = strlen($network) * 8;
        if (!isset($parts[1])) {
            return [$network, $maxPrefix];
        }

        if (!ctype_digit($parts[1]) || (int) $parts[1] > $maxPrefix) {
            return null;
        }

        return [$network, (int) $parts[1]];
    }

    /**
     * Packed address bytes; IPv4-mapped IPv6 (::ffff:a.b.c.d) is folded to IPv4
     * so a dual-stack socket does not defeat an IPv4 range.
     */
    private static function toBytes(string $ip): ?string
    {
        if (!self::isValidIp($ip)) {
            return null;
        }

        $bytes = inet_pton($ip);
        if ($bytes === false) {
            return null;
        }

        if (strlen($bytes) === 16 && str_starts_with($bytes, str_repeat("\0", 10)."\xff\xff")) {
            return substr($bytes, 12);
        }

        return $bytes;
    }

    private static function sharesPrefix(string $a, string $b, int $prefix): bool
    {
        $wholeBytes = intdiv($prefix, 8);
        if (substr($a, 0, $wholeBytes) !== substr($b, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $prefix % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($a[$wholeBytes]) & $mask) === (ord($b[$wholeBytes]) & $mask);
    }
}
