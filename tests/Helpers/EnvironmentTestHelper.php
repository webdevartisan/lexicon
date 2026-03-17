<?php

declare(strict_types=1);

namespace Tests\Helpers;

class EnvironmentTestHelper
{
    private static array $originalEnv = [];

    private static array $originalServer = [];

    /**
     * Sets environment variable and stores original value for restoration.
     * Automatically tracks changes for cleanup in restore().
     *
     * @param  string  $key  Environment variable key
     * @param  mixed  $value  Value to set
     */
    public static function setEnv(string $key, mixed $value): void
    {
        if (!isset(self::$originalEnv[$key])) {
            self::$originalEnv[$key] = $_ENV[$key] ?? null;
        }
        $_ENV[$key] = $value;
    }

    /**
     * Sets server variable and stores original value for restoration.
     * Automatically tracks changes for cleanup in restore().
     *
     * @param  string  $key  Server variable key
     * @param  mixed  $value  Value to set
     */
    public static function setServer(string $key, mixed $value): void
    {
        if (!isset(self::$originalServer[$key])) {
            self::$originalServer[$key] = $_SERVER[$key] ?? null;
        }
        $_SERVER[$key] = $value;
    }

    /**
     * Restores all environment and server variables to original values.
     * Call this in afterEach() to prevent test pollution between tests.
     */
    public static function restore(): void
    {
        foreach (self::$originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        foreach (self::$originalServer as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }

        self::$originalEnv = [];
        self::$originalServer = [];
    }

    /**
     * Sets production environment.
     */
    public static function setProduction(): void
    {
        self::setEnv('APP_ENV', 'production');
    }

    /**
     * Sets development environment.
     */
    public static function setDevelopment(): void
    {
        self::setEnv('APP_ENV', 'development');
    }

    /**
     * Sets staging environment.
     */
    public static function setStaging(): void
    {
        self::setEnv('APP_ENV', 'staging');
    }

    /**
     * Simulates HTTPS connection via HTTPS server variable.
     */
    public static function enableHttps(): void
    {
        self::setServer('HTTPS', 'on');
    }

    /**
     * Simulates HTTPS connection via SERVER_PORT 443.
     */
    public static function enableHttpsViaPort(): void
    {
        self::setServer('SERVER_PORT', '443');
    }

    /**
     * Simulates HTTPS connection via X-Forwarded-Proto header.
     * Used when behind reverse proxy or load balancer.
     */
    public static function enableHttpsViaProxy(): void
    {
        self::setServer('HTTP_X_FORWARDED_PROTO', 'https');
    }
}
