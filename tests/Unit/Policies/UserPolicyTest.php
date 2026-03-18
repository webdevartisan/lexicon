<?php

declare(strict_types=1);

use App\Policies\UserPolicy;
use App\Resources\UserResource;

/**
 * Unit tests for UserPolicy.
 *
 * UserResource is mocked to isolate pure authorization logic.
 * Tests reflect current behavior where isLastAdministrator() treats
 * ALL admins as the last admin until UserModel injection is implemented.
 */

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Build a mock UserResource with controllable id, roles, and hasRole.
 *
 * @param  string[]  $roles
 */
function mockTargetUser(int $id, array $roles = []): UserResource
{
    $target = Mockery::mock(UserResource::class);
    $target->shouldReceive('id')->andReturn($id);
    $target->shouldReceive('hasRole')->andReturnUsing(
        fn (string $role) => in_array($role, $roles, true)
    );

    return $target;
}

/**
 * Build an actor user array with given ID and global roles.
 *
 * @param  string[]  $roles
 */
function makeActorUser(int $id, array $roles = []): array
{
    return ['id' => $id, 'roles' => $roles];
}

afterEach(fn () => Mockery::close());

// ============================================================================
// view()
// ============================================================================

describe('UserPolicy::view', function () {

    test('administrator can view any user profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        $target = mockTargetUser(2);

        expect($policy->view($actor, $target))->toBeTrue();
    });

    test('user can view their own profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1);
        $target = mockTargetUser(1);

        expect($policy->view($actor, $target))->toBeTrue();
    });

    test('non-admin cannot view another users profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['author']);
        $target = mockTargetUser(2);

        expect($policy->view($actor, $target))->toBeFalse();
    });

    test('guest with no roles cannot view another users profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, []);
        $target = mockTargetUser(2);

        expect($policy->view($actor, $target))->toBeFalse();
    });
});

// ============================================================================
// update()
// ============================================================================

describe('UserPolicy::update', function () {

    test('administrator can update any user profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        $target = mockTargetUser(2);

        expect($policy->update($actor, $target))->toBeTrue();
    });

    test('user can update their own profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1);
        $target = mockTargetUser(1);

        expect($policy->update($actor, $target))->toBeTrue();
    });

    test('non-admin cannot update another users profile', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['author']);
        $target = mockTargetUser(2);

        expect($policy->update($actor, $target))->toBeFalse();
    });
});

// ============================================================================
// delete()
// ============================================================================

describe('UserPolicy::delete', function () {

    test('non-admin user can delete their own account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['author']);
        // Non-admin target: isLastAdministrator() returns false → deletion allowed
        $target = mockTargetUser(1, ['author']);

        expect($policy->delete($actor, $target))->toBeTrue();
    });

    test('admin cannot delete their own account (treated as last admin)', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        // Admin target: isLastAdministrator() always returns true → blocked
        // TODO: this will change when UserModel injection is implemented
        $target = mockTargetUser(1, ['administrator']);

        expect($policy->delete($actor, $target))->toBeFalse();
    });

    test('admin can delete a non-admin user account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        $target = mockTargetUser(2, ['author']);

        expect($policy->delete($actor, $target))->toBeTrue();
    });

    test('admin cannot delete another admin account (treated as last admin)', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        // TODO: this will change when UserModel injection is implemented
        $target = mockTargetUser(2, ['administrator']);

        expect($policy->delete($actor, $target))->toBeFalse();
    });

    test('non-admin cannot delete another users account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['author']);
        $target = mockTargetUser(2, ['author']);

        expect($policy->delete($actor, $target))->toBeFalse();
    });
});

// ============================================================================
// restore()
// ============================================================================

describe('UserPolicy::restore', function () {

    test('administrator can restore a deleted account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        $target = mockTargetUser(2);

        expect($policy->restore($actor, $target))->toBeTrue();
    });

    test('non-admin cannot restore a deleted account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['author']);
        $target = mockTargetUser(2);

        expect($policy->restore($actor, $target))->toBeFalse();
    });

    test('user cannot restore their own deleted account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, []);
        $target = mockTargetUser(1);

        expect($policy->restore($actor, $target))->toBeFalse();
    });
});

// ============================================================================
// forceDelete()
// ============================================================================

describe('UserPolicy::forceDelete', function () {

    test('administrator can permanently delete an account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['administrator']);
        $target = mockTargetUser(2);

        expect($policy->forceDelete($actor, $target))->toBeTrue();
    });

    test('non-admin cannot permanently delete an account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, ['author']);
        $target = mockTargetUser(2);

        expect($policy->forceDelete($actor, $target))->toBeFalse();
    });

    test('user cannot permanently delete their own account', function () {
        $policy = new UserPolicy();
        $actor = makeActorUser(1, []);
        $target = mockTargetUser(1);

        expect($policy->forceDelete($actor, $target))->toBeFalse();
    });
});
