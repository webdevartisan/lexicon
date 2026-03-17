<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class HealthCheckBypass
 *
 * Purpose:
 * - Short-circuit synthetic health checks (e.g., /healthz, /ping) before routing.
 * - Avoids touching the router, controllers, database or templates; keeps probes fast and predictable.
 *
 * Behavior:
 * - For matching paths, sends a lightweight 200 OK text response ("ok <version>") and exits.
 * - Only responds to GET and HEAD by default to avoid surprises for other HTTP verbs.
 *
 * Configuration:
 * - APP_VERSION (optional) is used to include a version string; if missing, defaults to "v0".
 * - Paths are currently hard-coded: "/healthz" and "/ping".
 *
 * Order in Kernel:
 * - Very early, after HTTPS/host normalization and before any heavy steps (maintenance, locale, etc.).
 * - This ensures monitoring systems get a fast answer even under load.
 *
 * Usage:
 * - Call HealthCheckBypass::handle($request) from the pre-routing PipelineRunner.
 */
final class HealthCheckBypass
{
    /**
     * Handle simple health endpoints and short-circuit the request pipeline.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Normalize path from the request URI.
        $uri = $request->uri ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Restrict to simple, known-safe methods.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }

        // Match dedicated health check endpoints and respond immediately.
        if ($path === '/healthz' || $path === '/ping') {
            // HTTP 200 OK with minimal plain-text payload.
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=5');

            // Including a version string helps distinguish deployments.
            // could later make this more opaque (e.g., a build hash) if version exposure is a concern.
            $version = $_ENV['APP_VERSION'] ?? 'v0';

            // HEAD responses should not include a body.
            if ($method === 'HEAD') {
                exit;
            }

            echo 'ok '.$version;
            exit;
        }
    }
}
