<?php

declare(strict_types=1);

use App\Policies\BlogPolicy;

/**
 * Unit tests for BlogPolicy.
 *
 * All blog object interactions are mocked to isolate pure authorization logic.
 */

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Build a mock blog object with controllable ownerId and roleForUser responses.
 */
function mockBlog(int $ownerId, string $roleForUser = ''): object
{
    $blog = Mockery::mock();
    $blog->shouldReceive('ownerId')->andReturn($ownerId);
    $blog->shouldReceive('roleForUser')->andReturn($roleForUser);

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
// view()
// ============================================================================

describe('BlogPolicy::view', function () {

    test('owner can always view their blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1);
        $blog = mockBlog(ownerId: 1);

        expect($policy->view($user, $blog))->toBeTrue();
    });

    test('non-owner with allowed per-blog role can view', function (string $role) {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: $role);

        expect($policy->view($user, $blog))->toBeTrue();
    })->with(['editor', 'author', 'viewer', 'contributor', 'reviewer']);

    test('non-owner with no blog role cannot view', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: '');

        expect($policy->view($user, $blog))->toBeFalse();
    });

    test('non-owner with unknown blog role cannot view', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'guest');

        expect($policy->view($user, $blog))->toBeFalse();
    });
});

// ============================================================================
// create()
// ============================================================================

describe('BlogPolicy::create', function () {

    test('user with allowed global role can create blog', function (string $role) {
        $policy = new BlogPolicy();
        $user = makeUser(1, [$role]);

        expect($policy->create($user))->toBeTrue();
    })->with(['administrator', 'editor', 'author', 'content_manager', 'blog_owner']);

    test('user with no roles cannot create blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1, []);

        expect($policy->create($user))->toBeFalse();
    });

    test('user with unknown role cannot create blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1, ['subscriber']);

        expect($policy->create($user))->toBeFalse();
    });
});

// ============================================================================
// update()
// ============================================================================

describe('BlogPolicy::update', function () {

    test('owner can always update their blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1);
        $blog = mockBlog(ownerId: 1);

        expect($policy->update($user, $blog))->toBeTrue();
    });

    test('per-blog editor can update blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'editor');

        expect($policy->update($user, $blog))->toBeTrue();
    });

    test('per-blog author cannot update blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'author');

        expect($policy->update($user, $blog))->toBeFalse();
    });

    test('non-owner with no blog role cannot update', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: '');

        expect($policy->update($user, $blog))->toBeFalse();
    });
});

// ============================================================================
// manageUsers()
// ============================================================================

describe('BlogPolicy::manageUsers', function () {

    test('owner can manage users', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1);
        $blog = mockBlog(ownerId: 1);

        expect($policy->manageUsers($user, $blog))->toBeTrue();
    });

    test('per-blog editor can manage users', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'editor');

        expect($policy->manageUsers($user, $blog))->toBeTrue();
    });

    test('per-blog author cannot manage users', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'author');

        expect($policy->manageUsers($user, $blog))->toBeFalse();
    });

    test('non-owner with no blog role cannot manage users', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: '');

        expect($policy->manageUsers($user, $blog))->toBeFalse();
    });
});

// ============================================================================
// createPost()
// ============================================================================

describe('BlogPolicy::createPost', function () {

    test('owner can always create a post', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1);
        $blog = mockBlog(ownerId: 1);

        expect($policy->createPost($user, $blog))->toBeTrue();
    });

    test('non-owner with allowed per-blog role can create post', function (string $role) {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: $role);

        expect($policy->createPost($user, $blog))->toBeTrue();
    })->with(['editor', 'author', 'contributor']);

    test('viewer cannot create a post', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'viewer');

        expect($policy->createPost($user, $blog))->toBeFalse();
    });

    test('non-owner with no blog role cannot create a post', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: '');

        expect($policy->createPost($user, $blog))->toBeFalse();
    });
});

// ============================================================================
// delete()
// ============================================================================

describe('BlogPolicy::delete', function () {

    test('owner can delete their blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(1);
        $blog = mockBlog(ownerId: 1);

        expect($policy->delete($user, $blog))->toBeTrue();
    });

    test('editor cannot delete blog even with per-blog role', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: 'editor');

        expect($policy->delete($user, $blog))->toBeFalse();
    });

    test('non-owner with no role cannot delete blog', function () {
        $policy = new BlogPolicy();
        $user = makeUser(2);
        $blog = mockBlog(ownerId: 1, roleForUser: '');

        expect($policy->delete($user, $blog))->toBeFalse();
    });
});
