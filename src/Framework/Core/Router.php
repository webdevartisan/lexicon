<?php

declare(strict_types=1);

namespace Framework\Core;

/**
 * Class Router
 *
 * Purpose:
 * - Register application routes with optional grouping (prefix, namespace, middleware, methods).
 * - Match an incoming request path + HTTP method to the first route whose pattern fits.
 *
 * Design:
 * - Routes are stored as a flat array of ['path' => '/foo/{id}', 'params' => [...]].
 * - Group attributes are kept on a stack and merged when adding routes.
 * - Matching is done in two passes:
 *   1) Simple segment-based placeholders: /blogs/{slug}, /posts/{id:\d+}
 *   2) Embedded placeholders within segments: /blog/{year:\d{4}}-{slug}
 *
 * Responsibilities:
 * - String pattern handling and parameter extraction (no controller instantiation here).
 * - HTTP method filtering at the routing layer.
 *
 * Usage:
 * - Configure routes in routes.php using Router::group() and Router::add().
 * - Call $router->match($path, $method) in the front controller to get route params.
 */
final class Router
{
    /**
     * List of registered routes.
     *
     * Each route is stored as:
     * [
     *   'path'   => '/admin/users/{action}',
     *   'params' => [
     *     'controller' => 'Dashboard\\Users',
     *     'action'     => 'index',
     *     'namespace'  => 'Dashboard',
     *     'middleware' => ['auth', 'role:admin'],
     *     'method'     => 'GET',
     *     // any other custom keys...
     *   ],
     * ]
     *
     * @var array<int, array{path:string,params:array<string,mixed>}>
     */
    private array $routes = [];

    /**
     * Stack of active group attributes for nested route groups.
     *
     * @var array<int, array<string,mixed>>
     */
    private array $groupStack = [];

    /**
     * Define a group of routes with shared attributes (prefix, namespace, middleware, method(s)).
     *
     * Example:
     * $router->group(
     *     ['prefix' => '/admin', 'namespace' => 'Dashboard', 'middleware' => 'auth'],
     *     function (Router $r) {
     *         $r->add('/users', ['controller' => 'Users', 'action' => 'index']);
     *     }
     * );
     *
     * @param  array<string,mixed>  $attributes
     * @param  callable  $callback  Receives the Router instance.
     */
    public function group(array $attributes, callable $callback): void
    {
        $parent = end($this->groupStack) ?: [];
        $merged = $this->mergeGroupAttributes($parent, $attributes);

        $this->groupStack[] = $merged;

        // Execute group definition callback.
        $callback($this);

        // Pop group attributes after callback completes.
        array_pop($this->groupStack);
    }

    /**
     * Register a route.
     *
     * @param  string  $path  Route path pattern, e.g. '/blogs/{slug}'.
     * @param  array<string,mixed>  $params  Arbitrary route parameters
     *                                       (controller, action, middleware, method, etc.).
     */
    public function add(string $path, array $params = []): void
    {
        $current = end($this->groupStack) ?: [];

        // 1) Prefix
        // Combine group's prefix with the route's path into a normalized absolute path.
        $prefix = $current['prefix'] ?? '';
        $fullPath = rtrim($prefix, '/').'/'.ltrim($path, '/');
        $fullPath = '/'.trim($fullPath, '/');

        // 2) Namespace (keep short, e.g. "Admin", not full FQCN).
        if (isset($current['namespace'])) {
            if (!empty($params['namespace'])) {
                $params['namespace'] =
                    trim((string) $current['namespace'], '\\').'\\'.trim((string) $params['namespace'], '\\');
            } else {
                $params['namespace'] = $current['namespace'];
            }
        }

        // 3) Middleware (merge group + route, then unique).
        $routeMw = $this->normalizeMiddleware($params['middleware'] ?? []);
        $groupMw = $this->normalizeMiddleware($current['middleware'] ?? []);
        $params['middleware'] = array_values(array_unique(array_merge($groupMw, $routeMw)));

        // 4) Methods (allow single or multiple from group).
        if (isset($current['method']) && !isset($params['method'])) {
            $params['method'] = $current['method'];
        }

        if (isset($current['methods']) && !isset($params['methods'])) {
            $params['methods'] = $current['methods'];
        }

        // Finally, register the route.
        $this->routes[] = [
            'path' => $fullPath,
            'params' => $params,
        ];
    }

    /**
     * Match a given path + HTTP method to a route.
     *
     * @param  string  $path  Request path, e.g. '/admin/users/edit'.
     * @param  string  $method  HTTP method, e.g. 'GET', 'POST'.
     * @return array<string,mixed>|false Route params on success, false if no match.
     */
    public function match(string $path, string $method): array|false
    {
        $requestPath = trim(urldecode($path), '/');

        foreach ($this->routes as $route) {
            $pathToMatch = $requestPath;

            // Index fallback only for dynamic controller/action routes,
            // and do not mutate the global path.
            //
            // Example:
            //   Route:  '/admin/users/{action}'
            //   URL:    '/admin/users'
            //   Fallback: treat as '/admin/users/index' to hit 'index' action.
            if (!empty($route['params']['namespace'])) {
                $segments = $requestPath === '' ? [] : explode('/', $requestPath);

                if (isset($segments[0]) && $segments[0] === strtolower((string) $route['params']['namespace'])) {
                    $expectsAction = str_contains($route['path'], '{action}');

                    if (count($segments) === 2 && $expectsAction) {
                        $pathToMatch = $requestPath.'/index';
                    }
                }
            }

            // 1) Simple pattern: segment-by-segment {param} / {param:regex}
            $pattern = $this->getPatternFromRoutePath($route['path']);

            if (preg_match($pattern, $pathToMatch, $matches)) {
                $namedMatches = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $params = array_merge($namedMatches, $route['params']);

                if (!$this->httpMethodMatches($params, $method)) {
                    continue;
                }

                return $params;
            }

            // 2) Embedded-params pattern (for slugs with params inside same segment).
            $embeddedPattern = $this->getPatternWithEmbeddedParams($route['path']);

            if (preg_match($embeddedPattern, $pathToMatch, $matches)) {
                $namedMatches = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $params = array_merge($namedMatches, $route['params']);

                if (!$this->httpMethodMatches($params, $method)) {
                    continue;
                }

                return $params;
            }
        }

        return false;
    }

