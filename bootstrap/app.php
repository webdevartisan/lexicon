<?php

declare(strict_types=1);

use Framework\Core\App;
use Framework\Core\Dotenv;
use Framework\Core\ErrorHandler;

/**
 * Framework Bootstrap
 *
 * Initializes core application components shared by both HTTP (web)
 * and CLI (console) entry points. Responsibilities include:
 * - Autoloading
 * - Environment configuration
 * - Error and exception handling
 * - Service container initialization
 *
 * HTTP-specific components (routing, middleware, sessions) are handled
 * by public/index.php.
 *
 * CLI-specific components (console kernel) are initialized by the CLI entry point.
 */
if (!defined('ROOT_PATH')) {
    throw new RuntimeException('ROOT_PATH must be defined before bootstrapping');
}

require ROOT_PATH.'/vendor/autoload.php';

$dotenv = new Dotenv();
$dotenv->load(ROOT_PATH.'/.env');

// The database connection is pinned to UTC; PHP must match or date() writes drift by the php.ini offset
date_default_timezone_set('UTC');

// Register handlers before any application code runs
set_error_handler([ErrorHandler::class, 'handleError']);
set_exception_handler([ErrorHandler::class, 'handleException']);

// Shared service container for both web and CLI
$container = require ROOT_PATH.'/config/services.php';

App::setContainer($container);

// Mailables build themselves in their constructor, so they cannot take the
// renderer as a dependency. Point them at the one that reads what was saved in
// the control panel; anything that never boots the app gets the shipped emails.
\App\Mail\Mailable::resolveTemplatesUsing(
    static fn (): \App\Services\EmailRenderer => $container->get(\App\Services\EmailRenderer::class)
);

return $container;
