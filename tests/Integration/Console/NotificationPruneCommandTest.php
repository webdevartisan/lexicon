<?php

declare(strict_types=1);

use App\Console\Commands\NotificationPruneCommand;
use App\Models\NotificationModel;
use App\Models\UserModel;
use Tests\Factories\UserFactory;

/**
 * Integration tests for NotificationPruneCommand.
 *
 * Verifies that the prune command correctly deletes stale notifications
 * according to the retention policy: read >30 days, any >90 days.
 */
beforeEach(function () {
    // Integration beforeEach only provides $this->db — set up models here
    $this->userModel = new UserModel($this->db);
    $this->userId = UserFactory::new($this->userModel)->create();
    $this->notifs = new NotificationModel($this->db);
    $this->command = new NotificationPruneCommand($this->notifs);
});

test('handle returns 0 when nothing to prune', function () {
    expect($this->command->handle())->toBe(0);
});

test('handle deletes read notifications older than 30 days', function () {
    $this->notifs->create($this->userId, 'post.approved', ['post_id' => 1]);
    $row = $this->notifs->findForUser($this->userId)[0];

    $this->db->execute(
        'UPDATE notifications SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY),
                                   read_at    = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY)
         WHERE id = ?',
        [(int) $row['id']]
    );

    ob_start();
    $exit = $this->command->handle();
    ob_end_clean();

    expect($exit)->toBe(0)
        ->and($this->notifs->findForUser($this->userId))->toHaveCount(0);
});

test('handle deletes unread notifications older than 90 days', function () {
    $this->notifs->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $row = $this->notifs->findForUser($this->userId)[0];

    $this->db->execute(
        'UPDATE notifications SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 91 DAY) WHERE id = ?',
        [(int) $row['id']]
    );

    ob_start();
    $this->command->handle();
    ob_end_clean();

    expect($this->notifs->findForUser($this->userId))->toHaveCount(0);
});

test('handle preserves recent notifications', function () {
    $this->notifs->create($this->userId, 'post.approved', ['post_id' => 1]);

    ob_start();
    $this->command->handle();
    ob_end_clean();

    expect($this->notifs->findForUser($this->userId))->toHaveCount(1);
});
