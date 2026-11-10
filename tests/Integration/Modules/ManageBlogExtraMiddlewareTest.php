<?php

declare(strict_types=1);

use App\Auth;
use App\Middleware\ManageBlogExtraMiddleware;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use App\Models\UserModel;
use Framework\Core\Request;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\MiddlewareTestHelper;

/**
 * A blog module's settings pages open only for people who may edit that blog,
 * and only while the module is switched on for it.
 */
beforeEach(function () {
    (new ModuleModel($this->db))->record('sample-extra', 1);
    $users = new UserModel($this->db);
    $this->ownerId = UserFactory::new($users)->create();
    $this->strangerId = UserFactory::new($users)->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->create($this->ownerId);
    $this->blogModules = new BlogModuleModel($this->db);

    $this->pass = function (int $userId, string $path): bool {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('user')->andReturn(['id' => $userId, 'roles' => [], 'permissions' => []]);
        $middleware = new ManageBlogExtraMiddleware($auth, new BlogModel($this->db), $this->blogModules);
        $reached = false;

        $middleware->process(
            new Request($path, 'GET', [], [], [], [], [], []),
            MiddlewareTestHelper::createTrackingHandler($reached)
        );

        return $reached;
    };
    $this->settings = fn (?int $blogId = null): string => '/en/dashboard/blog/'.($blogId ?? $this->blogId)
        .'/m/sample-extra/notes';
});

test('the blog owner reaches the settings page of a module switched on for the blog', function () {
    $this->blogModules->enable($this->blogId, 'sample-extra');

    expect(($this->pass)($this->ownerId, ($this->settings)()))->toBeTrue();
});

test('a signed in user who cannot edit the blog is refused', function () {
    $this->blogModules->enable($this->blogId, 'sample-extra');

    expect(fn () => ($this->pass)($this->strangerId, ($this->settings)()))->toThrow(UnauthorizedException::class);
});

test('the owner gets a 404 while the module is off for the blog', function () {
    expect(fn () => ($this->pass)($this->ownerId, ($this->settings)()))->toThrow(PageNotFoundException::class);
});

test('a blog that does not exist is a 404', function () {
    expect(fn () => ($this->pass)($this->ownerId, ($this->settings)(999999)))->toThrow(PageNotFoundException::class);
});

test('the path without a language prefix is read the same way', function () {
    $this->blogModules->enable($this->blogId, 'sample-extra');

    expect(($this->pass)($this->ownerId, '/dashboard/blog/'.$this->blogId.'/m/sample-extra'))->toBeTrue();
});
