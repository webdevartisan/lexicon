<?php

declare(strict_types=1);

/**
 * Blog Overview, Shared, the per-blog Posts list and the writer workspace were
 * folded into the Overview, Blogs and Posts pages. Their URLs must not reach
 * any controller action, with or without the debug-only wildcard routes.
 */
beforeEach(function () {
    $this->router = require ROOT_PATH.'/config/routes.php';

    $this->reachesAnAction = function (string $path, string $method = 'GET'): bool {
        $match = $this->router->match($path, $method);
        if ($match === false) {
            return false;
        }

        $name = (string) $match['controller'];
        if (!str_ends_with($name, 'Controller')) {
            $name = ucfirst(rtrim($name, 's')).'Controller';
        }
        $namespace = isset($match['namespace']) ? $match['namespace'].'\\' : '';
        $class = 'App\\Controllers\\'.$namespace.$name;

        return class_exists($class) && method_exists($class, (string) ($match['action'] ?? 'index'));
    };
});

it('no longer serves a removed dashboard page', function (string $path) {
    expect(($this->reachesAnAction)($path))->toBeFalse();
})->with([
    '/dashboard/shared',
    '/dashboard/blog/12/show',
    '/dashboard/blogs/12/show',
    '/dashboard/blog/12/posts',
    '/dashboard/blog/12/workspace',
]);

it('still serves the pages that replaced them', function (string $path) {
    expect(($this->reachesAnAction)($path))->toBeTrue();
})->with([
    '/dashboard',
    '/dashboard/blog',
    '/dashboard/post',
    '/dashboard/blog/12/review-queue',
]);

it('switches the active blog only through the renamed action', function () {
    expect($this->router->match('/dashboard/active-blog', 'POST'))->toMatchArray(['action' => 'setActiveBlog'])
        ->and(($this->reachesAnAction)('/dashboard/setDefaultBlog', 'POST'))->toBeFalse();
});
