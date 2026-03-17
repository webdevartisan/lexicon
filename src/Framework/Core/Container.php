<?php

declare(strict_types=1);

namespace Framework\Core;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * PSR-11 inspired dependency injection container with autowiring.
 *
 * Purpose:
 * - Register factories for services and resolve them on demand
 * - Autowire classes by reflecting their constructors when no explicit
 *   factory is registered
 * - Provide debugging capabilities to track resolution performance
 *
 * Design:
 * - Entries are stored as callables that return an object instance
 * - set() registers a factory that is called for each get()
 * - setShared() registers a lazy singleton: factory is called once per name
 * - get() can also autowire unregistered classes via constructor type hints
 * - All factory closures receive the container as their first parameter
 *
 * Security:
 * - Debug mode should be disabled in production to prevent information leakage
 * - Consider implementing namespace whitelisting for autowiring in production
 *
 * @example
 * // Register a factory that receives container
 * $container->set(LoggerInterface::class, function ($c) {
 *     return new FileLogger($c->get(ConfigInterface::class));
 * });
 *
 * // Register a singleton
 * $container->setShared(Database::class, function ($c) {
 *     return new Database($c->get(ConfigInterface::class));
 * });
 */
final class Container
{
    /**
     * @var array<string, true> Stack of classes currently being resolved
     */
    private array $resolving = [];

    /**
     * @var array<string, Closure> Registered service factories
     */
    private array $registry = [];

    /**
     * @var array<string, ReflectionClass<object>> Cache of reflection instances
     */
    private array $reflectionCache = [];

    /**
     * @var array<string, bool> Cache of singleton detection results
     */
    private array $singletonCache = [];

    // Debug tracking
    private array $instantiationLog = [];

    private bool $debugMode = false;

    private ?float $startTime = null;

    /**
     * Enable debug mode to track all resolutions.
     *
     * We capture timing and memory metrics for each resolution to identify
     * performance bottlenecks. This should be disabled in production.
     */
    public function enableDebug(): void
    {
        $this->debugMode = true;
        $this->startTime = $this->startTime ?? microtime(true);
    }

