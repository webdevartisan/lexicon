<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class UserAgentBlocklist
 *
 * Purpose:
 * - Block obviously malicious or noisy clients early based on User-Agent substrings or exact matches.
 * - Reduce server load from scrapers/scanners without engaging the app stack.
 *
 * Behavior:
 * - Reads HTTP_USER_AGENT and compares against a case-insensitive denylist.
 * - If matched, returns 403 Forbidden with a plain-text message.
 *
 * Configuration:
 * - USER_AGENT_BLOCKLIST: comma-separated patterns (substrings), e.g.
 *   "curl,python-requests,libwww,sqlmap,nmap,downloaders".
 * - USER_AGENT_BLOCK_EXACT: optional comma-separated exact matches when strict equality is desired.
 *
 * Order in Kernel:
 * - After HTTPS/host normalization and maintenance/rate-limit checks; before legacy/path/locale steps.
 * - This ensures canonicalization policies do not waste cycles on blocked traffic.
 *
 * Usage:
 * - Call UserAgentBlocklist::handle($request) from the pre-routing PipelineRunner.
 */
final class UserAgentBlocklist
{
    /**
     * Inspect the User-Agent header and apply deny rules.
     *
     * @param  Request  $request  Incoming HTTP request (not directly used; present for interface symmetry).
     */
    public static function handle(Request $request): void
    {
        // If there is no User-Agent at all, this step does nothing.
        // This avoids blocking barebones clients purely for missing headers.
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($ua === '') {
            return;
        }

        // Parse comma-separated lists from environment and strip quotes/whitespace.
        $denySubs = self::parseList($_ENV['USER_AGENT_BLOCKLIST'] ?? '');
        $denyExact = self::parseList($_ENV['USER_AGENT_BLOCK_EXACT'] ?? '');

        // Exact matches (case-sensitive to avoid accidental over-matches).
        // Useful for known, stable bad actors where strict equality is safe.
        if (!empty($denyExact) && in_array($ua, $denyExact, true)) {
            self::forbid();
        }

        // Substring matches (case-insensitive).
        // Each entry is treated as "contains this token anywhere in the UA string".
        $uaLower = strtolower($ua);
        foreach ($denySubs as $pat) {
            if ($pat === '') {
                continue;
            }

            if (str_contains($uaLower, strtolower($pat))) {
                self::forbid();
            }
        }
    }

    /**
     * Normalize a comma-separated list from .env into a clean array of tokens.
     *
     * This:
     * - Splits on commas.
     * - Trims whitespace and optional quotes from each entry.
     * - Filters out empty tokens.
     *
     * @param  string  $raw  Raw environment value.
     * @return string[]
     */
    private static function parseList(string $raw): array
    {
        return array_filter(
            array_map(
                static fn (string $entry): string => trim($entry, " \t\n\r\0\x0B\"'"),
                explode(',', $raw)
            )
        );
    }

    /**
     * Return a simple 403 Forbidden response and terminate execution.
     *
     * Response:
     * - Status: 403 Forbidden
     * - Content-Type: text/plain (no HTML, no user input, safe against XSS)
     */
    private static function forbid(): void
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo 'Forbidden';
        exit;
    }
}
