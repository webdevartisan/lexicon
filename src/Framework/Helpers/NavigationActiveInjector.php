<?php

declare(strict_types=1);

namespace Framework\Helpers;

/**
 * Inject active state into navigation HTML using regex.
 *
 * We parse navigation links and add active classes
 * without touching the DOM structure.
 */
class NavigationActiveInjector
{
    /**
     * Inject active state into navigation HTML.
     *
     * We find navigation links matching the current path
     * and add active CSS classes to them using regex.
     *
     * @param  string  $html  Complete HTML document
     * @param  string  $currentPath  Current request path
     * @return string HTML with active state injected
     */
    public static function inject(string $html, string $currentPath): string
    {
        // normalize the current path
        $currentPath = rtrim($currentPath, '/');
        if (empty($currentPath)) {
            $currentPath = '/';
        }

        // find all <a> tags with data-nav-path attribute
        // Pattern matches: <a ...data-nav-path="..."...>
        $pattern = '/<a\s+([^>]*?)data-nav-path="([^"]*)"([^>]*)>/i';

        $result = preg_replace_callback($pattern, function ($matches) use ($currentPath) {
            $beforePath = $matches[1];  // Attributes before data-nav-path
            $navPath = $matches[2];     // The path value
            $afterPath = $matches[3];   // Attributes after data-nav-path

            // normalize the nav path
            $navPath = rtrim($navPath, '/');
            if (empty($navPath)) {
                $navPath = '/';
            }

            // check if this link is active using your preferred logic
            $isActive = $currentPath === $navPath ||
                       ($navPath !== '/' && str_ends_with($currentPath, $navPath));

            if ($isActive) {
                // rebuild the full attributes string
                $allAttrs = $beforePath.'data-nav-path="'.$navPath.'"'.$afterPath;

                // extract existing class attribute
                $classPattern = '/class="([^"]*)"/i';

                if (preg_match($classPattern, $allAttrs, $classMatch)) {
                    $existingClasses = $classMatch[1];

                    // add active class if not already present
                    if (!str_contains($existingClasses, ' active ')) {
                        $newClasses = trim($existingClasses.' active');

                        $allAttrs = preg_replace(
                            $classPattern,
                            'class="'.$newClasses.'"',
                            $allAttrs,
                            1
                        );
                    }
                } else {
                    // add class attribute if it doesn't exist
                    $allAttrs .= ' class="active"';
                }

                // add aria-current attribute if not present
                if (!str_contains($allAttrs, 'aria-current')) {
                    $allAttrs .= ' aria-current="page"';
                }

                return '<a '.trim($allAttrs).'>';
            }

            // return unchanged if not active
            return $matches[0];

        }, $html);

        return $result ?: $html;
    }
}
