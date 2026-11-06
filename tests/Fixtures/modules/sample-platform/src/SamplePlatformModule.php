<?php

declare(strict_types=1);

namespace Tests\Fixtures\Modules\SamplePlatform;

use Framework\Core\Container;
use Framework\Modules\ModuleContext;
use Framework\Modules\ModuleInterface;
use Framework\Modules\ModuleRoutes;
use Framework\Modules\ModuleUninstaller;

/**
 * The smallest site wide module: one page on the site, one in the control panel and one slot.
 * It counts its uninstall cleanups so tests can see it ran once.
 */
final class SamplePlatformModule implements ModuleInterface, ModuleUninstaller
{
    public static int $uninstalls = 0;

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

    /**
     * Count the run; the module made nothing else to delete.
     */
    public function uninstall(): void
    {
        self::$uninstalls++;
    }
}
