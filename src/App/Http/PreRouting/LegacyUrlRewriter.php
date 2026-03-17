<?php

declare(strict_types=1);

namespace App\Http\PreRouting;

use Framework\Core\Request;

/**
 * Class LegacyUrlRewriter
 *
 * Purpose:
 * - Seamlessly support old URL patterns after a site restructure or migration.
 * - Avoid 404s for backlinks and user bookmarks; preserve SEO equity.
 *
 * Behavior:
 * - Checks the current path against a configurable rewrite map.
 * - For "moved permanently" paths, issues a 301 redirect to the new location (preserve query).
 * - For "internal rewrite" cases, mutates $request->uri to the new path without redirect.
 *
 * Configuration:
 * - Define a map (static array or external file) from legacy patterns to targets.
 *   Supports:
 *   - Exact match: "/old-blog/post-123" => "/blog/post-123"
 *   - Prefix match: [ "prefix" => "/old-blog/", "replace" => "/blog/" ]
 *   - Regex match:  [ "regex" => "#^/old/([0-9]+)/?$#", "target" => "/new/$1", "redirect" => true ]
 *
 * Order in Kernel:
 * - After host/HTTPS normalization, before path/trailing/locale steps, so final canonical is redirected at most once.
 *
 * Usage:
 * - Call LegacyUrlRewriter::handle($request) from the pre-routing PipelineRunner.
 */
final class LegacyUrlRewriter
{
    public static function handle(Request $request): void
    {
        $fullUri = $request->uri ?? '/';
        $path = parse_url($fullUri, PHP_URL_PATH) ?: '/';
        $query = parse_url($fullUri, PHP_URL_QUERY) ?: null;

        // Example rewrite rules (move to config/legacy_urls.php if preferred)
        $rules = [
            // Exact path redirect (301)
            ['exact' => '/old-blog', 'target' => '/blog', 'redirect' => true],

            // Prefix replacement (301)
            ['prefix' => '/old-blog/', 'replace' => '/blog/', 'redirect' => true],

            // Regex redirect (301)
            ['regex' => '#^/old-article/(\d+)-(.+)$#', 'target' => '/articles/$1/$2', 'redirect' => true],

            // Internal rewrite (no redirect)
            // ['exact' => '/legacy-dashboard', 'target' => '/dashboard', 'redirect' => false],
        ];

        foreach ($rules as $rule) {
            $newPath = null;

            if (isset($rule['exact']) && $path === $rule['exact']) {
                $newPath = $rule['target'];
            } elseif (isset($rule['prefix']) && str_starts_with($path, $rule['prefix'])) {
                $newPath = $rule['replace'].substr($path, strlen($rule['prefix']));
            } elseif (isset($rule['regex'])) {
                $newPath = preg_replace($rule['regex'], $rule['target'], $path);
                if ($newPath === null || $newPath === $path) {
                    $newPath = null;
                }
            }

            if ($newPath === null) {
                continue;
            }

            $redirect = ($rule['redirect'] ?? true) === true;
            $target = $newPath.($query ? ('?'.$query) : '');

            if ($redirect) {
                $isProd = ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production') === 'production';
                header('Location: '.$target, true, $isProd ? 301 : 302);
                exit;
            } else {
                // Internal rewrite; keep browser URL the same
                $request->uri = $target;

                return;
            }
        }
    }
}
