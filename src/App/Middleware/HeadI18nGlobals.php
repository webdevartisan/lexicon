<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\LocaleRegistry;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * HeadI18nGlobals
 *
 * Purpose:
 * - Provide head.* variables to templates for html lang/dir, canonical, hreflang alternates, and OG locale.
 * - Runs in the normal middleware pipeline (not pre-routing), so it only executes for rendered pages.
 *
 * Requirements:
 * - LocalePrefixIntake has already normalized the URL and set the session locale.
 * - Template viewer supports addGlobals(array $vars).
 */
final class HeadI18nGlobals implements MiddlewareInterface
{
    public function __construct(private TemplateViewerInterface $viewer, private LocaleRegistry $registry) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $supported = $this->registry->supported();
        $default = $this->registry->default();

        // Resolve current locale (session/cookie) and validate
        $current = strtolower($_SESSION['locale'] ?? ($_COOKIE['locale'] ?? $default));
        if (!in_array($current, $supported, true)) {
            $current = $default;
        }

        // Request path (already without locale prefix thanks to pre-routing)
        $full = $request->uri ?? '/';
        $path = parse_url($full, PHP_URL_PATH) ?: '/';
        $qs = parse_url($full, PHP_URL_QUERY) ?: null;

        $origin = $this->origin();

        // Canonical for current locale
        $canonical = $origin.'/'.$current.($path === '/' ? '' : $path).($qs ? ('?'.$qs) : '');

        // Hreflang alternates
        $alternates = [];
        foreach ($supported as $lang) {
            $href = $origin.'/'.$lang.($path === '/' ? '' : $path).($qs ? ('?'.$qs) : '');
            $alternates[] = ['hreflang' => $lang, 'href' => $href];
        }
        $xDefaultUrl = $origin.'/'.$default.($path === '/' ? '' : $path).($qs ? ('?'.$qs) : '');

        // Open Graph locale mapping (adjust regions as you prefer)
        $ogMap = [
            'en' => 'en_US',
            'el' => 'el_GR',
        ];
        $ogLocale = $ogMap[$current] ?? ($current.'_'.strtoupper($current));
        $ogLocaleAlternates = [];
        foreach ($supported as $lang) {
            if ($lang === $current) {
                continue;
            }
            $ogLocaleAlternates[] = $ogMap[$lang] ?? ($lang.'_'.strtoupper($lang));
        }

        $isRtl = '';
        if ($this->registry->isRtl($current)) {
            $isRtl = 'dir="rtl"';
        }

        // Inject into the template globals
        $this->viewer->addGlobals([
            // For the <html> element
            'supportedLocales' => $supported,
            'defaultLocale' => $default,
            'currentLang' => $current,
            'isRtl' => $isRtl,

            // For <head>
            'head' => [
                'canonicalUrl' => $canonical,
                'alternates' => $alternates,
                'xDefaultUrl' => $xDefaultUrl,
                'ogLocale' => $ogLocale,
                'ogLocaleAlternates' => $ogLocaleAlternates,
            ],
        ]);

        return $handler->handle($request);
    }

    private function origin(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return $scheme.'://'.$host;
    }
}