    /**
     * Disable debug mode to stop tracking resolutions.
     */
    public function disableDebug(): void
    {
        $this->debugMode = false;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool True if debug mode is active
     */
    public function isDebugEnabled(): bool
    {
        return $this->debugMode;
    }

    /**
     * Register a factory for the given service name.
     *
     * We store the factory as a closure that will be invoked on every get() call,
     * allowing fresh instances to be created each time. The factory receives the
     * container as its first parameter for resolving dependencies.
     *
     * @param  string  $name  Service identifier (typically a class name or interface)
     * @param  Closure(self): object  $factory  Closure that receives container and returns service instance
     *
     * @example
     * $container->set(LoggerInterface::class, function ($c) {
     *     return new FileLogger($c->get(ConfigInterface::class));
     * });
     */
    public function set(string $name, Closure $factory): void
    {
        $this->registry[$name] = $factory;
        // Clear singleton cache since registration changed
        unset($this->singletonCache[$name]);
    }

    /**
     * Register a lazy, shared service (singleton per container) for the given name.
     *
     * We wrap the provided factory in a closure that caches the first result,
     * ensuring the factory is only executed once. The factory receives the
     * container as its first parameter.
     *
     * @param  string  $name  Service identifier (typically a class name or interface)
     * @param  Closure(self): object  $factory  Closure that receives container and returns service instance
     *
     * @example
     * $container->setShared(Database::class, function ($c) {
     *     $config = $c->get(ConfigInterface::class);
     *     return new Database($config->get('database'));
     * });
     */
    public function setShared(string $name, Closure $factory): void
    {
        $this->registry[$name] = function (self $c) use ($factory, $name) {
            static $instances = [];

            if (!isset($instances[$name])) {
                $instances[$name] = $factory($c);
            }

            return $instances[$name];
        };

        // Mark as singleton in cache
        $this->singletonCache[$name] = true;
    }

    /**
     * Determine whether an explicit factory is registered for the given name.
     *
     * We check if a factory exists in the registry. This does not guarantee that
     * get() will succeed, as the factory might throw exceptions.
     *
     * @param  string  $name  Service identifier to check
     * @return bool True if a factory is registered
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->registry);
    }

    /**
     * Resolve a service or class by name.
     *
     * Resolution order:
     * - If a factory is registered via set()/setShared(), call it and return its result
     * - Otherwise, attempt to autowire the class by reflecting its constructor
     *   and recursively resolving its non-builtin typed parameters
     *
     * We cache reflection results and track circular dependencies to prevent
     * infinite recursion.
     *
     * @param  string  $className  Service identifier or class name to resolve
     * @return object The resolved service instance
     *
     * @throws InvalidArgumentException If circular dependency detected or autowiring fails
     */
    public function get(string $className): object
    {
        $startTime = $this->debugMode ? microtime(true) : 0.0;
        $startMemory = $this->debugMode ? memory_get_usage(true) : 0;

        // Determine resolution method before actual resolution for debug logging
        $method = $this->has($className) ? 'registered' : 'autowired';
        $type = 'factory';

        if ($this->has($className)) {
            $type = $this->getRegistrationType($className);
        }

        // Use registered factory if available
        if ($this->has($className)) {
            $factory = $this->registry[$className];
            // Pass container as parameter (standard DI container pattern)
            $instance = $factory($this);

            // Validate factory returned an object
            if (!is_object($instance)) {
                throw new InvalidArgumentException(
                    "Factory for '{$className}' must return an object, "
                    .gettype($instance).' returned.'
                );
            }

            // Track after resolution
            if ($this->debugMode) {
                $this->logInstantiation($className, $method, $type, $startTime, $startMemory);
            }

            return $instance;
        }

        // Check for circular dependency before attempting autowire
        if (isset($this->resolving[$className])) {
            $chain = implode(' -> ', array_keys($this->resolving))." -> {$className}";
            throw new InvalidArgumentException(
                "Circular dependency detected: {$chain}"
            );
        }

        $this->resolving[$className] = true;

        try {
            // Autowire the class via reflection
            $reflector = $this->getReflectionClass($className);

            // Check if class is instantiable
            if (!$reflector->isInstantiable()) {
                throw new InvalidArgumentException(
                    "Class '{$className}' is not instantiable (abstract class or interface)."
                );
            }

            $constructor = $reflector->getConstructor();

            // No constructor means no dependencies
            if ($constructor === null) {
                /** @var object $instance */
                $instance = new $className();

                if ($this->debugMode) {
                    $this->logInstantiation($className, $method, $type, $startTime, $startMemory);
                }

                return $instance;
            }

            $dependencies = $this->resolveDependencies(
                $constructor->getParameters(),
                $className
            );

            /** @var object $instance */
            $instance = new $className(...$dependencies);

            if ($this->debugMode) {
                $this->logInstantiation($className, $method, 'factory', $startTime, $startMemory);
            }

            return $instance;
        } finally {
            // Always clean up resolving stack, even if exception thrown
            unset($this->resolving[$className]);
        }
    }

    /**
     * Get or create a cached ReflectionClass instance.
     *
     * We cache reflection instances to avoid the overhead of repeated reflection
     * on the same class during the request lifecycle.
     *
     * @param  string  $className  Class name to reflect
     * @return ReflectionClass<object> Cached or new reflection instance
     *
     * @throws InvalidArgumentException If class does not exist or reflection fails
     */
    private function getReflectionClass(string $className): ReflectionClass
    {
        if (!isset($this->reflectionCache[$className])) {
            if (!class_exists($className)) {
                throw new InvalidArgumentException(
                    "Class '{$className}' does not exist and cannot be autowired."
                );
            }

            try {
                $this->reflectionCache[$className] = new ReflectionClass($className);
            } catch (\ReflectionException $e) {
                throw new InvalidArgumentException(
                    "Failed to reflect class '{$className}': {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $this->reflectionCache[$className];
    }

    /**
     * Resolve all constructor dependencies for autowiring.
     *
     * We iterate through constructor parameters and recursively resolve typed
     * dependencies using the container. Built-in types must have default values.
     *
     * @param  array<ReflectionParameter>  $parameters  Constructor parameters to resolve
     * @param  string  $className  Class name being autowired (for error messages)
     * @return array<mixed> Resolved dependency instances
     *
     * @throws InvalidArgumentException If a parameter cannot be resolved
     */
    private function resolveDependencies(array $parameters, string $className): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Parameters without type hints cannot be autowired
            if ($type === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new InvalidArgumentException(
                    "Cannot autowire parameter '\${$parameter->getName()}' in '{$className}': "
                    .'no type declaration and no default value.'
                );
            }

            // Only single named types are supported
            if (!($type instanceof ReflectionNamedType)) {
                throw new InvalidArgumentException(
                    "Cannot autowire parameter '\${$parameter->getName()}' in '{$className}': "
                    .'union types and intersection types are not supported.'
                );
            }

            // Built-in types (string, int, etc.) must have defaults
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                    continue;
                }

                throw new InvalidArgumentException(
                    "Cannot autowire parameter '\${$parameter->getName()}' of built-in type "
                    ."'{$type->getName()}' in '{$className}': no default value provided."
                );
            }

            // Recursively resolve class/interface dependencies
            $dependencyClass = $type->getName();
            $dependencies[] = $this->get($dependencyClass);
        }

        return $dependencies;
    }

