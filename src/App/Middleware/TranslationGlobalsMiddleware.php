<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\LocaleState;
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
        // Built on first use and reused for the rest of the request. This used to
        // construct a fresh service on every single $t() call, so a page with
        // fifty translated strings re-read and re-parsed the locale JSON fifty
        // times. Keyed by locale so the memo can never serve the wrong language.
        $translators = [];

        // Expose `$t` to all templates before rendering begins.
        $this->viewer->addGlobals([
            // Accept a string or path array plus optional params for interpolation.
            't' => function (string|array $key, array $params = []) use (&$translators): string {
                // Interface strings follow the chrome locale: the content locale
                // for guests, the reader's own preference once they are signed in.
                $locale = LocaleState::get()->chromeLocale;

                $translators[$locale] ??= new TranslationService($locale);

                return $translators[$locale]->translate($key, $params); // Dual resolution inside service.
            },
        ]);

        // Continue the PSR-15 pipeline.
        return $handler->handle($request);
    }
}
