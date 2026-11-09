<?php

declare(strict_types=1);

namespace Framework\Modules;

use Framework\Core\Router;
use LogicException;

/**
 * The routing a module gets: paths under its own prefix, handled by its own controllers.
 *
 * Modules never see the Router itself, so they cannot point a route at a core
 * controller or add one outside their prefix.
 */
final class ModuleRoutes
{
    public function __construct(private Router $router, private string $moduleKey) {}

    /**
     * Add a route under the module prefix.
     *
     * @param  string  $path  Path below the prefix, such as /stops
     * @param  array<string, mixed>  $params  Must name controller_class, plus action and method
     */
    public function add(string $path, array $params): void
    {
        if (empty($params['controller_class']) || isset($params['controller']) || isset($params['namespace'])) {
            throw new LogicException("Module {$this->moduleKey} routes must name a controller_class and nothing else");
        }

        $params['module'] = $this->moduleKey;
        $this->router->add($path, $params);
    }
}
