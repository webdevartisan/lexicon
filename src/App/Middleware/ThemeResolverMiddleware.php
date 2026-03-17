<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\BlogSettingsModel;
use App\Services\ThemeService;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Resolves and activates the public theme for:
 * - /{locale?}/blog/{blogSlug}[/{postSlug}]
 *
 * Exposes:
 * - $theme   (string|null) active theme to templates
 * - $asset() (callable)    helper for /themes/{theme}/public/... URLs
 */
final class ThemeResolverMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ThemeService $themes,
        private TemplateViewerInterface $viewer,
        private BlogSettingsModel $blogs
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Split path into segments
        $path = trim(parse_url($request->uri, PHP_URL_PATH) ?? '/', '/');
        $segments = $path === '' ? [] : explode('/', $path);

        // Known locale prefixes (keep in sync with your i18n config)
        $supportedLocales = ['en', 'fr', 'de', 'el', 'ar'];

        // Strip locale if first segment matches
        if (isset($segments[0]) && in_array(strtolower($segments[0]), $supportedLocales, true)) {
            array_shift($segments);
        }

        $theme = null;

        // Expect: /blog/{blogSlug}[/{postSlug}]
        if (isset($segments[0]) && $segments[0] === 'blog' && isset($segments[1])) {
            $blogSlug = $segments[1];

            // Mirror your route constraint
            if (preg_match('~^[A-Za-z0-9_-]+$~', $blogSlug)) {
                // Resolve theme by blog slug only
                $theme = $this->blogs->findThemeByBlogSlug($blogSlug);
            }
        }

        // Activate theme and publish globals (defaults when null)
        $this->activateAndPublish($theme);

        return $handler->handle($request);
    }

    private function activateAndPublish(?string $theme): void
    {
        // Activates theme; ThemeService falls back to default view roots when null
        $this->themes->activate($theme ?: null);

        // Publish theme and asset() to viewer globals if supported
        if (method_exists($this->viewer, 'addGlobals')) {
            $asset = fn (string $p) => $this->themes->assetUrl($p); // /themes/{active}/public/... or /assets/... fallback
            $this->viewer->addGlobals(['theme' => $theme, 'asset' => $asset]);
        }
    }
}
