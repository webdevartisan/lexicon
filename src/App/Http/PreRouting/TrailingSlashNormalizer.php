<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class TrailingSlashNormalizer
 *
 * Purpose:
 * - Enforce a single canonical path form by removing trailing slashes (except root) and collapsing duplicate slashes.
 * - Reduce duplicate content, improve analytics consistency, and stabilize route matching.
 *
 * Behavior:
 * - "/path/"      -> "/path" (redirect), but "/" remains "/".
 * - "/foo//bar"  -> "/foo/bar" (redirect).
 * - Preserves the query string.
 * - Uses 301 in production and 302 in non-production.
 *
 * Optional exceptions:
 * - If we want to allow bare locale segments with a trailing slash (e.g., "/gr/"),
 *   we can early-return for that pattern.
 *
 * Order in Kernel:
 * - Should run after HttpsRedirector and PathCanonicalization, and before LocalePrefixIntake,
 *   to minimize redirect hops and normalize paths prior to locale logic.
 *
 * Usage:
 * - Call TrailingSlashNormalizer::handle($request) from the pre-routing PipelineRunner
 *   (index.php invokes Kernel before dispatch).
 */
final class TrailingSlashNormalizer
{
    /**
     * Normalize duplicate and trailing slashes in the request path.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Only normalize for GET and HEAD to avoid redirecting form submissions or APIs.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        $fullUri = $request->uri ?? '/';
        $path = parse_url($fullUri, PHP_URL_PATH) ?: '/';
        $query = parse_url($fullUri, PHP_URL_QUERY) ?: null;

        // Optional: allow "/xx/" (bare locale with slash). Uncomment to exempt.
        // if (preg_match('#^/[a-z]{2}/$#i', $path)) {
        //     return;
        // }

        // Collapse duplicate slashes inside the path: "//foo///bar" => "/foo/bar".
        $normalized = preg_replace('#/{2,}#', '/', $path) ?? $path;

        // Strip trailing slash if not root.
        // This defines the canonical style as "no trailing slash" for non-root paths.
        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        // If normalization changed the path, redirect to the canonical form.
        if ($normalized !== $path) {
            $target = $normalized.($query ? ('?'.$query) : '');
            $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';

            header('Location: '.$target, true, $isProd ? 301 : 302);
            exit;
        }

        // Internal rewrite alternative (no redirect):
        // $request->uri = $normalized . ($query ? ('?' . $query) : '');
    }
}
