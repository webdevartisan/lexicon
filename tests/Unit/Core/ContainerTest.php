<?php

declare(strict_types=1);

use Framework\Core\Container;
use Tests\Fixtures\Container\CircularA;
use Tests\Fixtures\Container\CircularB;
use Tests\Fixtures\Container\ClassWithBuiltinParam;
use Tests\Fixtures\Container\ClassWithDefaultParam;
use Tests\Fixtures\Container\ClassWithDependency;
use Tests\Fixtures\Container\ClassWithUntypedParam;
use Tests\Fixtures\Container\SimpleClass;

/**
 * Container Resolution Tests
 *
 * Tests the dependency injection container's ability to resolve classes
 * with various dependency scenarios: simple classes, nested dependencies,
 * error cases (untyped/scalar params), circular dependencies, and singletons.
 */
test('container resolves simple class', function () {
    $container = new Container();

    $instance = $container->get(SimpleClass::class);

    expect($instance)->toBeInstanceOf(SimpleClass::class);
});

test('container resolves class with dependencies', function () {
    $container = new Container();

    $instance = $container->get(ClassWithDependency::class);

    expect($instance)->toBeInstanceOf(ClassWithDependency::class)
        ->and($instance->simple)->toBeInstanceOf(SimpleClass::class);
});

test('container throws exception for untyped parameters', function () {
    $container = new Container();

    // No type hint = container cannot determine what to inject
    expect(fn () => $container->get(ClassWithUntypedParam::class))
        ->toThrow(InvalidArgumentException::class);
});

test('container throws exception for built-in type parameters without defaults', function () {
    $container = new Container();

    // Scalar types (string, int, etc.) require explicit bindings or defaults
    expect(fn () => $container->get(ClassWithBuiltinParam::class))
        ->toThrow(InvalidArgumentException::class);
});

test('container resolves class with default parameters', function () {
    $container = new Container();

    $instance = $container->get(ClassWithDefaultParam::class);

    expect($instance->name)->toBe('default');
});

test('container detects circular dependencies', function () {
    $container = new Container();

    // CircularA → CircularB → CircularA creates infinite recursion
    expect(fn () => $container->get(CircularA::class))
        ->toThrow(InvalidArgumentException::class, 'Circular dependency detected');
});

test('container shared services return same instance', function () {
    $container = new Container();

    // setShared() creates singleton: factory runs once, instance cached
    $container->setShared(SimpleClass::class, fn () => new SimpleClass());

    $instance1 = $container->get(SimpleClass::class);
    $instance2 = $container->get(SimpleClass::class);

    expect($instance1)->toBe($instance2);
});

test('container non-shared services return different instances', function () {
    $container = new Container();

    // set() creates transient: factory runs on every get() call
    $container->set(SimpleClass::class, fn () => new SimpleClass());

    $instance1 = $container->get(SimpleClass::class);
    $instance2 = $container->get(SimpleClass::class);

    expect($instance1)->not->toBe($instance2);
});
