<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Registers a global translation callable `t` for all templates in this request.
 * This keeps views simple and delegates i18n logic to the service.
 */
class TranslationGlobalsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TemplateViewerInterface $viewer,   // View layer that can accept globals.
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Same lookup as chrome_translate(), so views and controllers share one
        // memoized translator per chrome locale.
        $this->viewer->addGlobals([
            't' => static fn (string|array $key, array $params = []): string => chrome_translate($key, $params),
        ]);

        // Continue the PSR-15 pipeline.
        return $handler->handle($request);
    }
}
