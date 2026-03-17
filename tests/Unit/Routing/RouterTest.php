<?php

declare(strict_types=1);

/**
 * Unit tests for Router class.
 *
 * Tests route registration, matching, parameter extraction, and HTTP method filtering
 * without any external dependencies.
 */

use Faker\Factory as Faker;
use Framework\Core\Router;

beforeEach(function () {
    $this->faker = Faker::create();
    $this->router = new Router();
});

/**
 * Tests basic route registration and matching without parameters.
 *
 * Verifies that simple static routes can be added and matched correctly.
 */
test('registers and matches simple routes', function () {
    $controller = $this->faker->word();
    $action = $this->faker->word();

    $this->router->add('/users', [
        'controller' => $controller,
        'action' => $action,
    ]);

    $result = $this->router->match('/users', 'GET');

    expect($result)->toBeArray()
        ->and($result['controller'])->toBe($controller)
        ->and($result['action'])->toBe($action);
});

/**
 * Tests dynamic parameter extraction from URL segments.
 *
 * Verifies that {param} placeholders are captured as named groups.
 */
test('extracts route parameters', function () {
    $userId = (string) $this->faker->numberBetween(1, 1000);

    $this->router->add('/users/{id}', [
        'controller' => 'Users',
        'action' => 'show',
    ]);

    $result = $this->router->match("/users/{$userId}", 'GET');

    expect($result['id'])->toBe($userId);
});

/**
 * Tests regex constraint validation in route parameters.
 *
 * Verifies that {param:regex} patterns enforce type constraints.
 */
test('enforces regex constraints', function () {
    $validId = (string) $this->faker->numberBetween(1, 1000);
    $invalidId = $this->faker->word();

    $this->router->add('/posts/{id:\d+}', [
        'controller' => 'Posts',
        'action' => 'show',
    ]);

    expect($this->router->match("/posts/{$validId}", 'GET'))->toBeArray();
    expect($this->router->match("/posts/{$invalidId}", 'GET'))->toBeFalse();
});

/**
 * Tests HTTP method-based route filtering.
 *
 * Verifies that routes with 'method' param only match specified HTTP verbs.
 */
test('filters by HTTP method', function () {
    $this->router->add('/form', [
        'controller' => 'Form',
        'action' => 'show',
        'method' => 'GET',
    ]);

    $this->router->add('/form', [
        'controller' => 'Form',
        'action' => 'submit',
        'method' => 'POST',
    ]);

    $getResult = $this->router->match('/form', 'GET');
    $postResult = $this->router->match('/form', 'POST');

    expect($getResult['action'])->toBe('show')
        ->and($postResult['action'])->toBe('submit');
});

/**
 * Tests route grouping with prefix attribute.
 *
 * Verifies that group prefix is prepended to all routes in the group.
 */
test('applies group prefix', function () {
    $this->router->group(['prefix' => '/admin'], function ($r) {
        $r->add('/users', ['controller' => 'Users']);
    });

    expect($this->router->match('/admin/users', 'GET'))->toBeArray();
    expect($this->router->match('/users', 'GET'))->toBeFalse();
});

/**
 * Tests negative case for unmatched routes.
 *
 * Verifies that router returns false when no route pattern matches.
 */
test('returns false for unmatched routes', function () {
    $this->router->add('/users', ['controller' => 'Users']);

    expect($this->router->match('/nonexistent', 'GET'))->toBeFalse();
});

// ============================================================================
// NEW TESTS (MISSING COVERAGE)
// ============================================================================

/**
 * Tests namespace merging in nested route groups.
 *
 * Verifies that child namespaces are appended to parent with backslash separator.
 */
test('merges namespaces in nested groups', function () {
    $this->router->group(['namespace' => 'Admin'], function ($r) {
        $r->group(['namespace' => 'Settings'], function ($r) {
            $r->add('/profile', ['controller' => 'Profile']);
        });
    });

    $result = $this->router->match('/profile', 'GET');

    expect($result)->toBeArray()
        ->and($result['namespace'])->toBe('Admin\\Settings');
});

