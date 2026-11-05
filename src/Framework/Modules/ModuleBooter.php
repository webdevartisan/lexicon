<?php

declare(strict_types=1);

namespace Framework\Modules;

use Framework\Core\Container;
use Framework\Core\Router;
use Throwable;

/**
 * Starts the installed modules: register() on every request, boot() once when routes, slots or commands are needed.
 *
 * A module that throws while starting is logged and left out, so one broken
 * module never takes the rest of the site down with it.
 */
final class ModuleBooter
{
    /** @var array<string, array{manifest: ModuleManifest, module: ModuleInterface}> */
    private array $modules = [];

    /** @var list<ModuleContext>|null */
    private ?array $contexts = null;

    public function __construct(
        private ModuleRegistry $registry,
        private Container $container,
        private ModuleInstances $instances,
    ) {
    }

    /**
     * Load and register every module that is switched on.
     */
    public function registerAll(): void
    {
        try {
            $running = $this->registry->running();
        } catch (Throwable $e) {
            // The modules table may not exist yet on a fresh database; the site runs without modules then.
            error_log('Modules not loaded: '.$e->getMessage());
            return;
        }

        foreach ($running as $key => $manifest) {
            try {
                $this->modules[$key] = ['manifest' => $manifest, 'module' => $this->load($manifest)];
            } catch (Throwable $e) {
                error_log("Module {$key} failed to register: ".$e->getMessage());
            }
        }
    }

    /**
     * Boot the modules and add their routes.
     */
    public function bootRoutes(Router $router): void
    {
        foreach ($this->contexts() as $context) {
            $context->applyRoutes($router);
        }
    }

    /**
     * Console commands of every booted module.
     *
     * @return array<string, class-string>
     */
    public function commands(): array
    {
        $commands = [];
        foreach ($this->contexts() as $context) {
            $commands += $context->commandMap();
        }

        return $commands;
    }

    /**
     * Slot renderers of every booted module, grouped by slot name.
     *
     * @return array<string, list<array{module: string, renderer: SlotRenderer}>>
     */
    public function slots(): array
    {
        $slots = [];
        foreach ($this->contexts() as $context) {
            foreach ($context->slots() as $slot) {
                $slots[$slot['slot']][] = ['module' => $context->key(), 'renderer' => $slot['renderer']];
            }
        }

        return $slots;
    }

    private function load(ModuleManifest $manifest): ModuleInterface
    {
        $module = $this->instances->for($manifest);
        if (!$module instanceof ModuleInterface) {
            throw new InvalidManifestException("{$manifest->class} does not implement ModuleInterface");
        }
        $module->register($this->container);

        return $module;
    }

    /**
     * @return list<ModuleContext>
     */
    private function contexts(): array
    {
        if ($this->contexts !== null) {
            return $this->contexts;
        }

        $this->contexts = [];
        foreach ($this->modules as $key => $entry) {
            $context = new ModuleContext($entry['manifest']);
            try {
                $entry['module']->boot($context);
            } catch (Throwable $e) {
                error_log("Module {$key} failed to boot: ".$e->getMessage());
                continue;
            }
            $this->contexts[] = $context;
        }

        return $this->contexts;
    }
}
