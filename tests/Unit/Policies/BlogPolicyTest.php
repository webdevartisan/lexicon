<?php

declare(strict_types=1);

use App\Policies\BlogPolicy;
use App\Resources\BlogResource;

/**
 * Unit tests for BlogPolicy.
 *
 * Every per-blog decision is a blog permission lookup, so the blog is mocked
 * with a fixed permission set and the tests check which slug each action asks for.
 */

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Every blog permission a policy can ask for, used to prove an action needs its own slug.
 */
const BLOG_PERMISSIONS = [
    'view_all_posts',
    'edit_own_blog',
    'manage_team',
    'create_posts',
    'delete_own_blog',
];

/**
 * Build a blog whose userCan answers from the given permission set.
 *
 * @param  string[]  $permissions
 */
function blogWith(array $permissions): BlogResource
{
    $blog = Mockery::mock(BlogResource::class);
    $blog->shouldReceive('userCan')->andReturnUsing(
        static fn (int $userId, string $permission): bool => in_array($permission, $permissions, true)
    );

    return $blog;
}

/**
 * Build a user array with given ID and global roles.
 *
 * @param  string[]  $roles
 */
function makeUser(int $id, array $roles = []): array
{
    return ['id' => $id, 'roles' => $roles];
}

afterEach(fn () => Mockery::close());

// ============================================================================
// Per-blog actions
// ============================================================================

describe('BlogPolicy per-blog actions', function () {

    test('an action is allowed when the user holds its permission', function (string $action, string $permission) {
        $policy = new BlogPolicy();

        expect($policy->{$action}(makeUser(2), blogWith([$permission])))->toBeTrue();
    })->with([
        ['view', 'view_all_posts'],
        ['update', 'edit_own_blog'],
        ['manageUsers', 'manage_team'],
        ['invite', 'manage_team'],
        ['createPost', 'create_posts'],
        ['delete', 'delete_own_blog'],
    ]);

    test('holding every other permission does not grant the action', function (string $action, string $permission) {
        $policy = new BlogPolicy();
        $others = array_values(array_diff(BLOG_PERMISSIONS, [$permission]));

        expect($policy->{$action}(makeUser(2), blogWith($others)))->toBeFalse();
    })->with([
        ['view', 'view_all_posts'],
        ['update', 'edit_own_blog'],
        ['manageUsers', 'manage_team'],
        ['invite', 'manage_team'],
        ['createPost', 'create_posts'],
        ['delete', 'delete_own_blog'],
    ]);
});

// ============================================================================
// create()
// ============================================================================

describe('BlogPolicy::create', function () {

    test('a system role that may start a blog can create one', function (string $role) {
        $policy = new BlogPolicy();

        expect($policy->create(makeUser(1, [$role])))->toBeTrue();
    })->with(['administrator', 'content_manager', 'reader']);

    // Blog roles live in blog_users and say nothing about starting a blog of your own.
    test('a blog role does not let the account start a blog', function (string $role) {
        $policy = new BlogPolicy();

        expect($policy->create(makeUser(1, [$role])))->toBeFalse();
    })->with(['editor', 'author', 'contributor', 'reviewer', 'viewer']);

    test('user with no roles cannot create blog', function () {
        $policy = new BlogPolicy();

        expect($policy->create(makeUser(1, [])))->toBeFalse();
    });

    test('user with unknown role cannot create blog', function () {
        $policy = new BlogPolicy();

        expect($policy->create(makeUser(1, ['subscriber'])))->toBeFalse();
    });
});
