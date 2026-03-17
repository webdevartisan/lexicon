<?php

declare(strict_types=1);

namespace Framework\Interfaces;

use Framework\Core\Request;
use Framework\Core\Response;

/**
 * Middleware component in the HTTP request/response pipeline.
 *
 * Purpose:
 * - Wrap the core request handler with cross-cutting concerns such as
 *   authentication, authorization, CSRF protection, logging, etc.
 * - Each middleware can short-circuit (return a Response) or delegate to
 *   the next handler in the chain.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming request.
     *
     * A middleware can:
     * - return a Response immediately (e.g. redirect, 403, JSON error), or
     * - call $next->handle($request) to continue the pipeline.
     */
    public function process(Request $request, RequestHandlerInterface $next): Response;
}
