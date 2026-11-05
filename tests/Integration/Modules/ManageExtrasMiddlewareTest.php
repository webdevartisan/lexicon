<?php

declare(strict_types=1);

use App\Auth;
use App\Middleware\ManageExtrasMiddleware;
use Framework\Core\Request;
use Framework\Exceptions\UnauthorizedException;
use Tests\Helpers\MiddlewareTestHelper;

function manageExtrasMiddlewareFor(array $user): ManageExtrasMiddleware
{
    $auth = Mockery::mock(Auth::class);
    $auth->shouldReceive('user')->andReturn($user);

    return new ManageExtrasMiddleware($auth);
}

function controlPanelModuleRequest(): Request
{
    return new Request('/admin/m/sample-platform/settings', 'GET', [], [], [], [], [], []);
}

test('an administrator passes the manage extras check', function () {
    $reached = false;
    $middleware = manageExtrasMiddlewareFor(['id' => 1, 'roles' => ['administrator'], 'permissions' => []]);

    $middleware->process(controlPanelModuleRequest(), MiddlewareTestHelper::createTrackingHandler($reached));

    expect($reached)->toBeTrue();
});

test('a user holding manage_extras passes without being an administrator', function () {
    $reached = false;
    $middleware = manageExtrasMiddlewareFor(['id' => 2, 'roles' => ['user'], 'permissions' => ['manage_extras']]);

    $middleware->process(controlPanelModuleRequest(), MiddlewareTestHelper::createTrackingHandler($reached));

    expect($reached)->toBeTrue();
});

test('a signed in user without manage_extras is refused before the module page runs', function () {
    $reached = false;
    $middleware = manageExtrasMiddlewareFor(['id' => 3, 'roles' => ['user'], 'permissions' => ['create_posts']]);

    $handler = MiddlewareTestHelper::createTrackingHandler($reached);

    expect(fn () => $middleware->process(controlPanelModuleRequest(), $handler))
        ->toThrow(UnauthorizedException::class)
        ->and($reached)->toBeFalse();
});
