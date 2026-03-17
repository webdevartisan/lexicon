<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class MaintenanceModeGate
 *
 * Purpose:
 * - Quickly put the site into maintenance mode before routing, returning a 503 response or redirecting to a static page.
 * - Avoids running the app stack during deployments, migrations, or incidents.
 *
 * Behavior:
 * - If MAINTENANCE=1, all HTTP requests receive a 503 Service Unavailable with a Retry-After header.
 * - Allows IP whitelisting via MAINTENANCE_ALLOW (comma-separated list) so staff/admins can bypass.
 * - Optional: serves a static HTML page (public/maintenance.html) if present; otherwise prints a minimal message.
 *
 * Configuration:
 * - MAINTENANCE=0|1 in environment (.env).
 * - MAINTENANCE_ALLOW="127.0.0.1,10.0.0.0/8" supports single IPv4 addresses and simple CIDR blocks.
 * - MAINTENANCE_RETRY_AFTER seconds (e.g., 600) to set Retry-After header for crawlers and clients.
 *
 * Order in Kernel:
 * - Place after HttpsRedirector/SubdomainNormalizer so the maintenance page is served on canonical HTTPS/host.
 * - Place before all path/locale steps to short-circuit as early as possible.
 *
 * Usage:
 * - Call MaintenanceModeGate::handle($request) from the pre-routing PipelineRunner.
 */
final class MaintenanceModeGate
{
    public static function handle(Request $request): void
    {
        // Never interfere with CLI commands (migrations, queues, etc.).
        if (PHP_SAPI === 'cli') {
            return;
        }

        // Global maintenance flag from environment.
        $enabled = ($_ENV['MAINTENANCE'] ?? '0') === '1';
        if (!$enabled) {
            return;
        }

        // Build allowlist from MAINTENANCE_ALLOW, e.g. "127.0.0.1,10.0.0.0/8".
        $allowRaw = $_ENV['MAINTENANCE_ALLOW'] ?? '';
        $allowed = array_filter(
            array_map(
                static fn (string $entry): string => trim($entry, " \t\n\r\0\x0B\"'"),
                explode(',', $allowRaw)
            )
        );

        // Client IP as seen by PHP.
        // could later extend this to use X-Forwarded-For behind a trusted proxy if needed.
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

        // If client IP matches an allowlisted entry or CIDR, bypass maintenance.
        if ($clientIp !== '' && self::isIpAllowed($clientIp, $allowed)) {
            return;
        }

        // Maintenance applies: send 503 Service Unavailable.
        // Retry-After tells crawlers and well-behaved clients when to try again.
        $retryAfter = (int) ($_ENV['MAINTENANCE_RETRY_AFTER'] ?? 600);
        http_response_code(503);
        header('Retry-After: '.max(0, $retryAfter));
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        // Serve static maintenance page if available, to keep content out of PHP templates.
        $static = ROOT_PATH.'/public/maintenance.html';
        if (is_readable($static)) {
            readfile($static);
        } else {
            // Minimal, hard-coded fallback message (no user input; safe against XSS).
            echo '<!doctype html>'
               .'<html><head>'
               .'<meta charset="utf-8">'
               .'<title>Maintenance</title>'
               .'<meta name="robots" content="noindex,nofollow">'
               .'</head><body>'
               .'<h1>We&rsquo;ll be back soon.</h1>'
               .'<p>Our site is undergoing maintenance. Please try again later.</p>'
               .'</body></html>';
        }

        // Stop further processing; nothing beyond this point should run during maintenance.
        exit;
    }

    private static function isIpAllowed(string $ip, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if ($entry === '') {
                continue;
            }

            if ($entry === $ip) {
                return true;
            }

            if (str_contains($entry, '/')) {
                if (self::cidrMatch($ip, $entry)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);
        $subnetLong &= $maskLong;

        return ($ipLong & $maskLong) === $subnetLong;
    }
}
