<?php

declare(strict_types=1);

use Framework\Core\Container;
use Framework\Core\Dispatcher;
use Framework\Core\Router;
use Framework\Exceptions\PageNotFoundException;
use Framework\View\RouteContext;

/**
 * Module routes name their controller class in full. Only classes in the module
 * namespaces may be named that way, so a route can never reach a core controller
 * through the module door and skip the core route's own middleware.
 */
beforeEach(function () {
    $dispatcher = new Dispatcher(new Router(), new Container(), new RouteContext());
    $method = new ReflectionMethod(Dispatcher::class, 'getControllerName');

    $this->resolve = fn (array $params): string => $method->invoke($dispatcher, $params);
});

test('a module controller class is used as given', function (string $class) {
    expect(($this->resolve)(['controller_class' => $class]))->toBe($class);
})->with([
    'Modules\\JourneyMap\\StopController',
    'Tests\\Fixtures\\Modules\\SampleExtra\\NoteController',
]);

test('a controller_class outside the module namespaces is refused', function (string $class) {
    expect(fn () => ($this->resolve)(['controller_class' => $class]))->toThrow(PageNotFoundException::class);
})->with([
    'App\\Controllers\\Admin\\ControlPanelController',
    'ModulesEvil\\Thing',
    '\\Modules\\JourneyMap\\StopController',
]);
