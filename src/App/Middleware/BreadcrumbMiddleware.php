<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\BreadcrumbService;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * BreadcrumbMiddleware
 *
 * automatically generate breadcrumb trails based on the current URL.
 * Controllers can override auto-generated breadcrumbs by calling
 * $this->breadcrumbs->set() manually.
 */
class BreadcrumbMiddleware implements MiddlewareInterface
{
    /**
     * @var BreadcrumbService Breadcrumb service instance
     */
    private BreadcrumbService $breadcrumbs;

    /**
     * @var array Navigation configuration
     */
    private array $navConfig;

    /**
     * @var array Breadcrumb configuration
     */
    private array $config;

    /**
     * Constructor
     *
     * inject the breadcrumb service and load configuration.
     *
     * @param  BreadcrumbService  $breadcrumbs  Breadcrumb service
     */
    public function __construct(BreadcrumbService $breadcrumbs)
    {
        $this->breadcrumbs = $breadcrumbs;
        $this->navConfig = require ROOT_PATH.'/config/navigation.php';
        $this->config = require ROOT_PATH.'/config/breadcrumbs.php';
    }

    /**
     * Process middleware
     *
     * build breadcrumbs before the controller executes.
     * If the controller manually sets breadcrumbs, respect that.
     *
     * @param  Request  $request  HTTP request
     * @param  RequestHandlerInterface  $handler  Next handler
     * @return Response HTTP response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // build breadcrumbs before controller execution
        $this->buildBreadcrumbs($request);

        // let the request proceed to the controller
        $response = $handler->handle($request);

        // don't override if controller manually set breadcrumbs
        // (Controllers set manual flag when calling breadcrumbs->set())

        return $response;
    }

    /**
     * Build breadcrumb trail from URL path
     *
     * analyze the URL and generate appropriate breadcrumbs.
     * This is only called if breadcrumbs weren't manually set.
     *
     * @param  Request  $request  HTTP request
     */
    private function buildBreadcrumbs(Request $request): void
    {
        // skip if breadcrumbs were already manually set by a controller
        if ($this->breadcrumbs->wasManuallySet()) {
            return;
        }

        $path = $this->normalizePath($request->uri);

        // clear any existing breadcrumbs
        $this->breadcrumbs->clear();

        // check if there's a predefined pattern for this path
        $pattern = $this->matchPattern($path);
        if ($pattern !== null) {
            $this->buildFromPattern($pattern, $path);

            return;
        }

        // detect area and build trail from navigation
        $area = $this->detectArea($path);
        $trail = $this->buildTrailFromNav($path, $area);

        // set the complete trail
        $this->breadcrumbs->set($trail);
    }

    /**
     * Normalize URL path
     *
     * clean up the path for consistent processing.
     *
     * @param  string  $uri  Full URI
     * @return string Normalized path
     */
    private function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * Detect navigation area from path
     *
     * determine if this is admin, dashboard, or front-end.
     *
     * @param  string  $path  URL path
     * @return string Area identifier (admin, back, front)
     */
    private function detectArea(string $path): string
    {
        if (str_starts_with($path, '/admin')) {
            return 'admin';
        }
        if (str_starts_with($path, '/dashboard')) {
            return 'back';
        }

        return 'front';
    }

