<?php

declare(strict_types=1);

namespace Framework\Modules;

use Closure;
use Framework\Core\Router;
use LogicException;

/**
 * What a module may add to the platform while it boots: routes, slots and console commands.
 */
final class ModuleContext
{
    /**
     * Where each scope mounts its routes: prefix and middleware for front pages and for manage pages.
     * Blog modules live under a blog, with optional control panel pages; platform modules live on the
     * site and in the control panel.
     * Manage pages are guarded on the route (manage-blog-extra, manage-extras), so a module
     * controller that forgets its own check still cannot open them to everyone.
     */
    private const AREAS = [
        'blog' => [
            'front' => ['/blog/{blogSlug:[A-Za-z0-9_-]+}/m/', ['theme']],
            'manage' => ['/dashboard/blog/{blogId:\d+}/m/', ['auth', 'manage-blog-extra']],
            'admin' => ['/admin/m/', ['auth', 'two-factor', 'manage-extras']],
        ],
        'platform' => [
            'front' => ['/m/', []],
            'manage' => ['/admin/m/', ['auth', 'two-factor', 'manage-extras']],
        ],
    ];

    /** @var list<Closure> */
    private array $frontRoutes = [];

    /** @var list<Closure> */
    private array $manageRoutes = [];

    /** @var list<Closure> */
    private array $adminRoutes = [];

    /** @var list<array{slot: string, renderer: SlotRenderer}> */
    private array $slots = [];

    /** @var array<string, class-string> */
    private array $commands = [];

    public function __construct(private ModuleManifest $manifest) {}

    /**
     * The module key.
     */
    public function key(): string
    {
        return $this->manifest->key;
    }

    /**
     * Public pages: /blog/{blogSlug}/m/<key> for a blog module, /m/<key> for a platform module.
     *
     * @param  Closure(ModuleRoutes): void  $define
     */
    public function frontRoutes(Closure $define): void
    {
        $this->frontRoutes[] = $define;
    }

    /**
     * Management pages: /dashboard/blog/{blogId}/m/<key> for a blog module,
     * /admin/m/<key> in the control panel for a platform module.
     *
     * @param  Closure(ModuleRoutes): void  $define
     */
    public function manageRoutes(Closure $define): void
    {
        $this->manageRoutes[] = $define;
    }

    /**
     * Control panel pages of a blog module, at /admin/m/<key> behind the same guard as /admin/extras.
     * For site wide settings a blog module needs, such as data every blog shares.
     *
     * @param  Closure(ModuleRoutes): void  $define
     *
     * @throws LogicException For a platform module, whose manage routes already live there
     */
    public function adminRoutes(Closure $define): void
    {
        if ($this->manifest->scope !== 'blog') {
            throw new LogicException(
                "Module {$this->manifest->key}: platform modules put control panel pages in manageRoutes"
            );
        }

        $this->adminRoutes[] = $define;
    }

    /**
     * Draw into a named slot the manifest declared.
     *
     * @throws LogicException When the manifest does not list the slot
     */
    public function slot(string $name, SlotRenderer $renderer): void
    {
        if (!in_array($name, $this->manifest->slots, true)) {
            throw new LogicException("Module {$this->manifest->key} did not declare slot {$name} in module.json");
        }

        $this->slots[] = ['slot' => $name, 'renderer' => $renderer];
    }

    /**
     * Console commands, each named <key>:<something> so they never shadow core commands.
     *
     * @param  array<string, class-string>  $map  Command name => command class
     *
     * @throws LogicException When a name lacks the module key prefix
     */
    public function commands(array $map): void
    {
        foreach ($map as $name => $class) {
            if (!str_starts_with((string) $name, $this->manifest->key.':')) {
                throw new LogicException("Module command {$name} must start with {$this->manifest->key}:");
            }
            $this->commands[$name] = $class;
        }
    }

    /**
     * Register the collected routes on the router. Called once by the booter.
     */
    public function applyRoutes(Router $router): void
    {
        $areas = self::AREAS[$this->manifest->scope];

        $this->mount($router, $areas['front'], $this->frontRoutes);
        $this->mount($router, $areas['manage'], $this->manageRoutes);
        if (isset($areas['admin'])) {
            $this->mount($router, $areas['admin'], $this->adminRoutes);
        }
    }

    /**
     * @param  array{0: string, 1: list<string>}  $area  Prefix up to the module key, and the group middleware
     * @param  list<Closure>  $definitions
     */
    private function mount(Router $router, array $area, array $definitions): void
    {
        $key = $this->manifest->key;
        [$prefix, $middleware] = $area;

        $router->group(
            ['prefix' => $prefix.$key, 'middleware' => $middleware],
            fn (Router $group) => $this->define($definitions, new ModuleRoutes($group, $key))
        );
    }

    /**
     * @param  list<Closure>  $definitions
     */
    private function define(array $definitions, ModuleRoutes $routes): void
    {
        foreach ($definitions as $define) {
            $define($routes);
        }
    }

    /**
     * @return list<array{slot: string, renderer: SlotRenderer}>
     */
    public function slots(): array
    {
        return $this->slots;
    }

    /**
     * @return array<string, class-string>
     */
    public function commandMap(): array
    {
        return $this->commands;
    }
}