    /**
     * Log an instantiation event for debug tracking.
     *
     * We record timing and memory metrics to help identify performance bottlenecks
     * during development and testing.
     *
     * @param  string  $className  Class name that was resolved
     * @param  string  $method  Resolution method ('registered' or 'autowired')
     * @param  string  $type  Service type ('factory' or 'singleton')
     * @param  float  $startTime  Timestamp when resolution started
     * @param  int  $startMemory  Memory usage when resolution started
     */
    private function logInstantiation(
        string $className,
        string $method,
        string $type,
        float $startTime,
        int $startMemory
    ): void {
        $this->instantiationLog[] = [
            'class' => $className,
            'method' => $method,
            'type' => $type,
            'timestamp' => microtime(true),
            'duration' => (microtime(true) - $startTime) * 1000, // ms
            'memory' => memory_get_usage(true) - $startMemory,
        ];
    }

    /**
     * Determine if a registered factory is a singleton.
     *
     * We check the cached result first, then inspect the factory's static variables
     * to detect if it uses the singleton pattern (has an $instances static variable).
     *
     * @param  string  $name  Service identifier to check
     * @return string 'singleton' if shared, 'factory' if not
     */
    private function getRegistrationType(string $name): string
    {
        if (!$this->has($name)) {
            return 'factory';
        }

        // Return cached result if available
        if (isset($this->singletonCache[$name])) {
            return $this->singletonCache[$name] ? 'singleton' : 'factory';
        }

        $factory = $this->registry[$name];
        $reflection = new \ReflectionFunction($factory);
        $staticVars = $reflection->getStaticVariables();

        // Check if the factory uses static $instances array (setShared pattern)
        $isSingleton = array_key_exists('instances', $staticVars);
        $this->singletonCache[$name] = $isSingleton;

        return $isSingleton ? 'singleton' : 'factory';
    }

    /**
     * Get comprehensive debug report with resolution statistics.
     *
     * We aggregate all instantiation logs and provide metrics grouped by class,
     * including resolution count, method, type, and performance data.
     *
     * @return array{
     *     total_registered: int,
     *     total_resolutions: int,
     *     total_duration: float,
     *     total_memory: int,
     *     request_duration: float,
     *     resolutions: array<string, array{
     *         count: int,
     *         method: string,
     *         type: string,
     *         total_duration: float,
     *         total_memory: int,
     *         instances: array
     *     }>
     * } Debug report with statistics
     */
    public function getDebugReport(): array
    {
        $grouped = [];
        $totalDuration = 0.0;
        $totalMemory = 0;

        foreach ($this->instantiationLog as $entry) {
            $class = $entry['class'];
            $totalDuration += $entry['duration'];
            $totalMemory += $entry['memory'];

            if (!isset($grouped[$class])) {
                $grouped[$class] = [
                    'count' => 0,
                    'method' => $entry['method'],
                    'type' => $entry['type'],
                    'total_duration' => 0.0,
                    'total_memory' => 0,
                    'instances' => [],
                ];
            }

            $grouped[$class]['count']++;
            $grouped[$class]['total_duration'] += $entry['duration'];
            $grouped[$class]['total_memory'] += $entry['memory'];
            $grouped[$class]['instances'][] = $entry;
        }

        // Sort by count descending to show most frequently resolved services first
        uasort($grouped, fn ($a, $b) => $b['count'] <=> $a['count']);

        return [
            'total_registered' => count($this->registry),
            'total_resolutions' => count($this->instantiationLog),
            'total_duration' => $totalDuration,
            'total_memory' => $totalMemory,
            'request_duration' => ($this->startTime ? (microtime(true) - $this->startTime) * 1000 : 0.0),
            'resolutions' => $grouped,
        ];
    }

    /**
     * Clear all reflection and singleton caches.
     *
     * We provide this method for testing or when you need to reset the container
     * state without creating a new instance.
     */
    public function clearCaches(): void
    {
        $this->reflectionCache = [];
        $this->singletonCache = [];
    }
}
