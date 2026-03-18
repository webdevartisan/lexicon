<?php

declare(strict_types=1);

use App\Policies\PostPolicy;
use App\Resources\PostResource;

/**
 * Unit tests for PostPolicy.
 *
 * Blog and post objects are mocked to isolate pure authorization logic.
 */

// ============================================================================
// HELPERS
// ============================================================================

/**
 * Build a mock blog with controllable ownerId and roleForUser.
 */
function mockPostBlog(int $ownerId, string $role = ''): object
{
    $blog = Mockery::mock(\App\Resources\BlogResource::class);
    $blog->shouldReceive('ownerId')->andReturn($ownerId);
    $blog->shouldReceive('roleForUser')->andReturn($role);

    return $blog;
}

/**
 * Build a mock PostResource with controllable blog, authorId, and status.
 */
function mockPost(int $blogOwnerId, string $blogRole = '', int $authorId = 0, string $status = 'draft'): PostResource
{
    $blog = mockPostBlog($blogOwnerId, $blogRole);

    $post = Mockery::mock(PostResource::class);
    $post->shouldReceive('blog')->andReturn($blog);
    $post->shouldReceive('authorId')->andReturn($authorId);
    $post->shouldReceive('status')->andReturn($status);

    return $post;
}

afterEach(fn () => Mockery::close());

// ============================================================================
// view()
// ============================================================================

describe('PostPolicy::view', function () {

    test('blog owner can always view post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: '');

        expect($policy->view(['id' => 1], $post))->toBeTrue();
    });

    test('non-owner with allowed per-blog role can view post', function (string $role) {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: $role);

        expect($policy->view(['id' => 2], $post))->toBeTrue();
    })->with(['editor', 'author', 'contributor', 'reviewer', 'viewer']);

    test('non-owner with no blog role cannot view post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: '');

        expect($policy->view(['id' => 2], $post))->toBeFalse();
    });

    test('non-owner with unknown blog role cannot view post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'guest');

        expect($policy->view(['id' => 2], $post))->toBeFalse();
    });
});

// ============================================================================
// update()
// ============================================================================

describe('PostPolicy::update', function () {

    test('blog owner can always update any post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: '', authorId: 99);

        expect($policy->update(['id' => 1], $post))->toBeTrue();
    });

    test('editor can update any post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'editor', authorId: 99);

        expect($policy->update(['id' => 2], $post))->toBeTrue();
    });

    test('author can update their own post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author', authorId: 2);

        expect($policy->update(['id' => 2], $post))->toBeTrue();
    });

    test('author cannot update another author post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author', authorId: 99);

        expect($policy->update(['id' => 2], $post))->toBeFalse();
    });

    test('viewer cannot update a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'viewer', authorId: 2);

        expect($policy->update(['id' => 2], $post))->toBeFalse();
    });

    test('non-member cannot update a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: '', authorId: 2);

        expect($policy->update(['id' => 2], $post))->toBeFalse();
    });
});

// ============================================================================
// publish()
// ============================================================================

describe('PostPolicy::publish', function () {

    test('blog owner can publish a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1);

        expect($policy->publish(['id' => 1], $post))->toBeTrue();
    });

    test('editor can publish a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'editor');

        expect($policy->publish(['id' => 2], $post))->toBeTrue();
    });

    test('author cannot publish a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author');

        expect($policy->publish(['id' => 2], $post))->toBeFalse();
    });

    test('reviewer cannot publish a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'reviewer');

        expect($policy->publish(['id' => 2], $post))->toBeFalse();
    });
});

// ============================================================================
// delete()
// ============================================================================

describe('PostPolicy::delete', function () {

    test('blog owner can delete any post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: '', authorId: 99, status: 'published');

        expect($policy->delete(['id' => 1], $post))->toBeTrue();
    });

    test('editor can delete any post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'editor', authorId: 99, status: 'published');

        expect($policy->delete(['id' => 2], $post))->toBeTrue();
    });

    test('author can delete their own draft', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author', authorId: 2, status: 'draft');

        expect($policy->delete(['id' => 2], $post))->toBeTrue();
    });

    test('author cannot delete their own published post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author', authorId: 2, status: 'published');

        expect($policy->delete(['id' => 2], $post))->toBeFalse();
    });

    test('author cannot delete another authors post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author', authorId: 99, status: 'draft');

        expect($policy->delete(['id' => 2], $post))->toBeFalse();
    });

    test('viewer cannot delete a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'viewer', authorId: 2, status: 'draft');

        expect($policy->delete(['id' => 2], $post))->toBeFalse();
    });
});

// ============================================================================
// markAsNeedsChanges()
// ============================================================================

describe('PostPolicy::markAsNeedsChanges', function () {

    test('blog owner can mark post as needs changes', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1);

        expect($policy->markAsNeedsChanges(['id' => 1], $post))->toBeTrue();
    });

    test('reviewer can mark post as needs changes', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'reviewer');

        expect($policy->markAsNeedsChanges(['id' => 2], $post))->toBeTrue();
    });

    test('editor cannot mark post as needs changes', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'editor');

        expect($policy->markAsNeedsChanges(['id' => 2], $post))->toBeFalse();
    });

    test('author cannot mark post as needs changes', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author');

        expect($policy->markAsNeedsChanges(['id' => 2], $post))->toBeFalse();
    });
});

// ============================================================================
// approve()
// ============================================================================

describe('PostPolicy::approve', function () {

    test('blog owner can approve a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1);

        expect($policy->approve(['id' => 1], $post))->toBeTrue();
    });

    test('reviewer can approve a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'reviewer');

        expect($policy->approve(['id' => 2], $post))->toBeTrue();
    });

    test('editor cannot approve a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'editor');

        expect($policy->approve(['id' => 2], $post))->toBeFalse();
    });

    test('author cannot approve a post', function () {
        $policy = new PostPolicy();
        $post   = mockPost(blogOwnerId: 1, blogRole: 'author');

        expect($policy->approve(['id' => 2], $post))->toBeFalse();
    });
});
