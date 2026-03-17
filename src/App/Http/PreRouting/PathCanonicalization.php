<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class PathCanonicalization
 *
 * Purpose:
 * - Normalize unsafe or ambiguous path forms before routing, reducing 404s and duplicate variants.
 * - Collapse repeated slashes and remove dot segments ("/./" and "/a/../b").
 *
 * Behavior:
 * - For idempotent requests (GET/HEAD), operates only on the path component (not the scheme/host).
 * - Collapses multiple slashes: "//" → "/" in the path.
 * - Resolves dot segments using RFC 3986 rules:
 *   - "/a/b/./c"  → "/a/b/c"
 *   - "/a/b/../c" → "/a/c"
 * - Preserves the query string.
 * - Redirects to the normalized path (301 in prod, 302 otherwise).
 *
 * Notes:
 * - This step runs before TrailingSlashNormalizer so the final canonical path is stable in one hop.
 * - If silent internal rewriting is preferred, we can assign to $request->uri instead of redirecting.
 *
 * Order in Kernel:
 * - After SubdomainNormalizer and before TrailingSlashNormalizer and LocalePrefixIntake.
 *
 * Usage:
 * - Call PathCanonicalization::handle($request) from the pre-routing PipelineRunner.
 */
final class PathCanonicalization
{
    /**
     * Normalize the request path and redirect if the canonical form differs.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        // Only normalize paths for GET and HEAD to avoid redirecting form submissions or APIs.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return;
        }

        // Extract full URI and split into path + query.
        $fullUri = $request->uri ?? '/';
        $path = parse_url($fullUri, PHP_URL_PATH) ?: '/';
        $query = parse_url($fullUri, PHP_URL_QUERY) ?: null;

        // Step 1: collapse multiple slashes in the path.
        // Example: "//foo///bar" → "/foo/bar".
        $collapsed = preg_replace('#/{2,}#', '/', $path) ?? $path;

        // Step 2: resolve dot segments (RFC 3986 remove_dot_segments for path component).
        $normalized = self::removeDotSegments($collapsed);

        if ($normalized === '') {
            $normalized = '/';
        }

        // If the normalized path differs, build the canonical target and redirect.
        if ($normalized !== $path) {
            $target = $normalized.($query ? ('?'.$query) : '');
            $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';

            header('Location: '.$target, true, $isProd ? 301 : 302);
            exit;
        }

        // Alternative internal rewrite (no redirect):
        // $request->uri = $normalized . ($query ? ('?' . $query) : '');
    }

    /**
     * Implements RFC 3986 remove_dot_segments algorithm for the path component.
     *
     * This function:
     * - Removes "." and ".." path segments according to section 5.2.4 of RFC 3986.
     * - Ensures the resulting path does not contain any dot segments.
     *
     * @param  string  $path  Raw path string (no scheme/host/query).
     * @return string Normalized path without dot segments.
     */
    private static function removeDotSegments(string $path): string
    {
        $input = $path;
        $output = '';

        while ($input !== '') {
            // 1. Remove leading "../" or "./" segments.
            if (str_starts_with($input, '../')) {
                $input = substr($input, 3);
                continue;
            }

            if (str_starts_with($input, './')) {
                $input = substr($input, 2);
                continue;
            }

            // 2. Replace "/./" with "/" and "/." with "/".
            if (str_starts_with($input, '/./')) {
                // "/./" -> "/"
                $input = substr_replace($input, '/', 0, 3);
                continue;
            }

            if ($input === '/.') {
                $input = '/';
                continue;
            }

            // 3. Handle "/../" and "/.." by removing the last segment from output.
            if (str_starts_with($input, '/../')) {
                $input = substr($input, 3);

                // Remove last segment from output (up to previous slash).
                $lastSlash = strrpos($output, '/');
                $output = $lastSlash !== false ? substr($output, 0, $lastSlash) : '';
                continue;
            }

            if ($input === '/..') {
                $input = '/';

                $lastSlash = strrpos($output, '/');
                $output = $lastSlash !== false ? substr($output, 0, $lastSlash) : '';
                continue;
            }

            // 4. Move the next path segment from input to output.
            $pos = strpos($input, '/', 1);

            if ($pos === false) {
                // No more "/" separators; move the remaining input.
                $output .= $input;
                $input = '';
            } else {
                // Move up to (but not including) the next slash.
                $output .= substr($input, 0, $pos);
                $input = substr($input, $pos);
            }
        }

        // Ensure at least "/" is returned for an empty output.
        return $output === '' ? '/' : $output;
    }
}
