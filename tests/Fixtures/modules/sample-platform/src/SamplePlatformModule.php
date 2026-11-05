<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SamplePlatform;

use Framework\Core\Container;
use Framework\Modules\ModuleContext;
use Framework\Modules\ModuleInterface;
use Framework\Modules\ModuleRoutes;

/**
 * The smallest site wide module: one page on the site, one in the control panel and one slot.
 */
final class SamplePlatformModule implements ModuleInterface
{
    public function register(Container $container): void
    {
    }

    public function boot(ModuleContext $context): void
    {
        $route = fn (string $action) => [
            'controller_class' => HelloController::class,
            'action' => $action,
            'method' => 'GET',
        ];

        $context->frontRoutes(fn (ModuleRoutes $routes) => $routes->add('/hello', $route('show')));
        $context->manageRoutes(fn (ModuleRoutes $routes) => $routes->add('/settings', $route('settings')));
        $context->slot('body.end', new MarkSlot());
    }
}
