<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

// Load autoloader
require ROOT_PATH . '/vendor/autoload.php';

// Load environment
$dotenv = new Framework\Core\Dotenv();
$dotenv->load(ROOT_PATH . '/tests/.env.testing');

// Create and register container
$container = require ROOT_PATH . '/config/services.php';
Framework\Core\App::setContainer($container);

// Initialize session for tests
$_SESSION = [];
