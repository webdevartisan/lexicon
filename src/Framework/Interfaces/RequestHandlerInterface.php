<?php

declare(strict_types=1);

namespace Framework\Interfaces;

use Framework\Core\Request;
use Framework\Core\Response;

/**
 * Core request handler contract.
 *
 * Purpose:
 * - Define a standard way to turn an HTTP Request into a Response.
 * - Allow handlers to be composed (e.g. middleware pipelines, routers, controllers).
 *
 * Typical implementations:
 * - Dispatcher: matches routes and invokes controllers.
 * - MiddlewareRequestHandler: executes a middleware stack, then delegates to a controller handler.
 * - Simple handlers for special cases (e.g. health checks, static responses).
 */
interface RequestHandlerInterface
{
    /**
     * Handle an incoming request and return a response.
     */
    public function handle(Request $request): Response;
}
