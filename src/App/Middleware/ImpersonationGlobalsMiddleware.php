<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ImpersonationService;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TemplateViewerInterface;

/**
 * Exposes the impersonation banner to every template.
 *
 * A global rather than controller data because the banner has to appear on
 * every surface an impersonating admin can reach: the control panel, the
 * Lexicon front and all five blog themes. Missing it on one page is the case
 * where an admin acts as someone else without realising it.
 */
class ImpersonationGlobalsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TemplateViewerInterface $viewer,
        private ImpersonationService $impersonation,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->viewer->addGlobals([
            'impersonation' => $this->impersonation->banner(),
        ]);

        return $handler->handle($request);
    }
}
