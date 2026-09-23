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

// ============================================================================
// SCOPE: the personal/content/admin inboxes are one table, kept apart by a
// scope filter rather than three tables, so the filter itself is what these
// tests are protecting.
// ============================================================================

test('create defaults to personal scope and rejects an unknown one', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.submitted', ['post_id' => 2], 'not-a-real-scope');

    $rows = $this->model->findForUser($this->userId);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['scope'])->toBe('personal')
        ->and($rows[1]['scope'])->toBe('personal');
});

test('findForUser, unreadCount, markAllRead, and deleteAllForUser each isolate by scope', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1], 'personal');
    $this->model->create($this->userId, 'post.submitted', ['post_id' => 2], 'content');
    $this->model->create($this->userId, 'admin.report_threshold', ['case_id' => 3], 'admin');

    expect($this->model->findForUser($this->userId, scope: 'content'))->toHaveCount(1)
        ->and($this->model->unreadCount($this->userId, 'content'))->toBe(1)
        ->and($this->model->unreadCount($this->userId))->toBe(3);

    $this->model->markAllRead($this->userId, 'content');
    expect($this->model->unreadCount($this->userId, 'content'))->toBe(0)
        ->and($this->model->unreadCount($this->userId, 'personal'))->toBe(1)
        ->and($this->model->unreadCount($this->userId, 'admin'))->toBe(1);

    $deleted = $this->model->deleteAllForUser($this->userId, 'admin');
    expect($deleted)->toBe(1)
        ->and($this->model->findForUser($this->userId))->toHaveCount(2);
});

test('markRead ignores an id from a different scope than the one the caller passes', function () {
    $this->model->create($this->userId, 'post.submitted', ['post_id' => 1], 'content');
    $id = (int) $this->model->findForUser($this->userId)[0]['id'];

    expect($this->model->markRead($id, $this->userId, 'personal'))->toBeFalse()
        ->and($this->model->unreadCount($this->userId, 'content'))->toBe(1)
        ->and($this->model->markRead($id, $this->userId, 'content'))->toBeTrue()
        ->and($this->model->unreadCount($this->userId, 'content'))->toBe(0);
});

test('deleteForUser ignores an id from a different scope than the one the caller passes', function () {
    $this->model->create($this->userId, 'admin.mail_queue_failures', ['failed_count' => 5], 'admin');
    $id = (int) $this->model->findForUser($this->userId)[0]['id'];

    expect($this->model->deleteForUser($id, $this->userId, 'content'))->toBeFalse()
        ->and($this->model->findForUser($this->userId))->toHaveCount(1)
        ->and($this->model->deleteForUser($id, $this->userId, 'admin'))->toBeTrue()
        ->and($this->model->findForUser($this->userId))->toHaveCount(0);
});

test('existsRecentAdminNotification finds a type inside the window and not outside it', function () {
    $this->model->create($this->userId, 'admin.scheduler_stalled', [], 'admin');

    expect($this->model->existsRecentAdminNotification('admin.scheduler_stalled', 60))->toBeTrue()
        ->and($this->model->existsRecentAdminNotification('admin.mail_queue_failures', 60))->toBeFalse();

    $this->db->execute(
        'UPDATE notifications SET created_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 MINUTE) WHERE user_id = ?',
        [$this->userId]
    );

    expect($this->model->existsRecentAdminNotification('admin.scheduler_stalled', 60))->toBeFalse();
});

test('existsRecentAdminNotification with a dedupe key only matches that same incident', function () {
    $this->model->create($this->userId, 'admin.report_threshold', ['case_id' => 1, 'dedupe_key' => '1'], 'admin');

    expect($this->model->existsRecentAdminNotification('admin.report_threshold', 60, '1'))->toBeTrue()
        ->and($this->model->existsRecentAdminNotification('admin.report_threshold', 60, '2'))->toBeFalse();
});

// ============================================================================
// SINGLE ROW AND THE UNREAD FILTER: what the open endpoint and the unread tab
// are built on.
// ============================================================================

test('findOneForUser returns the row, and only to its owner and its own inbox', function () {
    $otherUser = UserFactory::new($this->userModel)->create();
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5], 'content');
    $id = (int) $this->model->findForUser($this->userId)[0]['id'];

    expect($this->model->findOneForUser($id, $this->userId)['type'])->toBe('post.approved')
        ->and($this->model->findOneForUser($id, $this->userId, 'content')['id'])->toEqual($id)
        ->and($this->model->findOneForUser($id, $this->userId, 'personal'))->toBeNull()
        ->and($this->model->findOneForUser($id, $otherUser))->toBeNull()
        ->and($this->model->findOneForUser(999999, $this->userId))->toBeNull();
});

test('findPageForUser can return only what is unread, counted the same way', function () {
    $this->model->create($this->userId, 'blog.invite', ['blog_id' => 1]);
    $this->model->create($this->userId, 'post.approved', ['post_id' => 5]);
    $this->model->create($this->userId, 'post.published', ['post_id' => 6]);

    $first = (int) $this->model->findForUser($this->userId)[0]['id'];
    $this->model->markRead($first, $this->userId);

    $unread = $this->model->findPageForUser($this->userId, onlyUnread: true);
    $all = $this->model->findPageForUser($this->userId);

    expect($unread['total'])->toBe(2)
        ->and($unread['items'])->toHaveCount(2)
        ->and($all['total'])->toBe(3);
});

test('rows created in the same second still come back newest first', function () {
    foreach (range(1, 6) as $i) {
        $this->model->create($this->userId, 'post.approved', ['post_id' => $i]);
    }

    $newestIds = array_column($this->model->findForUser($this->userId, 3), 'id');
    $allIds = array_column($this->model->findForUser($this->userId), 'id');
    $expected = array_slice($allIds, 0, 3);

    // Same second for all six, so only the id tiebreaker keeps this stable.
    expect($newestIds)->toBe($expected)
        ->and((int) $newestIds[0])->toBeGreaterThan((int) $newestIds[2]);
});
