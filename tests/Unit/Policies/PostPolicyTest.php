<?php

declare(strict_types=1);

use App\Policies\PostPolicy;
use App\Resources\BlogResource;
use App\Resources\PostResource;

/**
 * Unit tests for PostPolicy.
 *
 * Decisions come from the acting user's permissions on the post's blog, with
 * ownership and workflow guards on top, so the blog is mocked with a fixed
 * permission set and the post with an author, status and workflow state.
 */

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Every post permission a policy can ask for, used to prove an action needs its own slug.
 */
const POST_PERMISSIONS = [
    'view_all_posts',
    'edit_blog_posts',
    'edit_own_posts',
    'publish_blog_posts',
    'delete_blog_posts',
    'delete_own_posts',
    'reject_posts',
    'approve_posts',
    'assign_reviewers',
    'review_posts',
];

/**
 * Build a post on a blog whose userCan answers from the given permission set.
 *
 * @param  string[]  $permissions
 */
function postWith(
    array $permissions,
    int $authorId = 7,
    string $status = 'draft',
    string $workflowState = 'draft'
): PostResource {
    $blog = Mockery::mock(BlogResource::class);
    $blog->shouldReceive('userCan')->andReturnUsing(
        static fn (int $userId, string $permission): bool => in_array($permission, $permissions, true)
    );

    $post = Mockery::mock(PostResource::class);
    $post->shouldReceive('blog')->andReturn($blog);
    $post->shouldReceive('authorId')->andReturn($authorId);
    $post->shouldReceive('status')->andReturn($status);
    $post->shouldReceive('workflowState')->andReturn($workflowState);

    return $post;
}

function postActor(int $id = 2): array
{
    return ['id' => $id, 'roles' => []];
}

afterEach(fn () => Mockery::close());

// ============================================================================
// Straight permission checks
// ============================================================================

describe('PostPolicy permission mapping', function () {

    test('an action is allowed when the user holds its permission', function (string $action, string $permission) {
        $policy = new PostPolicy();

        expect($policy->{$action}(postActor(), postWith([$permission])))->toBeTrue();
    })->with([
        ['view', 'view_all_posts'],
        ['publish', 'publish_blog_posts'],
        ['markAsNeedsChanges', 'reject_posts'],
        ['approve', 'approve_posts'],
        ['assignReviewer', 'assign_reviewers'],
        ['reviewPost', 'review_posts'],
    ]);

    test('holding every other permission does not grant the action', function (string $action, string $permission) {
        $policy = new PostPolicy();
        $others = array_values(array_diff(POST_PERMISSIONS, [$permission]));

        expect($policy->{$action}(postActor(), postWith($others)))->toBeFalse();
    })->with([
        ['view', 'view_all_posts'],
        ['publish', 'publish_blog_posts'],
        ['markAsNeedsChanges', 'reject_posts'],
        ['approve', 'approve_posts'],
        ['assignReviewer', 'assign_reviewers'],
        ['reviewPost', 'review_posts'],
    ]);
});

// ============================================================================
// update()
// ============================================================================

describe('PostPolicy::update', function () {

    test('edit_blog_posts updates anyone else\'s post, in review or not', function () {
        $policy = new PostPolicy();
        $post = postWith(['edit_blog_posts'], authorId: 99, workflowState: 'in_review');

        expect($policy->update(postActor(), $post))->toBeTrue();
    });

    test('edit_own_posts updates the user\'s own post', function () {
        $policy = new PostPolicy();
        $post = postWith(['edit_own_posts'], authorId: 2);

        expect($policy->update(postActor(), $post))->toBeTrue();
    });

    test('edit_own_posts does not reach someone else\'s post', function () {
        $policy = new PostPolicy();
        $post = postWith(['edit_own_posts'], authorId: 99);

        expect($policy->update(postActor(), $post))->toBeFalse();
    });

    // The author gets their post back once a reviewer sends it back for changes.
    test('a post in review is locked for its own author', function () {
        $policy = new PostPolicy();
        $post = postWith(['edit_own_posts'], authorId: 2, workflowState: 'in_review');

        expect($policy->update(postActor(), $post))->toBeFalse();
    });

    test('without an edit permission nobody updates the post', function () {
        $policy = new PostPolicy();
        $post = postWith(['view_all_posts'], authorId: 2);

        expect($policy->update(postActor(), $post))->toBeFalse();
    });
});

// ============================================================================
// delete()
// ============================================================================

describe('PostPolicy::delete', function () {

    test('delete_blog_posts deletes any post whatever its status', function () {
        $policy = new PostPolicy();
        $post = postWith(['delete_blog_posts'], authorId: 99, status: 'published');

        expect($policy->delete(postActor(), $post))->toBeTrue();
    });

    test('delete_own_posts deletes the user\'s own draft', function () {
        $policy = new PostPolicy();
        $post = postWith(['delete_own_posts'], authorId: 2, status: 'draft');

        expect($policy->delete(postActor(), $post))->toBeTrue();
    });

    test('delete_own_posts stops at a published post', function () {
        $policy = new PostPolicy();
        $post = postWith(['delete_own_posts'], authorId: 2, status: 'published');

        expect($policy->delete(postActor(), $post))->toBeFalse();
    });

    test('delete_own_posts does not reach someone else\'s draft', function () {
        $policy = new PostPolicy();
        $post = postWith(['delete_own_posts'], authorId: 99, status: 'draft');

        expect($policy->delete(postActor(), $post))->toBeFalse();
    });

    test('without a delete permission nobody deletes the post', function () {
        $policy = new PostPolicy();
        $post = postWith(['edit_blog_posts'], authorId: 2, status: 'draft');

        expect($policy->delete(postActor(), $post))->toBeFalse();
    });
});
