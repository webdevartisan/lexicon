<?php

declare(strict_types=1);

namespace Framework\Core;

use RuntimeException;

/**
 * Application core facade.
 *
 * Holds the DI container instance so that framework code and global helpers
 * (auth(), csrf(), etc.) can resolve services without relying on globals
 * or static singletons in each service.
 */
final class App
{
    /**
     * The main application container instance.
     *
     * Set once during bootstrap in public/index.php and then used read-only.
     */
    private static ?Container $container = null;

    /**
     * Register the container instance for global framework access.
     *
     * This is intended to be called once from the front controller after the
     * container is built from config/services.php.
     */
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    /**
     * Check if container has been initialized.
     */
    public static function hasContainer(): bool
    {
        return self::$container !== null;
    }

    /**
     * Get the application container.
     *
     * @throws RuntimeException When the container has not been set yet.
     */
    public static function container(): Container
    {
        if (self::$container === null) {
            // If this ever triggers, something is calling helpers before index.php
            // finishes bootstrapping the container.
            throw new RuntimeException('Application container has not been initialized.');
        }

        return self::$container;
    }
}
