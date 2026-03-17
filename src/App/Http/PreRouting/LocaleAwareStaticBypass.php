<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use App\Services\AssetPathMapper;
use Framework\Core\Request;

/**
 * Class LocaleAwareStaticBypass
 *
 * Purpose:
 * - Serve static files from /public for URLs like "/images/*", "/assets/*",
 *   and their locale-prefixed variants "/{locale}/images/*" without touching routing.
 *
 * Notes:
 * - Keeps locale handling out of asset URLs; avoids 500s from unmatched routes.
 * - Intended primarily for development or simple deployments; in production,
 *   a dedicated web server / CDN is preferred for serving static files.
 *
 * Responsibilities:
 * - Decide whether the path points to a static asset under known public roots.
 * - Resolve the URL path to an on-disk file via AssetPathMapper.
 * - Set Content-Type based on file extension and stream the file.
 * - Does NOT set Cache-Control; CacheControlHint is responsible for cache policy.
 *
 * Order:
 * - Place before routing; after HTTPS/host steps and CacheControlHint.
 * - Can run before or after LocalePrefixIntake, since it understands both
 *   prefixed and unprefixed paths.
 *
 * Usage:
 * - Call LocaleAwareStaticBypass::handle($request) from the pre-routing PipelineRunner.
 */
final class LocaleAwareStaticBypass
{
    /**
     * Short-circuit static asset requests and serve files directly from /public.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Only serve static assets on GET/HEAD; other methods should go through the app.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        // Read supported locales to detect optional "{locale}/" prefixes.
        $cfg = require ROOT_PATH.'/config/localization.php';
        $supported = array_map('strtolower', $cfg['supported'] ?? ['en']);

        $uri = $request->uri ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Break the path into segments: "/en/assets/app.css" → ["en","assets","app.css"].
        $segments = array_values(array_filter(explode('/', $path)));
        if (empty($segments)) {
            return;
        }

        // Detect optional locale prefix in the first segment.
        $i = 0;
        $first = strtolower($segments[0]);
        if (in_array($first, $supported, true)) {
            $i = 1;
        }

        // Allowed public roots under /public.
        // These represent directories where assets are stored.
        $roots = ['assets', 'cp-assets', 'images', 'uploads', 'themes'];
        if (!isset($segments[$i]) || !in_array(strtolower($segments[$i]), $roots, true)) {
            // Not a known static root; let the normal router handle it.
            return;
        }

        // Build filesystem-relative path (e.g. "/assets/app.1234abcd.css").
        $relativeParts = array_slice($segments, $i);
        $assetPath = '/'.implode('/', $relativeParts);

        // Use AssetPathMapper as the single source of truth for URL -> file mapping.
        $mapper = new AssetPathMapper(ROOT_PATH);
        $file = $mapper->fileFromUrlPath($assetPath);

        // If mapper returns null, this is not a known asset path; let the app handle it.
        if ($file === null) {
            return;
        }

        // File must exist and be readable; otherwise return a minimal 404.
        if (!is_file($file) || !is_readable($file)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not Found';
            exit;
        }

        // Minimal content-type map for common asset extensions.
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $types = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];

        if (isset($types[$ext])) {
            header('Content-Type: '.$types[$ext]);
        } else {
            // Fallback for unknown types; browsers will handle generic binary.
            header('Content-Type: application/octet-stream');
        }

        // Cache-Control is not set here; CacheControlHint should have already
        // applied the desired policy for /assets/... and other static paths.

        // For HEAD requests, do not send a body.
        if ($method === 'HEAD') {
            exit;
        }

        readfile($file);
        exit;
    }
}
