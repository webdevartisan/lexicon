<?php

declare(strict_types=1);

return [
    'aliases' => [
        'auth' => App\Middleware\AuthMiddleware::class,
        'verified' => App\Middleware\VerifiedMiddleware::class,
        'theme' => \App\Middleware\ThemeResolverMiddleware::class,
    ],
    'global' => [
        // First, so a blocked IP is refused before any page cache can answer for it
        App\Middleware\BlockedIpMiddleware::class,
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
        Framework\Security\CsrfMiddleware::class,
        App\Middleware\HandleValidationExceptionMiddleware::class,
    ],
];
