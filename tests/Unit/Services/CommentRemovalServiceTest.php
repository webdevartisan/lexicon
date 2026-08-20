<?php

declare(strict_types=1);

/**
 * Unit tests for comment removal.
 *
 * Two things have to hold no matter who pressed the button: only the author or
 * a blog moderator gets to remove a comment, and removing one must never take
 * somebody else's replies down with it.
 */

use App\Models\CommentModel;
use App\Services\CommentRemovalService;

beforeEach(function () {
    $this->comments = Mockery::mock(CommentModel::class);
    $this->service = new CommentRemovalService($this->comments);
});

// ---------------------------------------------------------------- permission

test('the author may remove their own comment', function () {
    $comment = ['id' => 5, 'user_id' => 7, 'deleted_at' => null];

    expect($this->service->canRemove($comment, 7, false))->toBeTrue();
});

test('a stranger may not remove someone else’s comment', function () {
    $comment = ['id' => 5, 'user_id' => 7, 'deleted_at' => null];

    expect($this->service->canRemove($comment, 99, false))->toBeFalse();
});

test('a blog moderator may remove any comment on their blog', function () {
    $comment = ['id' => 5, 'user_id' => 7, 'deleted_at' => null];

    expect($this->service->canRemove($comment, 99, true))->toBeTrue();
});

test('a guest may not remove anything', function () {
    $comment = ['id' => 5, 'user_id' => 7, 'deleted_at' => null];

    expect($this->service->canRemove($comment, null, true))->toBeFalse();
});

test('a guest comment is nobody’s to remove but a moderator’s', function () {
    $comment = ['id' => 5, 'user_id' => null, 'deleted_at' => null];

    expect($this->service->canRemove($comment, 7, false))->toBeFalse()
        ->and($this->service->canRemove($comment, 7, true))->toBeTrue();
});

test('an already removed comment cannot be removed again', function () {
    $comment = ['id' => 5, 'user_id' => 7, 'deleted_at' => '2026-08-19 12:00:00'];

    expect($this->service->canRemove($comment, 7, true))->toBeFalse();
});

// ----------------------------------------------------------- thread integrity

/**
 * A comment row as remove() reads it before deciding anything.
 *
 * @param  array<string, mixed>  $overrides  Columns this case cares about
 * @return array<string, mixed>
 */
function commentRow(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'user_id' => 7,
        'parent_comment_id' => null,
        'reply_to_comment_id' => null,
        'deleted_at' => null,
    ], $overrides);
}

test('a comment with replies becomes a tombstone so the replies survive', function () {
    $this->comments->shouldReceive('findById')->once()->with(5)->andReturn(commentRow(5));
    $this->comments->shouldReceive('hasReplies')->once()->with(5)->andReturnTrue();
    $this->comments->shouldReceive('softDelete')->once()->with(5, 'author')->andReturnTrue();
    $this->comments->shouldNotReceive('deleteById');

    expect($this->service->remove(5, CommentRemovalService::BY_AUTHOR))->toBeFalse();
});

test('a comment nothing replies to is deleted outright', function () {
    $this->comments->shouldReceive('findById')->once()->with(5)->andReturn(commentRow(5));
    $this->comments->shouldReceive('hasReplies')->once()->with(5)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(5)->andReturnTrue();
    $this->comments->shouldNotReceive('softDelete');

    expect($this->service->remove(5, CommentRemovalService::BY_AUTHOR))->toBeTrue();
});

test('a comment that has already gone is not removed twice', function () {
    $this->comments->shouldReceive('findById')->once()->with(5)->andReturnNull();
    $this->comments->shouldNotReceive('deleteById');
    $this->comments->shouldNotReceive('softDelete');

    expect($this->service->remove(5, CommentRemovalService::BY_AUTHOR))->toBeFalse();
});

test('the tombstone goes too once its last reply is removed', function () {
    $this->comments->shouldReceive('findById')->once()->with(9)
        ->andReturn(commentRow(9, ['parent_comment_id' => 5]));
    $this->comments->shouldReceive('hasReplies')->once()->with(9)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(9)->andReturnTrue();

    // The parent is a tombstone that was only being kept for this reply.
    $this->comments->shouldReceive('findById')->once()->with(5)
        ->andReturn(commentRow(5, ['deleted_at' => '2026-08-19 12:00:00']));
    $this->comments->shouldReceive('hasReplies')->once()->with(5)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(5)->andReturnTrue();

    expect($this->service->remove(9, CommentRemovalService::BY_MODERATOR))->toBeTrue();
});

test('a live parent is left alone when one of its replies is removed', function () {
    $this->comments->shouldReceive('findById')->once()->with(9)
        ->andReturn(commentRow(9, ['parent_comment_id' => 5]));
    $this->comments->shouldReceive('hasReplies')->once()->with(9)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(9)->andReturnTrue();

    $this->comments->shouldReceive('findById')->once()->with(5)->andReturn(commentRow(5));

    expect($this->service->remove(9, CommentRemovalService::BY_MODERATOR))->toBeTrue();
});

test('a tombstone still holding other replies is kept', function () {
    $this->comments->shouldReceive('findById')->once()->with(9)
        ->andReturn(commentRow(9, ['parent_comment_id' => 5]));
    $this->comments->shouldReceive('hasReplies')->once()->with(9)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(9)->andReturnTrue();

    $this->comments->shouldReceive('findById')->once()->with(5)
        ->andReturn(commentRow(5, ['deleted_at' => '2026-08-19 12:00:00']));
    $this->comments->shouldReceive('hasReplies')->once()->with(5)->andReturnTrue();

    expect($this->service->remove(9, CommentRemovalService::BY_MODERATOR))->toBeTrue();
});

test('the comment a clamped reply answered is collected along with its parent', function () {
    // Past the depth cap the reply hangs under 5 but answers 7, so 7 is the one
    // being held open as a tombstone for it.
    $this->comments->shouldReceive('findById')->once()->with(9)
        ->andReturn(commentRow(9, ['parent_comment_id' => 5, 'reply_to_comment_id' => 7]));
    $this->comments->shouldReceive('hasReplies')->once()->with(9)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(9)->andReturnTrue();

    $this->comments->shouldReceive('findById')->once()->with(5)->andReturn(commentRow(5));

    $this->comments->shouldReceive('findById')->once()->with(7)
        ->andReturn(commentRow(7, ['deleted_at' => '2026-08-19 12:00:00']));
    $this->comments->shouldReceive('hasReplies')->once()->with(7)->andReturnFalse();
    $this->comments->shouldReceive('deleteById')->once()->with(7)->andReturnTrue();

    expect($this->service->remove(9, CommentRemovalService::BY_MODERATOR))->toBeTrue();
});
