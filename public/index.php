<?php

declare(strict_types=1);

use App\Auth;
use App\Http\PreRouting\PipelineRunner;
use Framework\Core\Dispatcher;
use Framework\Core\Request;

/**
 * HTTP Entry Point
 *
 * bootstrap the framework for web requests and route them through
 * middleware to controllers. This handles browser/API requests only.
 *
 * For CLI commands, see the /cli entry point instead.
 */

require dirname(__DIR__).'/bootstrap/constants.php';

// bootstrap the shared application components (autoloader, env, container, error handling)
$container = require ROOT_PATH.'/bootstrap/app.php';

// ========================================================================
// HTTP-SPECIFIC INITIALIZATION
// ========================================================================

// load HTTP-specific configuration
$router = require ROOT_PATH.'/config/routes.php';
$middleware = require ROOT_PATH.'/config/middleware.php';

// initialize the route context for view rendering
$routeContext = $container->get(\Framework\View\RouteContext::class);

// touch Auth early so Session and security state are initialized before middleware
$container->get(Auth::class);

// build the HTTP dispatcher for routing requests through middleware and controllers
$dispatcher = new Dispatcher(
    $router,
    $container,
    $routeContext,
    $middleware
);

// create a Request object from PHP superglobals
$request = Request::createFromGlobals();

// run any pre-routing logic (HTTPS redirects, maintenance mode, etc.)
$preRoutingRunner = new PipelineRunner();
$preRoutingRunner->run($request);

// hand off the request to the dispatcher for middleware and controller execution
$response = $dispatcher->handle($request);

// send the final HTTP response to the client
$response->send();

// Fallback tick for installs with no crontab, checked only after the visitor
// already has their page. The env flag is read directly so that a normal
// install, where this is off, does no container or database work at all.
if (filter_var($_ENV['SCHEDULE_PSEUDO_CRON'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    // Hands the connection back on php-fpm so nothing below delays the browser.
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    $container->get(\App\Services\PseudoCron::class)->maybeTick($request);
}
