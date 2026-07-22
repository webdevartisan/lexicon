<?php

declare(strict_types=1);

/**
 * Unit tests for CommentAudienceResolver.
 *
 * The resolver decides who hears about a comment and in what capacities. Each
 * recipient's list is ordered most personal first (reply > authored >
 * moderation > blog); NotificationService later collapses that to one in-app
 * row and one email. These tests pin the ordering and membership using a fake
 * blog so no database is touched.
 */

use App\Models\BlogModel;
use App\Services\CommentAudienceResolver;

/**
 * Blog double: owner 1, an active editor (2), an active contributor (3), and an
 * inactive editor (4) who must never be treated as a moderator.
 */
function fakeBlog(): object
{
    return new class()
    {
        public function ownerId(): int
        {
            return 1;
        }

        public function users(): array
        {
            return [
                ['user_id' => 2, 'role' => 'editor', 'is_active' => 1],
                ['user_id' => 3, 'role' => 'contributor', 'is_active' => 1],
                ['user_id' => 4, 'role' => 'editor', 'is_active' => 0],
            ];
        }
    };
}

function makeResolver(): CommentAudienceResolver
{
    // baseRoleFor is the identity here: the fake blog already uses base slugs.
    $blogModel = Mockery::mock(BlogModel::class);
    $blogModel->shouldReceive('baseRoleFor')->andReturnUsing(fn (string $role): string => $role);

    return new CommentAudienceResolver($blogModel);
}

afterEach(function () {
    Mockery::close();
});

describe('CommentAudienceResolver::resolve', function () {

    test('a person wearing every hat gets every reason, most personal first', function () {
        // Owner 1 is also the post author and wrote the comment being replied to.
        $audience = makeResolver()->resolve(fakeBlog(), 1, 1, 9, false);

        expect($audience)->toBe([1 => [
            CommentAudienceResolver::TYPE_REPLY,
            CommentAudienceResolver::TYPE_AUTHORED,
            CommentAudienceResolver::TYPE_BLOG,
        ]]);
    });

    test('a pending comment reaches owner and active editors for moderation', function () {
        // Post author 5 is an outsider; inactive editor 4 must be excluded.
        $audience = makeResolver()->resolve(fakeBlog(), 5, null, 9, true);

        expect($audience[1])->toBe([CommentAudienceResolver::TYPE_MODERATION, CommentAudienceResolver::TYPE_BLOG])
            ->and($audience[2])->toBe([CommentAudienceResolver::TYPE_MODERATION])
            ->and($audience[5])->toBe([CommentAudienceResolver::TYPE_AUTHORED])
            ->and($audience)->not->toHaveKey(4);
    });

    test('an author who can also moderate is offered their own post ahead of the queue', function () {
        // Editor 2 wrote the post; a pending comment lands. Authored outranks moderation.
        $audience = makeResolver()->resolve(fakeBlog(), 2, null, 9, true);

        expect($audience[2])->toBe([CommentAudienceResolver::TYPE_AUTHORED, CommentAudienceResolver::TYPE_MODERATION])
            ->and($audience[1])->toBe([CommentAudienceResolver::TYPE_MODERATION, CommentAudienceResolver::TYPE_BLOG]);
    });

    test('the commenter is never notified about their own comment', function () {
        // Author 5 comments on their own post; only the owner-firehose remains.
        $audience = makeResolver()->resolve(fakeBlog(), 5, null, 5, false);

        expect($audience)->toBe([1 => [CommentAudienceResolver::TYPE_BLOG]]);
    });

    test('a reply to a guest notifies no one for the reply', function () {
        // repliedToUserId is null: the parent had no account. Author and owner still hear it.
        $audience = makeResolver()->resolve(fakeBlog(), 5, null, 9, false);

        expect($audience)->toBe([
            5 => [CommentAudienceResolver::TYPE_AUTHORED],
            1 => [CommentAudienceResolver::TYPE_BLOG],
        ]);
    });

    test('the blog firehose reaches only the owner, not editors', function () {
        $audience = makeResolver()->resolve(fakeBlog(), 5, null, 9, false);

        // Editor 2 is not in the audience for a published comment they have no stake in.
        expect($audience)->not->toHaveKey(2);
    });

    test('three distinct stakeholders each get their own reason', function () {
        // Reply to reader 7, on a post by author 5, on owner 1's blog.
        $audience = makeResolver()->resolve(fakeBlog(), 5, 7, 9, false);

        expect($audience)->toBe([
            7 => [CommentAudienceResolver::TYPE_REPLY],
            5 => [CommentAudienceResolver::TYPE_AUTHORED],
            1 => [CommentAudienceResolver::TYPE_BLOG],
        ]);
    });
});