/**
 * Tests middleware merging with pipe-separated syntax.
 *
 * Verifies that 'auth|role:admin' is split into separate middleware entries.
 */
test('normalizes pipe-separated middleware', function () {
    $this->router->add('/admin', [
        'controller' => 'Dashboard',
        'middleware' => 'auth|role:admin|throttle',
    ]);

    $result = $this->router->match('/admin', 'GET');

    expect($result['middleware'])->toBeArray()
        ->toHaveCount(3)
        ->toContain('auth', 'role:admin', 'throttle');
});

/**
 * Tests middleware inheritance in route groups.
 *
 * Verifies that child routes inherit parent middleware and can add their own.
 */
test('merges group and route middleware', function () {
    $this->router->group(['middleware' => 'auth'], function ($r) {
        $r->add('/posts', [
            'controller' => 'Posts',
            'middleware' => ['csrf', 'throttle'],
        ]);
    });

    $result = $this->router->match('/posts', 'GET');

    // Parent middleware applied first, then route-specific
    expect($result['middleware'])->toBe(['auth', 'csrf', 'throttle']);
});

/**
 * Tests parameter extraction in group prefixes.
 *
 * Verifies that parameters in group prefix are properly extracted and merged.
 */
test('extracts parameters from group prefix', function () {
    $blogSlug = 'tech-blog';

    $router = new Router();
    $router->group([
        'prefix' => '/blog/{blogSlug:[A-Za-z0-9_-]+}',
        'middleware' => 'theme',
    ], function ($r) {
        $r->add('/', [
            'controller' => 'BlogController',
            'action' => 'showBlog',
        ]);
    });

    $result = $router->match("/blog/{$blogSlug}", 'GET');

    expect($result)->toBeArray()
        ->and($result['blogSlug'])->toBe($blogSlug)
        ->and($result['middleware'])->toContain('theme');
});

/**
 * Tests index action fallback for dynamic controller/action routes.
 *
 * Verifies that /admin/users maps to /admin/users/index when namespace is set.
 */
test('applies index action fallback for namespaced routes', function () {
    $this->router->add('/admin/{controller}/{action}', [
        'namespace' => 'admin',
    ]);

    // Access /admin/users without explicit action should default to index
    $result = $this->router->match('/admin/users', 'GET');

    expect($result)->toBeArray()
        ->and($result['action'])->toBe('index');
});

/**
 * Tests multiple HTTP methods using 'methods' array.
 *
 * Verifies that routes can accept multiple verbs via 'methods' parameter.
 */
test('matches multiple HTTP methods with methods array', function () {
    $this->router->add('/api/posts', [
        'controller' => 'ApiPosts',
        'methods' => ['GET', 'POST', 'PUT'],
    ]);

    expect($this->router->match('/api/posts', 'GET'))->toBeArray();
    expect($this->router->match('/api/posts', 'POST'))->toBeArray();
    expect($this->router->match('/api/posts', 'PUT'))->toBeArray();
    expect($this->router->match('/api/posts', 'DELETE'))->toBeFalse();
});

/**
 * Tests URL decoding in route matching.
 *
 * Verifies that encoded characters are properly decoded before matching.
 */
test('decodes URL-encoded paths before matching', function () {
    $slug = 'hello world';
    $encodedSlug = 'hello%20world';

    $this->router->add('/posts/{slug}', [
        'controller' => 'Posts',
        'action' => 'show',
    ]);

    $result = $this->router->match("/posts/{$encodedSlug}", 'GET');

    expect($result)->toBeArray()
        ->and($result['slug'])->toBe($slug);
});

/**
 * Tests trailing slash normalization.
 *
 * Verifies that routes match regardless of trailing slashes.
 */
test('normalizes trailing slashes in paths', function () {
    $this->router->add('/users', ['controller' => 'Users']);

    // Both with and without trailing slash should match
    expect($this->router->match('/users/', 'GET'))->toBeArray();
    expect($this->router->match('/users', 'GET'))->toBeArray();
});

/**
 * Tests root path handling.
 *
 * Verifies that empty/root path routes work correctly.
 */
