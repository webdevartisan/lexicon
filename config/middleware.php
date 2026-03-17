<?php

declare(strict_types=1);

return [
    'aliases' => [
        'auth' => App\Middleware\AuthMiddleware::class,
        'role' => Framework\Http\Middleware\RequireRoleMiddleware::class,
        'message' => App\Middleware\ChangeResponseExample::class,
        'trim' => App\Middleware\ChangeRequestExample::class,
        'theme' => \App\Middleware\ThemeResolverMiddleware::class,
    ],
    'global' => [
        App\Middleware\SecurityHeadersMiddleware::class,
        App\Middleware\LocaleMiddleware::class,
        App\Middleware\TranslationGlobalsMiddleware::class,
        App\Middleware\NavGlobalsMiddleware::class,
        App\Middleware\HeadI18nGlobals::class,
        App\Middleware\LocalizeAnchorHrefs::class,
        App\Middleware\BreadcrumbMiddleware::class,
        Framework\Cache\CacheMiddleware::class,
        Framework\Http\Middleware\ContainerDebugMiddleware::class,
        App\Middleware\NavigationActiveMiddleware::class,
        App\Middleware\HandleValidationExceptionMiddleware::class,
        // App\Middleware\CsrfMiddleware::class,
        // App\Middleware\LocaleMiddleware::class,
    ],
];
