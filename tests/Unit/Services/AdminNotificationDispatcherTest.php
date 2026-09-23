<?php

declare(strict_types=1);

use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Services\AdminNotificationDispatcher;

afterEach(function () {
    Mockery::close();
});

test('fans the event out to every recipient holding the permission', function () {
    $notifications = Mockery::mock(NotificationModel::class);
    $notifications->shouldReceive('existsRecentAdminNotification')
        ->with('admin.mail_queue_failures', 60, null)
        ->andReturn(false);

    $users = Mockery::mock(UserModel::class);
    $users->shouldReceive('findAllWithPermission')
        ->with('manage_mail_queue')
        ->andReturn([['id' => 1, 'handle' => 'root'], ['id' => 2, 'handle' => 'ops']]);

    $notifications->shouldReceive('create')->once()
        ->with(1, 'admin.mail_queue_failures', ['failed_count' => 7], 'admin')->andReturn(true);
    $notifications->shouldReceive('create')->once()
        ->with(2, 'admin.mail_queue_failures', ['failed_count' => 7], 'admin')->andReturn(true);

    $dispatcher = new AdminNotificationDispatcher($notifications, $users);
    $result = $dispatcher->dispatch('admin.mail_queue_failures', 'manage_mail_queue', ['failed_count' => 7]);

    expect($result)->toBeTrue();
});

test('skips writing anything when the cooldown already covers this type', function () {
    $notifications = Mockery::mock(NotificationModel::class);
    $notifications->shouldReceive('existsRecentAdminNotification')->andReturn(true);
    $notifications->shouldReceive('create')->never();

    $users = Mockery::mock(UserModel::class);
    $users->shouldReceive('findAllWithPermission')->never();

    $dispatcher = new AdminNotificationDispatcher($notifications, $users);
    $result = $dispatcher->dispatch('admin.scheduler_stalled', 'manage_scheduled_tasks', []);

    expect($result)->toBeFalse();
});

test('skips writing anything when nobody holds the permission', function () {
    $notifications = Mockery::mock(NotificationModel::class);
    $notifications->shouldReceive('existsRecentAdminNotification')->andReturn(false);
    $notifications->shouldReceive('create')->never();

    $users = Mockery::mock(UserModel::class);
    $users->shouldReceive('findAllWithPermission')->andReturn([]);

    $dispatcher = new AdminNotificationDispatcher($notifications, $users);
    $result = $dispatcher->dispatch('admin.report_threshold', 'handle_reports', ['case_id' => 9]);

    expect($result)->toBeFalse();
});

test('stamps the dedupe key onto the payload of every recipient row', function () {
    $notifications = Mockery::mock(NotificationModel::class);
    $notifications->shouldReceive('existsRecentAdminNotification')
        ->with('admin.report_threshold', 60, '42')
        ->andReturn(false);

    $users = Mockery::mock(UserModel::class);
    $users->shouldReceive('findAllWithPermission')->andReturn([['id' => 5, 'handle' => 'mod']]);

    $notifications->shouldReceive('create')->once()
        ->with(5, 'admin.report_threshold', ['case_id' => 42, 'dedupe_key' => '42'], 'admin')
        ->andReturn(true);

    $dispatcher = new AdminNotificationDispatcher($notifications, $users);
    $result = $dispatcher->dispatch('admin.report_threshold', 'handle_reports', ['case_id' => 42], 60, '42');

    expect($result)->toBeTrue();
});

test('narrows the cooldown check to the given dedupe key instead of the type as a whole', function () {
    $notifications = Mockery::mock(NotificationModel::class);
    $notifications->shouldReceive('existsRecentAdminNotification')
        ->with('admin.report_threshold', 30, '7')
        ->once()
        ->andReturn(false);

    $users = Mockery::mock(UserModel::class);
    $users->shouldReceive('findAllWithPermission')->andReturn([]);

    $dispatcher = new AdminNotificationDispatcher($notifications, $users);
    $dispatcher->dispatch('admin.report_threshold', 'handle_reports', ['case_id' => 7], 30, '7');

    // Real assertion is the ->with() constraint above, verified by Mockery::close().
    expect(true)->toBeTrue();
});
