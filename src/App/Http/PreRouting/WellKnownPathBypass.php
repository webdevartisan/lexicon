<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

/**
 * Class WellKnownPathBypass
 *
 * Purpose:
 * - Identify protocol endpoints that belong at the origin root rather than
 *   inside the locale-prefixed URL space.
 *
 * Behavior:
 * - Pure predicate, no state. LocalePrefixIntake calls it and returns early,
 *   which is why this is not a pipeline step of its own.
 *
 * Notes:
 * - Apache serves any file that exists under public/ before the request
 *   reaches the front controller, so robots.txt and favicon.ico only arrive
 *   here when the file is absent. /sitemap.xml is a route rather than a file,
 *   so it always arrives here and this guard is what keeps it unprefixed.
 */
final class WellKnownPathBypass
{
    /**
     * Exact root paths that user agents and crawlers fetch by specification.
     */
    private const EXACT = ['/robots.txt', '/sitemap.xml', '/favicon.ico'];

    /**
     * Path prefixes reserved by RFC 8615.
     */
    private const PREFIXES = ['/.well-known/'];

    /**
     * Whether a path is a protocol endpoint rather than a page.
     */
    public static function isWellKnown(string $path): bool
    {
        $path = strtolower($path);

        if (in_array($path, self::EXACT, true)) {
            return true;
        }

        foreach (self::PREFIXES as $prefix) {
            // Anchored, so an ordinary page like /blog/.well-known/x stays in
            // the locale space.
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
