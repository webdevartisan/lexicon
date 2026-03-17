<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class LocalePrefixIntake
 *
 * Purpose:
 * - Enforce canonical locale-prefixed URLs (e.g., "/en/path"), while internally rewriting
 *   the Request path so routing stays language-agnostic ("/path").
 *
 * Behavior:
 * - If the first segment is a supported locale (case-insensitive), set session/cookie,
 *   strip the prefix for internal routing, and keep the visible URL unchanged.
 * - If no prefix, redirect once to "/{resolved-locale}{path}" (preserving query).
 * - If the first segment looks like a 2-letter code but is unsupported, redirect to the
 *   default-locale version to avoid junk prefixes being indexed.
 *
 * Order:
 * - Should run after scheme/host canonicalization and path normalization, and before routing.
 *
 * Usage:
 * - Call LocalePrefixIntake::handle($request) from the pre-routing PipelineRunner.
 */
final class LocalePrefixIntake
{
    /**
     * Detect locale prefix, adjust session/cookie, and normalize the internal request path.
     *
     * @param  Request  $request  Incoming HTTP request object.
     */
    public static function handle(Request $request): void
    {
        $cfg = require ROOT_PATH.'/config/localization.php';
        $supported = array_map('strtolower', $cfg['supported'] ?? ['en']);
        $default = strtolower($cfg['default'] ?? 'en');

        $fullUri = $request->uri ?? '/';
        $path = parse_url($fullUri, PHP_URL_PATH) ?: '/';
        $query = parse_url($fullUri, PHP_URL_QUERY) ?: null;

        $segments = array_values(array_filter(explode('/', $path)));
        $first = isset($segments[0]) ? strtolower($segments[0]) : null;

        // Normalize request method and classify unsafe methods.
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $isUnsafe = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        // 0) Unknown-looking locale (two letters) → redirect to default without the unknown segment.
        //    Example: "/xx/blogs" where "xx" is not in the supported list.
        if ($first && preg_match('#^[a-z]{2}$#i', $first) && !in_array($first, $supported, true)) {
            // Remove the first segment (the unknown locale).
            $remaining = '/'.implode('/', array_slice($segments, 1));
            $remaining = $remaining === '/' ? '' : $remaining;

            $target = '/'.$default.$remaining;
            if ($query) {
                $target .= '?'.$query;
            }

            // For unsafe methods, do not redirect or rewrite to avoid breaking form submissions/APIs.
            if ($isUnsafe) {
                return;
            }

            // Use 308 in production (permanent, method-preserving), 307 elsewhere.
            $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';
            header('Location: '.$target, true, $isProd ? 308 : 307);
            exit;
        }

        // 1) Prefixed with supported locale → set and internally rewrite path.
        //    Visible URL keeps the locale prefix; router sees a clean, unprefixed path.
        if ($first && in_array($first, $supported, true)) {
            // Persist locale choice in session (if active) and cookie.
            $_SESSION['locale'] = $first;
            /*setcookie('locale', $first, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'httponly' => false,
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'samesite' => 'Lax',
            ]);*/

            // Strip only the first segment for routing; keep visible URL unchanged.
            $stripped = '/'.implode('/', array_slice($segments, 1));
            $request->uri = $stripped === '/' || $stripped === '' ? '/' : $stripped;
            // If we ever want the router to see query too, we could include it here:
            // $request->uri = ($stripped === '' ? '/' : $stripped) . ($query ? ('?' . $query) : '');

            return;
        }

        // 2) No prefix → resolve target locale (session > cookie > Accept-Language > default).
        $resolved = $_SESSION['locale']
            ?? $_COOKIE['locale']
            ?? self::pickFromAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', $supported, $default)
            ?? $default;

        $resolved = strtolower($resolved);
        if (!in_array($resolved, $supported, true)) {
            $resolved = $default;
        }

        // Build the canonical, locale-prefixed target.
        $target = '/'.$resolved.($path === '/' ? '' : $path);
        if ($query) {
            $target .= '?'.$query;
        }

        // Refresh locale cookie to align with the resolved locale.
        /*setcookie('locale', $resolved, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'httponly' => false,
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);*/

        // For unsafe methods, avoid redirecting to protect non-idempotent requests.
        if ($isUnsafe) {
            return;
        }

        // Redirect once to the canonical locale-prefixed URL.
        // 308 in production (permanent, method-preserving), 307 elsewhere.
        $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';
        header('Location: '.$target, true, $isProd ? 308 : 307);
        exit;
    }

    /**
     * Pick best match from Accept-Language list; returns null if no match.
     *
     * Example header:
     *   "en-US,en;q=0.9,el;q=0.8" → primary tags "en", "el", etc.
     *
     * @param  string  $header  Raw Accept-Language header.
     * @param  array  $supported  Supported locale codes (lowercase).
     * @param  string  $default  Default locale (lowercase).
     * @return string|null Best-matching supported locale or null if none.
     */
    private static function pickFromAcceptLanguage(string $header, array $supported, string $default): ?string
    {
        if ($header === '') {
            return null;
        }

        // Parse simple language tags like "en-US,en;q=0.9,el;q=0.8".
        $langs = array_map('trim', explode(',', $header));
        foreach ($langs as $tag) {
            // Extract primary subtag (e.g., "en" from "en-US").
            $primary = strtolower(explode('-', explode(';', $tag)[0])[0]);
            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return null;
    }
}