    /**
     * Merge parent and child group attributes for nested route groups.
     *
     * @param  array<string,mixed>  $parent
     * @param  array<string,mixed>  $child
     * @return array<string,mixed>
     */
    private function mergeGroupAttributes(array $parent, array $child): array
    {
        $merged = $parent;

        // Prefix
        if (isset($child['prefix'])) {
            $p = rtrim((string) ($parent['prefix'] ?? ''), '/');
            $c = '/'.trim((string) $child['prefix'], '/');
            $merged['prefix'] = $p.$c;
        }

        // Namespace
        if (isset($child['namespace'])) {
            $pn = trim((string) ($parent['namespace'] ?? ''), '\\');
            $cn = trim((string) $child['namespace'], '\\');
            $merged['namespace'] = $pn !== '' ? $pn.'\\'.$cn : $cn;
        }

        // Middleware (parent first, then child).
        $merged['middleware'] = $this->normalizeMiddleware($parent['middleware'] ?? []);
        $childMw = $this->normalizeMiddleware($child['middleware'] ?? []);
        $merged['middleware'] = array_values(
            array_unique(array_merge($merged['middleware'], $childMw))
        );

        // Methods
        if (isset($child['method'])) {
            $merged['method'] = $child['method'];
        }

        if (isset($child['methods'])) {
            $merged['methods'] = $child['methods'];
        }

        return $merged;
    }

    /**
     * Normalize middleware definitions into a flat array of strings.
     *
     * Accepts:
     * - 'auth'              → ['auth']
     * - 'auth|role:admin'   → ['auth', 'role:admin']
     * - ['auth', 'throttle']→ ['auth', 'throttle']
     *
     * @param  string|array<int,string>  $mw
     * @return string[]
     */
    private function normalizeMiddleware(string|array $mw): array
    {
        if (is_string($mw)) {
            // Support "auth|role:admin" pipe-separated lists.
            if (str_contains($mw, '|')) {
                return array_values(
                    array_filter(
                        array_map('trim', explode('|', $mw))
                    )
                );
            }

            $mw = trim($mw);

            return $mw === '' ? [] : [$mw];
        }

        if (is_array($mw)) {
            return array_values(
                array_filter(
                    array_map('trim', $mw)
                )
            );
        }

        return [];
    }

    /**
     * Check whether a route's configured HTTP method(s) allow the current method.
     *
     * Supported keys:
     * - 'method'  => 'GET'
     * - 'methods' => ['GET', 'POST']
     *
     * If neither is configured, all methods are allowed.
     *
     * @param  array<string,mixed>  $params
     */
    private function httpMethodMatches(array $params, string $method): bool
    {
        $methodUpper = strtoupper($method);

        if (isset($params['method'])) {
            if ($methodUpper !== strtoupper((string) $params['method'])) {
                return false;
            }
        }

        if (isset($params['methods'])) {
            $allowed = array_map('strtoupper', (array) $params['methods']);
            if (!in_array($methodUpper, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build a regex pattern from a route path, segment by segment.
     *
     * Supports:
     * - /blogs/{slug}
     * - /posts/{id:\d+}
     *
     * Where:
     * - {param}          → named group matching any non-slash segment.
     * - {param:regex}    → named group using the provided regex.
     */
    private function getPatternFromRoutePath(string $route_path): string
    {
        $route_path = trim($route_path, '/');
        $segments = $route_path === '' ? [] : explode('/', $route_path);

        $segments = array_map(
            static function (string $segment): string {
                // {param}
                if (preg_match('#^\\{([a-z][a-z0-9]*)\\}#i', $segment, $m)) {
                    return '(?<'.$m[1].'>[^/]*)';
                }

                // {param:regex}
                if (preg_match('#^\\{([a-z][a-z0-9]*):(.+)\\}#i', $segment, $matches)) {
                    return '(?<'.$matches[1].'>'.$matches[2].')';
                }

                return $segment;
            },
            $segments
        );

        return '#^'.implode('/', $segments).'$#iu';
    }

    /**
     * Build a regex where {param} and {param:regex} are embedded inside segments.
     *
     * For cases like:
     * - /blog/{year:\d{4}}-{slug}
     *
     * Notes:
     * - Hyphens are not allowed in PHP group names, so they are replaced with underscores.
     */
    private function getPatternWithEmbeddedParams(string $route_path): string
    {
        $route_path = trim($route_path, '/');

        // Replace {param:regex}.
        $route_path = preg_replace_callback(
            '#\\{([a-zA-Z_][a-zA-Z0-9_-]*):([^}]+)\\}#',
            static function (array $matches): string {
                // Hyphens not allowed in group names → replace with underscore.
                $safeName = str_replace('-', '_', $matches[1]);

                return '(?<'.$safeName.'>'.$matches[2].')';
            },
            $route_path
        );

        // Replace {param}.
        $route_path = preg_replace_callback(
            '#\\{([a-zA-Z_][a-zA-Z0-9_-]*)\\}#',
            static function (array $matches): string {
                $safeName = str_replace('-', '_', $matches[1]);

                return '(?<'.$safeName.'>[^/]+)';
            },
            $route_path
        );

        return '#^'.$route_path.'$#iu';
    }
}
