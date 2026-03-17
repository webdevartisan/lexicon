<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\TranslationService;
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
        // Expose `$t` to all templates before rendering begins.
        if (method_exists($this->viewer, 'addGlobals')) {
            $this->viewer->addGlobals([
                // Accept a string or path array plus optional params for interpolation.
                't' => function (string|array $key, array $params = []): string {
                    // Resolve the translator fresh per call, using the current session locale
                    $translator = new TranslationService($_SESSION['locale'] ?? 'en');

                    return $translator->translate($key, $params); // Dual resolution inside service.
                },
            ]);
        }

        // Continue the PSR-15 pipeline.
        return $handler->handle($request);
    }
}
