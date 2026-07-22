<?php

declare(strict_types=1);

use App\Mail\CollaboratorRemovedMail;
use App\Mail\NewCommentMail;
use App\Mail\PostApprovedMail;
use App\Mail\PostNeedsChangesMail;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\CommentAudienceResolver;
use App\Services\MailQueueService;
use App\Services\NotificationService;

/**
 * Unit tests for the NotificationService dispatcher.
 *
 * dispatch() always writes the in-app row, then queues a Mailable when the
 * gating preference is on. dispatchFirstEnabled() extends that to a recipient
 * who qualifies for several types at once: one in-app row for the most personal
 * type, and at most one email under the most important type still switched on.
 */
afterEach(function () {
    Mockery::close();
});

function makeNotificationService(
    ?NotificationModel $notif = null,
    ?UserModel $users = null,
    ?UserPreferencesModel $prefs = null,
    ?MailQueueService $queue = null,
): NotificationService {
    return new NotificationService(
        $notif ?? Mockery::mock(NotificationModel::class),
        $users ?? Mockery::mock(UserModel::class),
        $prefs ?? Mockery::mock(UserPreferencesModel::class),
        $queue ?? Mockery::mock(MailQueueService::class),
    );
}

describe('NotificationService::dispatch', function () {

    test('always writes the in-app notification row, even when email is muted', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')
            ->with(7, 'post.approved', ['post_id' => 1, 'post_title' => 'T', 'reviewer_username' => 'r'])
            ->once()
            ->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'a@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_post_status')->andReturn(false);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'post.approved', [
            'post_id' => 1, 'post_title' => 'T', 'reviewer_username' => 'r',
        ]);

        expect(true)->toBeTrue();
    });

    test('queues the Mailable when the gating preference is enabled', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'author@example.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_post_status')->andReturn(true);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::type(PostApprovedMail::class), 'notification', 7)
            ->andReturn(1);

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'post.approved', [
            'post_id' => 1, 'post_title' => 'My Post', 'reviewer_username' => 'bob',
        ]);

        expect(true)->toBeTrue();
    });

    test('post.needs_changes forwards feedback to the Mailable', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'a@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->andReturn(true);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::on(function ($m) {
                if (!$m instanceof PostNeedsChangesMail) {
                    return false;
                }
                // Body is only populated once the mailable is built.
                $m->build();

                return str_contains($m->getBody(), 'fix the intro');
            }), 'notification', 7)
            ->andReturn(1);

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'post.needs_changes', [
            'post_id' => 1, 'post_title' => 'My Post', 'reviewer_username' => 'r',
            'feedback' => 'Please fix the intro',
        ]);

        expect(true)->toBeTrue();
    });

    test('collaborator.removed is gated by notify_role_changes', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'gone@example.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_role_changes')->andReturn(true);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->once()->with(Mockery::type(CollaboratorRemovedMail::class), 'notification', 7)->andReturn(1);

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'collaborator.removed', [
            'blog_name' => 'My Blog', 'removed_by_username' => 'admin',
        ]);

        expect(true)->toBeTrue();
    });

    test('post.reviewer_assigned is gated by the new notify_review_requests toggle', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'reviewer@example.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_review_requests')->andReturn(false);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'post.reviewer_assigned', [
            'post_id' => 1, 'post_title' => 'T', 'assigned_by_username' => 'owner',
        ]);

        expect(true)->toBeTrue();
    });

    test('blog.invite writes in-app but never emails', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->never();

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->never();

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'blog.invite', [
            'blog_id' => 1, 'role' => 'author', 'invited_by' => 2, 'token' => 'abc',
        ]);

        expect(true)->toBeTrue();
    });

    test('skips email when the recipient user record cannot be loaded', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(null);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->never();

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, $users, $prefs, $queue)->dispatch(7, 'post.approved', [
            'post_id' => 1, 'post_title' => 'T', 'reviewer_username' => 'r',
        ]);

        expect(true)->toBeTrue();
    });

    test('unknown type writes the in-app row but never emails', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, null, null, $queue)->dispatch(7, 'totally.new.event', ['arbitrary' => 'payload']);

        expect(true)->toBeTrue();
    });
});

describe('NotificationService::dispatchFirstEnabled', function () {

    // A pending comment on a blog you own: you qualify for both moderation and
    // the blog firehose. These pin the fall-through the settings page promises.
    $commentPayload = [
        'post_title' => 'P', 'blog_slug' => 'b', 'post_slug' => 'p',
        'commenter_name' => 'x', 'comment_excerpt' => 'e',
        'awaiting_moderation' => true, 'comment_id' => 5, 'blog_id' => 7,
    ];

    test('records the in-app row for the most personal type', function () use ($commentPayload) {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')
            ->once()
            ->with(7, CommentAudienceResolver::TYPE_MODERATION, $commentPayload)
            ->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->andReturn(['id' => 7, 'email' => 'o@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->andReturn(false);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, $users, $prefs, $queue)->dispatchFirstEnabled(
            7,
            [CommentAudienceResolver::TYPE_MODERATION, CommentAudienceResolver::TYPE_BLOG],
            $commentPayload
        );

        expect(true)->toBeTrue();
    });

    test('both toggles on emails once, under the more important type', function () use ($commentPayload) {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->andReturn(['id' => 7, 'email' => 'o@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_comments_moderation')->andReturn(true);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::on(function ($m) {
                $m->build();

                return $m instanceof NewCommentMail && str_contains($m->getSubject(), 'awaiting your approval');
            }), 'notification', 7)
            ->andReturn(1);

        makeNotificationService($notif, $users, $prefs, $queue)->dispatchFirstEnabled(
            7,
            [CommentAudienceResolver::TYPE_MODERATION, CommentAudienceResolver::TYPE_BLOG],
            $commentPayload
        );

        expect(true)->toBeTrue();
    });

    test('muting the top type falls through to the next one still on', function () use ($commentPayload) {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->andReturn(['id' => 7, 'email' => 'o@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_comments_moderation')->andReturn(false);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_comments_blog')->andReturn(true);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')
            ->once()
            ->with(Mockery::on(function ($m) {
                $m->build();

                // The blog-reason subject is the plain one, not the moderation phrasing.
                return $m instanceof NewCommentMail
                    && str_contains($m->getSubject(), 'New comment on')
                    && !str_contains($m->getSubject(), 'approval');
            }), 'notification', 7)
            ->andReturn(1);

        makeNotificationService($notif, $users, $prefs, $queue)->dispatchFirstEnabled(
            7,
            [CommentAudienceResolver::TYPE_MODERATION, CommentAudienceResolver::TYPE_BLOG],
            $commentPayload
        );

        expect(true)->toBeTrue();
    });

    test('all matching toggles off sends no email but still records in-app', function () use ($commentPayload) {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->andReturn(['id' => 7, 'email' => 'o@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->andReturn(false);

        $queue = Mockery::mock(MailQueueService::class);
        $queue->shouldReceive('enqueue')->never();

        makeNotificationService($notif, $users, $prefs, $queue)->dispatchFirstEnabled(
            7,
            [CommentAudienceResolver::TYPE_MODERATION, CommentAudienceResolver::TYPE_BLOG],
            $commentPayload
        );

        expect(true)->toBeTrue();
    });
});
