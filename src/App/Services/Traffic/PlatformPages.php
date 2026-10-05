<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\PageModel;

/**
 * The platform's own pages that count as views, worked out from the path alone.
 * A fixed list rather than a pattern, so account pages and links carrying a token stay out.
 */
final class PlatformPages
{
    /** Every page type a platform page can have. Blog pages use the others. */
    public const PAGE_TYPES = ['home', 'discover', 'static_page', 'guide', 'profile', 'auth'];

    private const STATIC_PAGES = ['about', 'privacy', 'terms', 'cookies', 'contact'];

    private const AUTH_PAGES = ['login', 'register'];

    /**
     * @param  list<string>  $segments  Path segments after the locale
     * @return array{0: string, 1: string}|null Page type and the path to store
     */
    public static function match(array $segments): ?array
    {
        $first = $segments[0] ?? '';
        $count = count($segments);
        $isGuide = $count === 2 && $first === 'getting-started' && in_array($segments[1], PageModel::GUIDE_SLUGS, true);

        return match (true) {
            $count === 0, $segments === ['home'] => ['home', '/'],
            $segments === ['discover'] => ['discover', '/discover'],
            $segments === ['getting-started'] => ['guide', '/getting-started'],
            $count === 1 && in_array($first, self::STATIC_PAGES, true) => ['static_page', '/'.$first],
            $count === 1 && in_array($first, self::AUTH_PAGES, true) => ['auth', '/'.$first],
            $isGuide => ['guide', '/getting-started/'.$segments[1]],
            // One row for every profile, so no handle outlives its account in the totals.
            $count === 2 && $first === 'profile' => ['profile', '/profile'],
            default => null,
        };
    }

    /**
     * @param  string  $path  A path without its locale prefix, e.g. /about
     */
    public static function counts(string $path): bool
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));

        return self::match($segments) !== null;
    }
}
