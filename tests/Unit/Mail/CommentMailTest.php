<?php

declare(strict_types=1);

use App\Mail\BlogCommentMail;
use App\Mail\CommentMail;
use App\Mail\CommentModerationMail;
use App\Mail\CommentReplyMail;
use App\Mail\PostCommentMail;

/**
 * One comment reaches different people for different reasons, and each reason
 * is its own email so it can be worded on its own. What stays in code is the
 * default subject, where the button goes, and whether the held-comment note
 * applies.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';
});

/**
 * @param  class-string<CommentMail>  $class
 */
function commentMail(string $class, bool $awaitingModeration = false, int $blogId = 7): CommentMail
{
    return new $class('to@example.test', 'Ten Days in Crete', 'travel', 'ten-days', 'quietreader', 'Loved it', $awaitingModeration, 128, $blogId);
}

test('each reason has its own subject and opening line', function (string $class, string $subject, string $lead) {
    $mail = commentMail($class);

    expect($mail->getTo())->toHaveKey('to@example.test')
        ->and($mail->getSubject())->toBe($subject)
        ->and($mail->getBody())->toContain($lead)
        ->and($mail->getBody())->toContain('Loved it');
})->with([
    'reply' => [CommentReplyMail::class, 'New reply to your comment on: Ten Days in Crete', 'quietreader replied to your comment on Ten Days in Crete:'],
    'post' => [PostCommentMail::class, 'New comment on your post: Ten Days in Crete', 'quietreader commented on your post Ten Days in Crete:'],
    'moderation' => [CommentModerationMail::class, 'Comment awaiting your approval on: Ten Days in Crete', 'quietreader commented on Ten Days in Crete and it needs your approval:'],
    'blog' => [BlogCommentMail::class, 'New comment on: Ten Days in Crete', 'quietreader commented on Ten Days in Crete:'],
]);

test('a visible comment links to its place on the post', function () {
    expect(commentMail(PostCommentMail::class)->getBody())->toContain('https://example.test/blog/travel/ten-days#comment-128');
});

test('a held comment links to the post without an anchor and says why it is not there yet', function () {
    $body = commentMail(CommentReplyMail::class, awaitingModeration: true)->getBody();

    expect($body)->toContain('https://example.test/blog/travel/ten-days"')
        ->and($body)->not->toContain('#comment-128')
        ->and($body)->toContain('awaiting moderation before it appears publicly');
});

test('moderators are sent to the queue and not told the comment is held', function () {
    $body = commentMail(CommentModerationMail::class, awaitingModeration: true)->getBody();

    expect($body)->toContain('https://example.test/dashboard/blog/7/comments')
        ->and($body)->toContain('Review the comment')
        ->and($body)->not->toContain('awaiting moderation before it appears publicly');
});

test('without a blog to point at, moderators get the post instead', function () {
    expect(commentMail(CommentModerationMail::class, awaitingModeration: true, blogId: 0)->getBody())
        ->toContain('https://example.test/blog/travel/ten-days"');
});
