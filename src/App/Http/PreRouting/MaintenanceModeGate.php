<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use App\Services\MaintenanceMode;
use Framework\Core\Request;

/**
 * Class MaintenanceModeGate
 *
 * Purpose:
 * - Put the site into maintenance mode before routing, returning a 503 response.
 * - Avoids running the app stack during deployments, migrations, or incidents.
 *
 * Behavior:
 * - Maintenance is on while storage/maintenance.json exists (see MaintenanceMode).
 *   The admin Settings toggle writes that file; deploy scripts can touch/delete it
 *   directly, so the switch works even when the database is unreachable.
 * - Allowlisted IPs (stored in the flag payload, falling back to MAINTENANCE_ALLOW)
 *   bypass the gate. Supports single IPv4/IPv6 addresses and IPv4 CIDR blocks.
 * - The control panel and auth paths stay reachable for everyone so an admin can
 *   always log in and turn maintenance off; those routes enforce their own auth.
 * - Serves a static HTML page (public/maintenance.html) if present; otherwise a
 *   minimal styled page with the site name from the flag payload.
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
    // Paths that must stay reachable during maintenance (checked after locale prefix is stripped)
    private const ALLOWED_PREFIXES = ['/admin', '/login', '/logout', '/password'];

    public static function handle(Request $request): void
    {
        // Never interfere with CLI commands (migrations, queues, etc.).
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (!MaintenanceMode::active()) {
            return;
        }

        $payload = MaintenanceMode::payload();

        // Keep the control panel and auth flows reachable so admins can log in
        // and switch maintenance off. These routes enforce their own auth.
        if (self::isAllowedPath($_SERVER['REQUEST_URI'] ?? '/')) {
            return;
        }

        // Allowlist from the flag payload; falls back to MAINTENANCE_ALLOW for
        // files created by hand (touch storage/maintenance.json).
        $allowed = $payload['allow'] ?? MaintenanceMode::configuredAllowlist();

        // Client IP as seen by PHP.
        // could later extend this to use X-Forwarded-For behind a trusted proxy if needed.
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

        // If client IP matches an allowlisted entry or CIDR, bypass maintenance.
        if ($clientIp !== '' && self::isIpAllowed($clientIp, (array) $allowed)) {
            return;
        }

        // Maintenance applies: send 503 Service Unavailable.
        // Retry-After tells crawlers and well-behaved clients when to try again.
        $retryAfter = (int) ($payload['retry_after'] ?? 600);
        http_response_code(503);
        header('Retry-After: '.max(0, $retryAfter));
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        // Serve static maintenance page if available, to keep content out of PHP templates.
        $static = ROOT_PATH.'/public/maintenance.html';
        if (is_readable($static)) {
            readfile($static);
        } else {
            echo self::maintenancePage((string) ($payload['site_name'] ?? 'Lexicon'));
        }

        // Stop further processing; nothing beyond this point should run during maintenance.
        exit;
    }

    /**
     * Check whether the request path may pass during maintenance.
     *
     * The URI still carries the locale prefix at pre-routing time
     * (/en/admin/...), so strip one two-letter segment before matching.
     */
    private static function isAllowedPath(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = preg_replace('#^/[a-z]{2}(?=/|$)#i', '', $path) ?: '/';

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $allowed  Allowlist entries: exact IPs or CIDR ranges
     */
    private static function isIpAllowed(string $ip, array $allowed): bool
    {
        foreach ($allowed as $entry) {
            if (!is_string($entry) || $entry === '') {
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

    /**
     * Minimal inline 503 page used when public/maintenance.html is absent.
     *
     * Site name comes from the flag payload (snapshotted at enable time),
     * never from the database, which may be down.
     */
    private static function maintenancePage(string $siteName): string
    {
        $siteName = e($siteName);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{$siteName} — Down for maintenance</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #0f172a; color: #e2e8f0; }
        .box { text-align: center; padding: 2rem; max-width: 28rem; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; color: #f8fafc; }
        p { color: #94a3b8; line-height: 1.6; margin: 0; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon">&#128295;</div>
        <h1>We&rsquo;ll be right back</h1>
        <p>{$siteName} is down for scheduled maintenance. We should be back online shortly &mdash; thanks for your patience.</p>
    </div>
</body>
</html>
HTML;
    }
}
