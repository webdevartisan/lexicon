<?php

declare(strict_types=1);

namespace Framework\Security;

/**
 * Resolves the real client address when the app sits behind reverse proxies.
 *
 * X-Forwarded-For is only believed when the connecting peer is a configured
 * trusted proxy, and the chain is read from the right so entries a client
 * injected on the left are never used.
 */
final class ClientIp
{
    // Bounds the work a hostile header can cause
    private const MAX_HOPS = 20;

    /**
     * Resolve the client IP for a request.
     *
     * @param  array<string,mixed>  $server  $_SERVER or an equivalent
     * @param  string[]  $trusted  Trusted proxy addresses and CIDR ranges
     * @return string|null The client IP, or null when no peer address is known
     */
    public static function resolve(array $server, array $trusted): ?string
    {
        $peer = (string) ($server['REMOTE_ADDR'] ?? '');
        if ($peer === '') {
            return null;
        }

        if (!self::isTrusted($peer, $trusted)) {
            return $peer;
        }

        $header = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($header === '') {
            return $peer;
        }

        $hops = array_slice(array_reverse(explode(',', $header)), 0, self::MAX_HOPS);
        $client = $peer;

        foreach ($hops as $hop) {
            $hop = trim($hop);
            // A malformed entry means the header was tampered with; stop at the last hop we can vouch for
            if (!IpRange::isValidIp($hop)) {
                return $client;
            }

            $client = $hop;
            if (!self::isTrusted($hop, $trusted)) {
                return $hop;
            }
        }

        return $client;
    }

    /**
     * Resolve using the TRUSTED_PROXIES environment setting.
     *
     * @param  array<string,mixed>  $server
     */
    public static function fromEnvironment(array $server): ?string
    {
        return self::resolve($server, self::parseList((string) env('TRUSTED_PROXIES', '')));
    }

    /**
     * Parse a comma-separated list, dropping wildcards, /0 ranges and malformed entries.
     *
     * @return string[]
     */
    public static function parseList(string $raw): array
    {
        $entries = array_map(
            static fn (string $entry): string => trim($entry, " \t\n\r\0\x0B\"'"),
            explode(',', $raw)
        );

        return array_values(array_filter($entries, static fn (string $entry): bool => IpRange::isValidRange($entry)));
    }

    /**
     * @param  string[]  $trusted
     */
    private static function isTrusted(string $ip, array $trusted): bool
    {
        foreach ($trusted as $range) {
            if (IpRange::contains($ip, $range)) {
                return true;
            }
        }

        return false;
    }
}
