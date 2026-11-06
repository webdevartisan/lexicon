<?php

declare(strict_types=1);

use Framework\Core\Container;
use Framework\Core\Router;
use Framework\Database;
use Framework\Modules\ModuleBooter;
use Framework\Modules\ModuleContext;
use Framework\Modules\ModuleInstances;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRoutes;
use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;
use Tests\Fixtures\Modules\SampleExtra\SampleExtraModule;
use Tests\Helpers\ModuleFixtures;

/**
 * @param array<string, string> $states
 */
function booterWith(array $states): ModuleBooter
{
    $container = new Container();
    $container->set(Database::class, fn () => Mockery::mock(Database::class));

    return new ModuleBooter(ModuleFixtures::registry($states), $container, new ModuleInstances($container));
}

function quietSlot(): SlotRenderer
{
    return new class () implements SlotRenderer {
        public function shouldRender(SlotContext $context): bool
        {
            return true;
        }

        public function render(SlotContext $context): string
        {
            return '';
        }

        public function assets(): array
        {
            return ['css' => [], 'js' => []];
        }
    };
}

beforeEach(function () {
    ModuleFixtures::loadSampleExtra();
    SampleExtraModule::$registered = false;
});

afterEach(fn () => ModuleFixtures::cleanUp());

test('a module folder that is not installed is never registered or booted', function () {
    $booter = booterWith(states: []);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    expect(SampleExtraModule::$registered)->toBeFalse()
        ->and($router->match('/blog/any/m/sample-extra/note', 'GET'))->toBeFalse();
});

test('a paused module is not registered either', function () {
    $booter = booterWith(states: ['sample-extra' => 'paused']);
    $booter->registerAll();

    expect(SampleExtraModule::$registered)->toBeFalse();
});

test('an installed module adds its front route under the blog prefix', function () {
    $booter = booterWith(states: ['sample-extra' => 'on']);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    expect(SampleExtraModule::$registered)->toBeTrue()
        ->and($router->match('/blog/trip/m/sample-extra/note', 'GET'))->toMatchArray([
            'controller_class' => 'Tests\Fixtures\Modules\SampleExtra\NoteController',
            'module' => 'sample-extra',
            'blogSlug' => 'trip',
        ]);
});

test('an installed module adds its dashboard route under the blog id prefix with auth', function () {
    $booter = booterWith(states: ['sample-extra' => 'on']);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    $route = $router->match('/dashboard/blog/7/m/sample-extra/notes', 'GET');

    expect($route)->toMatchArray(['module' => 'sample-extra', 'blogId' => '7'])
        ->and($route['middleware'])->toContain('auth');
});

test('a module route cannot name a core controller', function (array $params) {
    $routes = new ModuleRoutes(new Router(), 'sample-extra');

    expect(fn () => $routes->add('/x', $params))->toThrow(LogicException::class);
})->with([
    'core controller name' => [['controller' => 'AdminController', 'action' => 'index']],
    'namespace hop' => [['controller_class' => 'Modules\\X\\Y', 'namespace' => 'Admin']],
    'no controller at all' => [['action' => 'index']],
]);

test('module commands must carry the module key as their prefix', function () {
    $manifest = ModuleManifest::fromFile(ROOT_PATH.'/tests/Fixtures/modules/sample-extra/module.json');
    $context = new ModuleContext($manifest);

    expect(fn () => $context->commands(['cache:clear' => 'X']))->toThrow(LogicException::class);
});

test('booted module commands are listed for the console', function () {
    $booter = booterWith(states: ['sample-extra' => 'on']);
    $booter->registerAll();

    expect($booter->commands())
        ->toBe(['sample-extra:tidy' => 'Tests\Fixtures\Modules\SampleExtra\TidyCommand']);
});

test('a slot not declared in the manifest is refused', function () {
    $manifest = ModuleManifest::fromFile(ROOT_PATH.'/tests/Fixtures/modules/sample-extra/module.json');
    $context = new ModuleContext($manifest);

    expect(fn () => $context->slot('blog.nav', quietSlot()))->toThrow(LogicException::class);
});

test('a declared slot is collected by the booter', function () {
    $booter = booterWith(states: ['sample-extra' => 'on']);
    $booter->registerAll();

    $slots = $booter->slots();

    expect(array_keys($slots))->toBe(['post.after-content'])
        ->and($slots['post.after-content'][0]['module'])->toBe('sample-extra');
});

test('a module switched off site wide is not registered', function () {
    $booter = booterWith(states: ['sample-extra' => 'off']);
    $booter->registerAll();

    expect(SampleExtraModule::$registered)->toBeFalse();
});

test('a platform module mounts its front route at /m/<key> without a blog', function () {
    $booter = booterWith(states: ['sample-platform' => 'on']);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    expect($router->match('/m/sample-platform/hello', 'GET'))->toMatchArray(['module' => 'sample-platform'])
        ->and($router->match('/blog/trip/m/sample-platform/hello', 'GET'))->toBeFalse();
});

test('a platform module mounts its manage route in the control panel behind manage-extras', function () {
    $booter = booterWith(states: ['sample-platform' => 'on']);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    $route = $router->match('/admin/m/sample-platform/settings', 'GET');

    expect($route)->toMatchArray(['module' => 'sample-platform'])
        ->and($route['middleware'])->toBe(['auth', 'two-factor', 'manage-extras']);
});

test('a blog module manage route checks the blog through manage-blog-extra', function () {
    $booter = booterWith(states: ['sample-extra' => 'on']);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    expect($router->match('/dashboard/blog/7/m/sample-extra/notes', 'GET')['middleware'])
        ->toBe(['auth', 'manage-blog-extra']);
});

test('a blog module admin route mounts in the control panel behind manage-extras', function () {
    $booter = booterWith(states: ['sample-extra' => 'on']);
    $booter->registerAll();
    $booter->bootRoutes($router = new Router());

    $route = $router->match('/admin/m/sample-extra', 'GET');

    expect($route)->toMatchArray(['module' => 'sample-extra'])
        ->and($route['middleware'])->toBe(['auth', 'two-factor', 'manage-extras']);
});

test('a platform module cannot add admin routes', function () {
    $context = new ModuleContext(ModuleManifest::fromFile(ModuleFixtures::PATH.'/sample-platform/module.json'));

    expect(fn () => $context->adminRoutes(fn (ModuleRoutes $routes) => null))->toThrow(LogicException::class);
});
