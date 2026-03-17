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

// Register handlers before any application code runs
set_error_handler([ErrorHandler::class, 'handleError']);
set_exception_handler([ErrorHandler::class, 'handleException']);

// Shared service container for both web and CLI
$container = require ROOT_PATH.'/config/services.php';

App::setContainer($container);

return $container;
