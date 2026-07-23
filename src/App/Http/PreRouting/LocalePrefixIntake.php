<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use App\Services\LocaleRegistry;
use App\Services\LocaleState;
use App\Services\UserLocalePreference;
use App\ValueObjects\LocaleContext;
use Framework\Core\Request;

/**
 * Class LocalePrefixIntake
 *
 * Purpose:
 * - Enforce canonical locale-prefixed URLs (e.g., "/en/path"), while internally rewriting
 *   the Request path so routing stays language-agnostic ("/path").
 *
 * Behavior:
 * - If the first segment is a supported locale (case-insensitive), set the session,
 *   strip the prefix for internal routing, and keep the visible URL unchanged.
 *   The locale cookie is written by LocaleMiddleware, never here.
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
        $registry = LocaleRegistry::instance();
        $supported = $registry->supported();
        $default = $registry->default();

        $fullUri = $request->uri ?? '/';
        $path = parse_url($fullUri, PHP_URL_PATH) ?: '/';
        $query = parse_url($fullUri, PHP_URL_QUERY) ?: null;

        // Crawlers fetch these at the origin root by specification, so they never
        // take a locale prefix. /sitemap.xml is the one that matters: it is a
        // route rather than a file, so Apache cannot serve it before we get here.
        if (WellKnownPathBypass::isWellKnown($path)) {
            return;
        }

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
            $isProd = (env('APP_ENV', $_SERVER['APP_ENV'] ?? 'production')) === 'production';
            header('Location: '.$target, true, $isProd ? 308 : 307);
            exit;
        }

        // 1) Prefixed with supported locale → set and internally rewrite path.
        //    Visible URL keeps the locale prefix; router sees a clean, unprefixed path.
        if ($first && in_array($first, $supported, true)) {
            // Persist locale choice in session (if active).
            $_SESSION['locale'] = $first;

            // Strip only the first segment for routing; keep visible URL unchanged.
            // The leading slash guarantees a non-empty path, '/' when nothing remains.
            $request->uri = '/'.implode('/', array_slice($segments, 1));

            // A prefixed protocol endpoint such as /en/robots.txt would otherwise
            // serve the same body as the canonical /robots.txt. Send it to the one
            // real URL. Permanent, because these never belong under a locale.
            if (WellKnownPathBypass::isWellKnown($request->uri)) {
                if ($isUnsafe) {
                    return;
                }

                header('Location: '.$request->uri, true, 301);
                exit;
            }
            // The prefix is the content locale, always. Chrome follows it for
            // guests; a signed-in reader's stored preference overrides only the
            // chrome half, so /el/ still serves Greek content to someone whose
            // interface is English.
            $preference = self::storedPreference();
            LocaleState::set(new LocaleContext($first, $preference ?? $first));

            return;
        }

        // 2) No prefix → resolve target locale
        //    (session > account preference > cookie > Accept-Language > default).
        //    Read once and reused for the chrome half below, so a request costs
        //    at most one preference lookup.
        $preference = self::storedPreference();

        $resolved = $_SESSION['locale']
            ?? $preference
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

        // An unsafe method is served here rather than redirected, so it still
        // needs a context for anything downstream that reads the locale.
        LocaleState::set(new LocaleContext($resolved, $preference ?? $resolved));

        // For unsafe methods, avoid redirecting to protect non-idempotent requests.
        if ($isUnsafe) {
            return;
        }

        // Redirect once to the canonical locale-prefixed URL.
        // 308 in production (permanent, method-preserving), 307 elsewhere.
        $isProd = (env('APP_ENV', $_SERVER['APP_ENV'] ?? 'production')) === 'production';
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
     * @param  string[]  $supported  Supported locale codes (lowercase).
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

    /**
     * The signed-in reader's stored interface language, or null.
     *
     * Reachable from pre-routing because index.php builds the container and
     * touches Auth before it runs the pre-routing pipeline. A guest costs one
     * session array read and no query.
     *
     * @return string|null Supported locale code, or null for a guest or no preference
     */
    private static function storedPreference(): ?string
    {
        $userId = auth()->user()['id'] ?? null;

        return app(UserLocalePreference::class)->resolve(
            $userId === null ? null : (int) $userId
        );
    }
}
