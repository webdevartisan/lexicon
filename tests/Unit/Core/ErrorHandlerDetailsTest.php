<?php

declare(strict_types=1);

use Framework\Core\ErrorHandler;

/**
 * Stack traces must only reach error pages in an explicitly named dev environment.
 */
dataset('appEnvironments', [
    'development' => ['development', true],
    'local' => ['local', true],
    'testing' => ['testing', true],
    'production' => ['production', false],
    'staging' => ['staging', false],
    'typo' => ['prod', false],
    'wrong case' => ['Development', false],
    'empty' => ['', false],
    'unset' => [null, false],
]);

test('showsDetails only allows named dev environments', function (?string $env, bool $expected) {
    $previous = $_ENV['APP_ENV'] ?? null;

    try {
        if ($env === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $env;
        }

        expect(ErrorHandler::showsDetails())->toBe($expected);
    } finally {
        if ($previous === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $previous;
        }
    }
})->with('appEnvironments');
