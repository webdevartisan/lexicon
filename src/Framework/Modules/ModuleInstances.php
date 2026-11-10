<?php

declare(strict_types=1);

namespace Framework\Modules;

use Framework\Core\Container;

/**
 * Builds each module's main class once per request, through the container.
 *
 * The booter, the installer and the platform duties all ask here, so a module is
 * never constructed twice and its classes are always loadable first.
 */
final class ModuleInstances
{
    /** @var array<string, object> */
    private array $instances = [];

    public function __construct(private Container $container) {}

    /**
     * The module's main class, built but not booted.
     *
     * @throws InvalidManifestException When the class named in module.json cannot be loaded
     */
    public function for(ModuleManifest $manifest): object
    {
        return $this->instances[$manifest->key] ??= $this->build($manifest);
    }

    private function build(ModuleManifest $manifest): object
    {
        ModuleClassLoader::register($manifest);
        if (!class_exists($manifest->class)) {
            throw new InvalidManifestException("Module {$manifest->key} class {$manifest->class} was not found");
        }

        return $this->container->get($manifest->class);
    }
}
