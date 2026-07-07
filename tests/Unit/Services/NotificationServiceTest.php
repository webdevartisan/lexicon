<?php

declare(strict_types=1);

use App\Mail\CollaboratorRemovedMail;
use App\Mail\PostApprovedMail;
use App\Mail\PostNeedsChangesMail;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\MailService;
use App\Services\NotificationService;

/**
 * Unit tests for NotificationService dispatcher.
 *
 * Verifies that dispatch() always writes the in-app row, then conditionally
 * builds and sends a Mailable based on the user's notification preference.
 */
afterEach(fn () => Mockery::close());

function makeNotificationService(
    ?NotificationModel $notif = null,
    ?UserModel $users = null,
    ?UserPreferencesModel $prefs = null,
    ?MailService $mail = null,
): NotificationService {
    return new NotificationService(
        $notif ?? Mockery::mock(NotificationModel::class),
        $users ?? Mockery::mock(UserModel::class),
        $prefs ?? Mockery::mock(UserPreferencesModel::class),
        $mail ?? Mockery::mock(MailService::class),
    );
}

describe('NotificationService::dispatch', function () {

    test('always writes the in-app notification row', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')
            ->with(7, 'post.approved', ['post_id' => 1, 'post_title' => 'T', 'reviewer_username' => 'r'])
            ->once()
            ->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'a@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_post_status')->andReturn(false);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')->never();

        $service = makeNotificationService($notif, $users, $prefs, $mail);
        $service->dispatch(7, 'post.approved', [
            'post_id' => 1, 'post_title' => 'T', 'reviewer_username' => 'r',
        ]);

        // Mockery verifies ->once() / ->never() expectations on afterEach close()
        expect(true)->toBeTrue();
    });

    test('sends Mailable when the gating preference is enabled', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'author@example.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_post_status')->andReturn(true);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')
            ->once()
            ->with(Mockery::type(PostApprovedMail::class));

        $service = makeNotificationService($notif, $users, $prefs, $mail);
        $service->dispatch(7, 'post.approved', [
            'post_id' => 1, 'post_title' => 'My Post', 'reviewer_username' => 'bob',
        ]);
    });

    test('post.needs_changes forwards feedback to Mailable', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        // Brief had ->shouldReceive('find') — corrected to findById (real method name)
        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'a@b.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->andReturn(true);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn ($m) => $m instanceof PostNeedsChangesMail
                && str_contains($m->getBody(), 'fix the intro')));

        $service = makeNotificationService($notif, $users, $prefs, $mail);
        $service->dispatch(7, 'post.needs_changes', [
            'post_id' => 1, 'post_title' => 'My Post', 'reviewer_username' => 'r',
            'feedback' => 'Please fix the intro',
        ]);
    });

    test('collaborator.removed uses notify_role_changes preference', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        // Brief had ->shouldReceive('find') — corrected to findById (real method name)
        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(['id' => 7, 'email' => 'gone@example.test']);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->with(7, 'notify_role_changes')->andReturn(true);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')->once()->with(Mockery::type(CollaboratorRemovedMail::class));

        $service = makeNotificationService($notif, $users, $prefs, $mail);
        $service->dispatch(7, 'collaborator.removed', [
            'blog_name' => 'My Blog', 'removed_by_username' => 'admin',
        ]);
    });

    test('blog.invite writes in-app but skips email (InvitationService sends BlogInviteMail itself)', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->never();

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->never();

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')->never();

        $service = makeNotificationService($notif, $users, $prefs, $mail);
        $service->dispatch(7, 'blog.invite', [
            'blog_id' => 1, 'role' => 'author', 'invited_by' => 2, 'token' => 'abc',
        ]);
    });

    test('skips email when recipient user record cannot be loaded', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->with(7)->andReturn(null);

        $prefs = Mockery::mock(UserPreferencesModel::class);
        $prefs->shouldReceive('notificationPreference')->never();

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')->never();

        $service = makeNotificationService($notif, $users, $prefs, $mail);
        $service->dispatch(7, 'post.approved', [
            'post_id' => 1, 'post_title' => 'T', 'reviewer_username' => 'r',
        ]);
    });

    test('unknown type writes in-app row but never emails', function () {
        $notif = Mockery::mock(NotificationModel::class);
        $notif->shouldReceive('create')->once()->andReturn(true);

        $mail = Mockery::mock(MailService::class);
        $mail->shouldReceive('send')->never();

        $service = makeNotificationService($notif, null, null, $mail);
        $service->dispatch(7, 'totally.new.event', ['arbitrary' => 'payload']);
    });
});
