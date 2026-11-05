<?php

declare(strict_types=1);

namespace Framework\Modules;

use Framework\Core\Container;

/**
 * The main class of a module, named in its module.json.
 */
interface ModuleInterface
{
    /**
     * Bind the module's services. Runs on every request while the module is installed.
     */
    public function register(Container $container): void;

    /**
     * Add routes, slots and commands through the context.
     */
    public function boot(ModuleContext $context): void;
}