    /**
     * Match path against configured patterns
     *
     * check if the current path matches any predefined pattern.
     *
     * @param  string  $path  URL path
     * @return string|null Matching pattern or null
     */
    private function matchPattern(string $path): ?string
    {
        $patterns = $this->config['patterns'] ?? [];

        foreach ($patterns as $pattern => $labels) {
            // convert pattern placeholders to regex
            $regex = $this->patternToRegex($pattern);
            if (preg_match($regex, $path)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Convert breadcrumb pattern to regex
     *
     * transform patterns like "/blog/{id}/edit" into regex.
     *
     * @param  string  $pattern  Pattern with placeholders
     * @return string Regular expression
     */
    private function patternToRegex(string $pattern): string
    {
        // escape special regex characters
        $regex = preg_quote($pattern, '/');

        // replace {id} placeholders with regex for numbers
        $regex = str_replace('\{id\}', '\d+', $regex);

        // replace {slug} placeholders with regex for slugs
        $regex = str_replace('\{slug\}', '[a-zA-Z0-9_-]+', $regex);

        return '/^'.$regex.'$/';
    }

    /**
     * Build breadcrumbs from pattern configuration
     *
     * use predefined labels, URLs, and translation keys from config.
     * Config provides complete breadcrumb items with i18n support.
     *
     * @param  string  $pattern  Matched pattern
     * @param  string  $path  Actual URL path
     */
    private function buildFromPattern(string $pattern, string $path): void
    {
        $configuredItems = $this->config['patterns'][$pattern] ?? [];

        // start with an empty trail
        $trail = [];

        // add "Home" only if configured to show it
        if ($this->config['show_home'] ?? true) {
            $trail[] = [
                'label' => $this->config['home_label'] ?? 'Home',
                'url' => $this->config['home_url'] ?? '/',
                'key' => $this->config['home_translation_key'] ?? null,
            ];
        }

        // add all configured breadcrumb items with URLs and translation keys
        foreach ($configuredItems as $item) {
            $trail[] = [
                'label' => $item['label'] ?? 'Unknown',
                'url' => $item['url'] ?? null,
                'key' => $item['key'] ?? null,
            ];
        }

        $this->breadcrumbs->set($trail);
    }

    /**
     * Build breadcrumb trail from navigation config
     *
     * analyze the path and navigation items to build breadcrumbs.
     *
     * @param  string  $currentPath  Current request path
     * @param  string  $area  Navigation area (admin, back, front)
     * @return array<int, array{label: string, url: string|null, key: string|null}> Breadcrumb trail
     */
    private function buildTrailFromNav(string $currentPath, string $area): array
    {
        $navItems = $this->navConfig[$area] ?? [];

        // start with an empty trail
        $trail = [];

        // check if should show Home from config
        $showHomeFromConfig = $this->config['show_home'] ?? true;

        // add "Home" only if configured to show it
        if ($showHomeFromConfig) {
            $trail[] = [
                'label' => $this->config['home_label'] ?? 'Home',
                'url' => $this->config['home_url'] ?? '/',
                'key' => $this->config['home_translation_key'] ?? null,
            ];
        }

        // find matching parent navigation items
        $matchedItems = $this->findParentNavItems($currentPath, $navItems);

        // add matched navigation items to trail
        foreach ($matchedItems as $item) {
            // skip section headers
            if (($item['type'] ?? 'link') === 'section_header') {
                continue;
            }

            // skip contextual items with placeholders
            if (!empty($item['replace_blog_id'])) {
                continue;
            }

            $itemPath = rtrim($item['href'], '/');
            $isCurrentPage = ($itemPath === $currentPath);

            // skip navigation "Home" items that point to "/" when config Home is already added
            if ($showHomeFromConfig && $item['label'] === 'Home' && ($itemPath === '/' || $itemPath === '')) {
                continue;
            }

            // handle area-specific home items (like /admin or /dashboard root)
            if ($item['label'] === 'Home' && $itemPath !== '/' && $itemPath !== '') {
                $areaConfig = $this->config['area_names'][$area] ?? ['label' => ucfirst($area), 'key' => null];
                $areaLabel = is_array($areaConfig) ? $areaConfig['label'] : $areaConfig;
                $areaKey = is_array($areaConfig) ? ($areaConfig['key'] ?? null) : null;

                $trail[] = [
                    'label' => $areaLabel,
                    'url' => $isCurrentPage ? null : $item['href'],
                    'key' => $areaKey,
                ];
            } else {
                $trail[] = [
                    'label' => $item['label'],
                    'url' => $isCurrentPage ? null : $item['href'],
                    'key' => $item['key'] ?? null, // Support translation keys in nav config
                ];
            }
        }

        // add remaining path segments not covered by navigation
        $trail = $this->addRemainingSegments($trail, $currentPath, $matchedItems);

        return $trail;
    }

    /**
     * Find parent navigation items that match the path
     *
     * identify all navigation items that are ancestors of the current path.
     *
     * @param  string  $currentPath  Current request path
     * @param  array  $navItems  Navigation items from config
     * @return array Matched parent items in hierarchical order
     */
    private function findParentNavItems(string $currentPath, array $navItems): array
    {
        $matches = [];
        $showHomeFromConfig = $this->config['show_home'] ?? true;

        // check each navigation item
        foreach ($navItems as $item) {
            $itemPath = rtrim($item['href'] ?? '', '/');

            // skip empty paths and contextual items with placeholders
            if ($itemPath === '' || str_contains($itemPath, '{')) {
                continue;
            }

            // skip root "/" Home items when config Home is enabled (avoid duplicates)
            if ($showHomeFromConfig && $itemPath === '/' && ($item['label'] ?? '') === 'Home') {
                continue; // ← SKIP root Home from navigation
            }

            // check if this item is in the current path hierarchy
            if ($currentPath === $itemPath || str_starts_with($currentPath, $itemPath.'/')) {
                $matches[] = $item;
            }
        }

        // sort by path depth (shortest first) for hierarchical order
        usort($matches, function ($a, $b) {
            $depthA = substr_count(trim($a['href'], '/'), '/');
            $depthB = substr_count(trim($b['href'], '/'), '/');

            return $depthA <=> $depthB;
        });

        return $matches;
    }

    /**
     * Add remaining path segments beyond navigation matches
     *
     * process URL segments that don't have navigation items.
     *
     * @param  array  $trail  Current breadcrumb trail
     * @param  string  $currentPath  Full path
     * @param  array  $matchedItems  Matched navigation items
     * @return array Updated breadcrumb trail
     */
    private function addRemainingSegments(array $trail, string $currentPath, array $matchedItems): array
    {
        // find the last matched navigation path
        $lastMatchedPath = !empty($matchedItems)
            ? rtrim(end($matchedItems)['href'], '/')
            : '';

        // check if current path extends beyond the last match
        if (!$lastMatchedPath || $currentPath === $lastMatchedPath) {
            return $trail;
        }

        // extract remaining segments
        $remainingPath = substr($currentPath, strlen($lastMatchedPath));
        $segments = array_filter(explode('/', $remainingPath));

        if (empty($segments)) {
            return $trail;
        }

        // build breadcrumbs for each remaining segment
        $accumulatedPath = $lastMatchedPath;
        $segmentCount = count($segments);

        foreach ($segments as $index => $segment) {
            $labelData = $this->segmentToLabel($segment);

            // skip hidden segments
            if ($labelData === null) {
                continue;
            }

            $isLastSegment = ($index === $segmentCount - 1);
            $accumulatedPath .= '/'.$segment;

            $trail[] = [
                'label' => is_array($labelData) ? $labelData['label'] : $labelData,
                'url' => $isLastSegment ? null : $accumulatedPath,
                'key' => is_array($labelData) ? ($labelData['key'] ?? null) : null,
            ];
        }

        return $trail;
    }

    /**
     * Convert URL segment to readable label with translation key
     *
     * transform segments like "email-test" → ["label" => "Email Test", "key" => "t-email-test"].
     *
     * @param  string  $segment  URL segment
     * @return array|string|null Label data (null = hide from breadcrumbs)
     */
    private function segmentToLabel(string $segment)
    {
        // check if this segment should be hidden
        $hiddenSegments = $this->config['hidden_segments'] ?? [];
        if (in_array($segment, $hiddenSegments, true)) {
            return;
        }

        // check for custom label overrides with translation keys
        $segmentLabels = $this->config['segment_labels'] ?? [];
        if (isset($segmentLabels[$segment])) {
            return $segmentLabels[$segment]; // Returns ['label' => '...', 'key' => '...']
        }

        // handle numeric IDs
        if (is_numeric($segment)) {
            $showIds = $this->config['show_ids'] ?? false;

            return $showIds ? "ID: $segment" : 'View';
        }

        // convert kebab-case and snake_case to Title Case (no translation key for dynamic segments)
        return ucwords(str_replace(['-', '_'], ' ', $segment));
    }
}
