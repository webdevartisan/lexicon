<?php

declare(strict_types=1);

use Framework\Core\Container;
use Framework\Core\Dispatcher;
use Framework\Core\Router;
use Framework\View\RouteContext;

/**
 * Controller names from the route config are class names and must keep their
 * case. Lowercasing them only worked on case-insensitive filesystems: on Linux
 * the autoloader looked for Authcontroller.php and every multi-word controller,
 * the login page included, failed to load.
 */
beforeEach(function () {
    $dispatcher = new Dispatcher(new Router(), new Container(), new RouteContext());
    $method = new ReflectionMethod(Dispatcher::class, 'getControllerName');

    $this->resolve = fn (array $params): string => $method->invoke($dispatcher, $params);
});

test('a controller named in the route config keeps its case', function (string $name, string $class) {
    expect(($this->resolve)(['controller' => $name]))->toBe('App\\Controllers\\'.$class);
})->with([
    ['AuthController', 'AuthController'],
    ['EmailBindingController', 'EmailBindingController'],
    ['EmailTest', 'EmailTestController'],
]);

test('the route namespace is kept as well', function () {
    expect(($this->resolve)(['controller' => 'EmailLayoutController', 'namespace' => 'Admin']))
        ->toBe('App\\Controllers\\Admin\\EmailLayoutController');
});

test('a kebab-case name from the URL becomes a singular PascalCase controller', function (string $name, string $class) {
    expect(($this->resolve)(['controller' => $name]))->toBe('App\\Controllers\\'.$class);
})->with([
    ['user-profile', 'UserProfileController'],
    ['blogs', 'BlogController'],
    ['USER-PROFILE', 'UserProfileController'],
]);
