<?php

declare(strict_types=1);

use App\Presenters\NotificationPresenter;

/**
 * What a notification says and where it goes.
 *
 * The destination matters most: it used to be posted back by the browser as a
 * hidden field, and it is now worked out here from the row's own payload, so
 * these are the tests standing between a reader and a link to nowhere.
 */
function notificationRow(string $type, array $payload = [], ?string $readAt = null): array
{
    return [
        'id' => 7,
        'type' => $type,
        'scope' => 'personal',
        'data' => json_encode($payload),
        'read_at' => $readAt,
        'created_at' => '2026-09-22 10:00:00',
    ];
}

test('a reply points at the comment on the post it was left on', function () {
    $item = NotificationPresenter::for(notificationRow('comment.reply', [
        'commenter_name' => 'Ada',
        'post_title' => 'On Rivers',
        'blog_slug' => 'field-notes',
        'post_slug' => 'on-rivers',
        'comment_id' => 42,
    ]));

    expect($item['title'])->toBe('Ada replied to your comment on On Rivers')
        ->and($item['href'])->toBe('/blog/field-notes/on-rivers#comment-42')
        ->and($item['icon'])->toBe('reply')
        ->and($item['tone'])->toBe(NotificationPresenter::TONE_NEUTRAL)
        ->and($item['isUnread'])->toBeTrue();
});

test('a comment still in the queue points at the queue, not at a page that hides it', function () {
    $item = NotificationPresenter::for(notificationRow('comment.on_your_post', [
        'blog_id' => 3,
        'blog_slug' => 'field-notes',
        'post_slug' => 'on-rivers',
        'comment_id' => 42,
        'awaiting_moderation' => true,
    ]));

    expect($item['href'])->toBe('/dashboard/blog/3/comments');
});

test('a payload with no slugs gives no link rather than half a URL', function () {
    $item = NotificationPresenter::for(notificationRow('post.published', ['post_title' => 'Old One']));

    expect($item['href'])->toBe('')
        ->and($item['title'])->toContain('Old One');
});

test('a moderator warning carries the message itself and has nowhere to send anyone', function () {
    $item = NotificationPresenter::for(notificationRow('moderation.warning', [
        'subject_type' => 'comment',
        'subject_label' => 'on Rivers',
        'message' => 'Please keep it civil.',
    ]));

    expect($item['message'])->toBe('Please keep it civil.')
        ->and($item['href'])->toBe('')
        ->and($item['tone'])->toBe(NotificationPresenter::TONE_CRITICAL);
});

test('good news and bad news read as different tones', function () {
    expect(NotificationPresenter::for(notificationRow('post.approved'))['tone'])->toBe(NotificationPresenter::TONE_POSITIVE)
        ->and(NotificationPresenter::for(notificationRow('post.needs_changes'))['tone'])->toBe(NotificationPresenter::TONE_ATTENTION)
        ->and(NotificationPresenter::for(notificationRow('collaborator.removed'))['tone'])->toBe(NotificationPresenter::TONE_CRITICAL);
});

test('a read row is not reported as unread', function () {
    $item = NotificationPresenter::for(notificationRow('post.approved', [], '2026-09-22 11:00:00'));

    expect($item['isUnread'])->toBeFalse();
});

test('an unknown type still renders as a row instead of blowing up', function () {
    $item = NotificationPresenter::for(notificationRow('something.new.we.added'));

    expect($item['title'])->toBe('something.new.we.added')
        ->and($item['icon'])->toBe('bell')
        ->and($item['href'])->toBe('');
});

test('an admin alert points at the thing that needs looking at', function () {
    expect(NotificationPresenter::for(notificationRow('admin.report_threshold', ['case_id' => 12, 'report_count' => 3]))['href'])
        ->toBe('/admin/reports/12')
        ->and(NotificationPresenter::for(notificationRow('admin.mail_queue_failures', ['failed_count' => 4]))['href'])
        ->toBe('/admin/mail-queue?status=failed');
});

test('a whole list is presented in one call', function () {
    $items = NotificationPresenter::forAll([
        notificationRow('post.approved', ['post_title' => 'One']),
        notificationRow('post.needs_changes', ['post_title' => 'Two']),
    ]);

    expect($items)->toHaveCount(2)
        ->and($items[0]['title'])->toContain('One')
        ->and($items[1]['title'])->toContain('Two');
});