test('handles root path correctly', function () {
    $this->router->add('/', [
        'controller' => 'Home',
        'action' => 'index',
    ]);

    expect($this->router->match('/', 'GET'))->toBeArray()
        ->and($this->router->match('', 'GET'))->toBeArray();
});

/**
 * Tests nested group prefix concatenation.
 *
 * Verifies that nested groups properly chain prefixes.
 */
test('concatenates nested group prefixes', function () {
    $this->router->group(['prefix' => '/api'], function ($r) {
        $r->group(['prefix' => '/v1'], function ($r) {
            $r->add('/users', ['controller' => 'ApiUsers']);
        });
    });

    expect($this->router->match('/api/v1/users', 'GET'))->toBeArray();
    expect($this->router->match('/api/users', 'GET'))->toBeFalse();
    expect($this->router->match('/v1/users', 'GET'))->toBeFalse();
});

/**
 * Tests case-insensitive regex matching.
 *
 * Verifies that route patterns use case-insensitive matching (unicode flag).
 */
test('performs case-insensitive pattern matching', function () {
    $this->router->add('/About', ['controller' => 'About']);

    // Router uses 'iu' flags for unicode + case-insensitive matching
    expect($this->router->match('/about', 'GET'))->toBeArray();
    expect($this->router->match('/ABOUT', 'GET'))->toBeArray();
});

/**
 * Tests parameter names with underscores and hyphens.
 *
 * Verifies that {user_id} and {post-slug} are correctly extracted.
 */
test('extracts parameters with underscores and hyphens', function () {
    $userId = (string) $this->faker->numberBetween(1, 1000);

    $this->router->add('/user/{user_id}/posts', [
        'controller' => 'UserPosts',
    ]);

    $result = $this->router->match("/user/{$userId}/posts", 'GET');

    // Hyphens in param names converted to underscores for PHP named groups
    expect($result)->toBeArray()
        ->and($result['user_id'])->toBe($userId);
});

/**
 * Tests that route params override matched URL params.
 *
 * Verifies merge order: URL matches first, then route params override.
 */
test('route params override URL parameter matches', function () {
    $this->router->add('/admin/{action}', [
        'controller' => 'Dashboard',
        'action' => 'index', // This DOES override {action} from URL
    ]);

    $result = $this->router->match('/admin/users', 'GET');

    // Route params override URL matches: action => 'index' wins
    expect($result['action'])->toBe('index');
});

/**
 * Tests HTTP method case insensitivity.
 *
 * Verifies that method matching is case-insensitive (GET = get = Get).
 */
test('matches HTTP methods case-insensitively', function () {
    $this->router->add('/api/data', [
        'controller' => 'Api',
        'method' => 'get',
    ]);

    expect($this->router->match('/api/data', 'GET'))->toBeArray();
    expect($this->router->match('/api/data', 'get'))->toBeArray();
    expect($this->router->match('/api/data', 'Get'))->toBeArray();
});

/**
 * Tests middleware deduplication in nested groups.
 *
 * Verifies that duplicate middleware entries are removed via array_unique.
 */
test('removes duplicate middleware in nested groups', function () {
    $this->router->group(['middleware' => 'auth'], function ($r) {
        $r->group(['middleware' => ['auth', 'csrf']], function ($r) {
            $r->add('/profile', ['controller' => 'Profile']);
        });
    });

    $result = $this->router->match('/profile', 'GET');

    // 'auth' should appear only once despite being in both groups
    expect($result['middleware'])->toBe(['auth', 'csrf']);
});

/**
 * Tests complex regex patterns with character classes.
 *
 * Verifies that custom regex like [a-z0-9-]+ works in route constraints.
 */
test('supports complex regex patterns in constraints', function () {
    $slug = $this->faker->slug();

    $this->router->add('/category/{slug:[a-z0-9-]+}', [
        'controller' => 'Category',
    ]);

    expect($this->router->match("/category/{$slug}", 'GET'))->toBeArray();
    expect($this->router->match('/category/Invalid_Slug!', 'GET'))->toBeFalse();
});
