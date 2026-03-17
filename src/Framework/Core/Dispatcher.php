<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Interfaces\AuthInterface;
use Framework\Http\Middleware\RequireRoleMiddleware;
use Framework\BaseController;
use Framework\Exceptions\PageNotFoundException;
use Framework\Handlers\ControllerRequestHandler;
use Framework\Handlers\MiddlewareRequestHandler;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Interfaces\TemplateViewerInterface;
use Framework\View\RouteContext;
use UnexpectedValueException;
use Whoops\Exception\Frame;

final class Dispatcher implements RequestHandlerInterface
{
    private array $middlewareConfig;

    private array $middlewareClasses = [];

    private array $globalMiddleware = [];

    public function __construct(
        private Router $router,
        private Container $container,
        private RouteContext $routeContext,
        array $middlewareConfig = [],
        private string $controllerNamespace = 'App\\Controllers',
    ) {
        $this->middlewareConfig = $middlewareConfig;
        $this->middlewareClasses = $this->middlewareConfig['aliases'] ?? $this->middlewareConfig;
        $this->globalMiddleware = $this->middlewareConfig['global'] ?? [];
    }

    public function handle(Request $request): Response
    {
        // 1. Resolve path and match route
        $path = $this->getPath($request->uri);
        $params = $this->matchRoute($path, $request->method);

        // should set these here so the view resolver can infer templates when controllers call view(null|$data).
        $this->routeContext->setControllerFqcn($this->getControllerName($params));
        $this->routeContext->setAction($this->getActionName($params));

        // 2. Build controller with injected dependencies
        $controller = $this->buildController($params);

        // 3. Resolve action arguments via reflection
        $args = $this->getActionArguments(
            $this->getControllerName($params),
            $this->getActionName($params),
            $params
        );

        // 4. Wrap controller in a handler
        $controllerHandler = $this->buildControllerHandler($controller, $params, $args);

        // 5. Collect middleware and wrap everything
        $middlewareHandler = $this->buildMiddlewareHandler($params, $controllerHandler);

        // 6. Execute the pipeline
        return $middlewareHandler->handle($request);
    }

    // --- routing ---

    private function matchRoute(string $path, string $method): array
    {
        $params = $this->router->match($path, $method);
        if ($params === false) {
            throw new PageNotFoundException("No route matched for '{$path}' with method '{$method}'");
        }

        return $params;
    }

    public function getPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false) {
            throw new UnexpectedValueException("Malformed URL: '{$uri}'");
        }

        return $path;
    }

    // --- controller resolution ---

    private function buildController(array $params): BaseController
    {
        $controllerName = $this->getControllerName($params);
        $controller = $this->container->get($controllerName);

        // inject framework-level dependencies
        $controller->setViewer($this->container->get(TemplateViewerInterface::class));
        $controller->setResponse($this->container->get(Response::class));
        $controller->setValidatorFactory($this->container->get('validator.factory'));

        // inject application-level dependencies if the controller supports them
        if ($controller instanceof \Framework\Interfaces\SessionAwareInterface) {
            $controller->setSession($this->container->get(\Framework\Session::class));
        }

        return $controller;
    }

    private function buildControllerHandler(BaseController $controller, array $params, array $args): ControllerRequestHandler
    {
        $actionName = $this->getActionName($params);

        return new ControllerRequestHandler($controller, $actionName, $args);
    }

    private function getControllerName(array $params): string
    {
        $controller = $params['controller'] ?? '';
        $controller = $this->normalizeControllerName($controller);

        $namespace = $this->controllerNamespace;
        if (!empty($params['namespace'])) {
            $namespace .= '\\'.trim((string) $params['namespace'], '\\');
        }

        return $namespace.'\\'.$controller;
    }

    private function getActionName(array $params): string
    {
        $action = $params['action'] ?? 'index';

        return $this->normalizeActionName($action);
    }

    private function normalizeControllerName(string $name): string
    {
        // Convert kebab-case to PascalCase (e.g. "user-profile" → "UserProfile")
        $name = str_replace('-', '', ucwords(strtolower($name), '-'));

        $inflector = \Doctrine\Inflector\InflectorFactory::create()->build();
        if (stripos($name, 'Controller') === false) {
            $name = $inflector->singularize($name).'Controller';
        }

        return $name;
    }

    private function normalizeActionName(string $raw): string
    {
        // Convert kebab-case to camelCase (e.g. "show-profile" → "showProfile")
        return lcfirst(str_replace('-', '', ucwords(strtolower($raw), '-')));
    }

    private function getActionArguments(string $controller, string $action, array $params): array
    {
        $method = new \ReflectionMethod($controller, $action);
        $args = [];

        foreach ($method->getParameters() as $parameter) {
            $args[$parameter->getName()] = $this->resolveParameterValue($parameter, $params);
        }

        return $args;
    }

    private function resolveParameterValue(\ReflectionParameter $parameter, array $params): mixed
    {
        $name = $parameter->getName();

        if (array_key_exists($name, $params)) {
            return $params[$name];
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return null;
    }

    // --- middleware orchestration ---

    private function buildMiddlewareHandler(array $params, ControllerRequestHandler $controllerHandler): MiddlewareRequestHandler
    {
        $globalInstances = $this->buildGlobalMiddleware();
        $routeInstances = $this->getMiddleware($params);

        $allMiddleware = array_merge($globalInstances, $routeInstances);

        return new MiddlewareRequestHandler($allMiddleware, $controllerHandler);
    }

    private function buildGlobalMiddleware(): array
    {
        $instances = [];
        foreach ($this->globalMiddleware as $class) {
            $instances[] = $this->container->get($class);
        }

        return $instances;
    }

    private function getMiddleware(array $params): array
    {
        if (!isset($params['middleware']) || $params['middleware'] === null) {
            return [];
        }

        $keys = $this->parseMiddlewareKeys($params['middleware']);
        $instances = [];

        foreach ($keys as $key) {
            if ($key === '') {
                continue;
            }

            [$name, $arg] = $this->splitMiddlewareKey($key);
            $class = $this->resolveMiddlewareClass($name);

            $instances[] = $this->createMiddlewareInstance($name, $class, $arg);
        }

        return $instances;
    }

    private function parseMiddlewareKeys(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        return array_values(
            array_filter(
                array_map('trim', explode('|', (string) $raw))
            )
        );
    }

    private function splitMiddlewareKey(string $key): array
    {
        return array_pad(explode(':', $key, 2), 2, null);
    }

    private function resolveMiddlewareClass(string $name): string
    {
        if (!array_key_exists($name, $this->middlewareClasses)) {
            throw new UnexpectedValueException("Middleware '{$name}' not found in config settings.");
        }

        return $this->middlewareClasses[$name];
    }

    private function createMiddlewareInstance(string $name, string $class, ?string $arg): object
    {
        // preserve special-case role middleware logic
        if ($name === 'role' && $arg !== null) {
            $auth = $this->container->get(AuthInterface::class);
            $roles = array_map('trim', explode(',', $arg));

            if ($class === RequireRoleMiddleware::class) {
                return new RequireRoleMiddleware($auth, $roles);
            }

            return new $class($auth, $roles);
        }

        return $this->container->get($class);
    }
}
