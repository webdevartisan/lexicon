<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\HeadI18nBuilder;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * HeadI18nGlobals
 *
 * Purpose:
 * - Provide head.* variables to templates for html lang/dir, canonical, hreflang
 *   alternates and OG locale.
 * - Runs in the normal middleware pipeline, so it only executes for rendered pages.
 *
 * Requirements:
 * - LocalePrefixIntake has already normalized the URL and resolved LocaleState.
 * - Template viewer supports addGlobals(array $vars).
 *
 * Note on ordering:
 * - This runs before the controller, so it can only see a page's locale set when
 *   something earlier established one. Pages that learn their own set later, such
 *   as blog pages, rebuild these globals themselves through the same builder.
 *   Globals are merged at render time, so the later call wins.
 */
final class HeadI18nGlobals implements MiddlewareInterface
{
    public function __construct(
        private TemplateViewerInterface $viewer,
        private HeadI18nBuilder $builder
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Path arrives without the locale prefix thanks to pre-routing.
        $full = $request->uri ?? '/';

        $this->viewer->addGlobals($this->builder->build(
            parse_url($full, PHP_URL_PATH) ?: '/',
            parse_url($full, PHP_URL_QUERY) ?: null
        ));

        return $handler->handle($request);
    }
}
