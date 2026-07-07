<?php

declare(strict_types=1);

use App\Models\NotificationModel;
use App\Models\UserModel;
use Tests\Factories\UserFactory;

/**
 * Integration tests for NotificationModel.
 *
 * Verifies create/read/markRead against a real database.
 */
beforeEach(function () {
    $this->userModel = new UserModel($this->db);
    $this->model = new NotificationModel($this->db);

    $this->userId = UserFactory::new($this->userModel)->create();
});

test('create stores a notification', function () {
    $result = $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1, 'role' => 'author']);
    expect($result)->toBeTrue();
});

test('findForUser returns notifications for the user', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);

    $notifications = $this->model->findForUser($this->userId);
    expect($notifications)->toHaveCount(2);
});

test('findForUser scopes to the given user', function () {
    $otherUser = UserFactory::new($this->userModel)->create();
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($otherUser, 'blog.invite', ['blog_id' => 2]);

    expect($this->model->findForUser($this->userId))->toHaveCount(1);
});

test('findForUser with onlyUnread excludes read notifications', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);

    $rows = $this->model->findForUser($this->userId);
    $this->model->markRead((int) $rows[0]['id'], $this->userId);

    expect($this->model->findForUser($this->userId, 20, onlyUnread: true))->toHaveCount(1)
        ->and($this->model->findForUser($this->userId))->toHaveCount(2);
});

test('markRead sets read_at and is owner-scoped', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $id = (int) $this->model->findForUser($this->userId)[0]['id'];

    expect($this->model->markRead($id, $this->userId))->toBeTrue();

    $updated = $this->model->findForUser($this->userId);
    expect($updated[0]['read_at'])->not->toBeNull();

    // A different user cannot mark it read again.
    $otherUser = UserFactory::new($this->userModel)->create();
    expect($this->model->markRead($id, $otherUser))->toBeFalse();
});

// ============================================================================
// EXTENDED METHODS: unreadCount, markAllRead, pruneStale, findPageForUser
// ============================================================================

test('unreadCount returns count of unread notifications', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);
    $this->model->create($this->userId, 'post.needs_changes', ['post_id' => 6]);

    expect($this->model->unreadCount($this->userId))->toBe(3);
});

test('unreadCount excludes read notifications', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);

    $rows = $this->model->findForUser($this->userId);
    $this->model->markRead((int) $rows[0]['id'], $this->userId);

    expect($this->model->unreadCount($this->userId))->toBe(1);
});

test('markAllRead sets read_at on every unread notification for the user', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);

    $affected = $this->model->markAllRead($this->userId);

    expect($affected)->toBe(2)
        ->and($this->model->unreadCount($this->userId))->toBe(0);
});

test('markAllRead does not affect other users notifications', function () {
    $otherId = UserFactory::new($this->userModel)->create();
    $this->model->create($otherId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);

    $this->model->markAllRead($this->userId);

    expect($this->model->unreadCount($otherId))->toBe(1);
});

test('pruneStale deletes read notifications older than 30 days', function () {
    $this->model->create($this->userId, 'post.approved', ['post_id' => 1]);
    $rows = $this->model->findForUser($this->userId);
    $id = (int) $rows[0]['id'];

    // Backdate to 31 days ago and mark read
    $this->db->execute(
        'UPDATE notifications SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY),
                                   read_at    = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 31 DAY)
         WHERE id = ?',
        [$id]
    );

    $result = $this->model->pruneStale();

    expect($result['read_pruned'])->toBe(1)
        ->and($this->model->findForUser($this->userId))->toHaveCount(0);
});

test('pruneStale deletes any notification older than 90 days even if unread', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $rows = $this->model->findForUser($this->userId);
    $id = (int) $rows[0]['id'];

    $this->db->execute(
        'UPDATE notifications SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 91 DAY) WHERE id = ?',
        [$id]
    );

    $result = $this->model->pruneStale();

    expect($result['old_pruned'])->toBe(1);
});

test('pruneStale keeps recent unread notifications', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);

    $result = $this->model->pruneStale();

    expect($result['read_pruned'])->toBe(0)
        ->and($result['old_pruned'])->toBe(0)
        ->and($this->model->unreadCount($this->userId))->toBe(1);
});

test('findPageForUser returns paginated rows + total', function () {
    foreach (range(1, 12) as $i) {
        $this->model->create($this->userId, 'post.approved', ['post_id' => $i]);
    }

    $page1 = $this->model->findPageForUser($this->userId, perPage: 5, page: 1);
    $page3 = $this->model->findPageForUser($this->userId, perPage: 5, page: 3);

    expect($page1['total'])->toBe(12)
        ->and($page1['items'])->toHaveCount(5)
        ->and($page3['items'])->toHaveCount(2);
});
