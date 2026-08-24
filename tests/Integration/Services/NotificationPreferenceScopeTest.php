<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\UserModel;
use App\Services\NotificationPreferenceScope;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * The scope decides which notification toggles a user is shown, gated on the
 * blog permissions they actually hold. These tests pin each capability tier to
 * the exact key set it should see, so a permission or audience change that would
 * quietly hide (or reveal) a toggle fails loudly here.
 */
beforeEach(function () {
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->scope = new NotificationPreferenceScope($this->blogModel);

    // A separate account to own the blogs our collaborators are attached to.
    $this->hostId = UserFactory::new($this->userModel)->create();

    // Attach $userId to $this->blogId as a collaborator with the given role,
    // straight to SQL so the editorial-workflow gate on addUserToBlog stays out
    // of the way and reviewer memberships can be created unconditionally.
    $this->makeCollaborator = function (int $userId, string $role) {
        $this->db->query(
            'INSERT INTO blog_users (blog_id, user_id, role, assigned_by, assigned_at, is_active)
             VALUES (?, ?, ?, ?, NOW(), 1)',
            [$this->blogId, $userId, $role, $this->hostId]
        );
    };

    $this->blogId = BlogFactory::new($this->blogModel)->create($this->hostId);
});

test('a reader sees only the reply toggle', function () {
    $userId = UserFactory::new($this->userModel)->create();

    expect($this->scope->applicableKeys($userId))
        ->toBe(['notify_comment_replies']);
});

test('a blog owner sees author, review, moderation, and firehose toggles but not role changes', function () {
    $ownerId = UserFactory::new($this->userModel)->create();
    BlogFactory::new($this->blogModel)->create($ownerId);

    expect($this->scope->applicableKeys($ownerId))->toBe([
        'notify_comment_replies',
        'notify_comments_authored',
        'notify_comments_moderation',
        'notify_comments_blog',
        'notify_post_status',
        'notify_review_requests',
        'notify_invites',
    ]);
});

test('an author collaborator sees post and comment toggles plus role changes', function () {
    $userId = UserFactory::new($this->userModel)->create();
    ($this->makeCollaborator)($userId, 'author');

    expect($this->scope->applicableKeys($userId))->toBe([
        'notify_comment_replies',
        'notify_comments_authored',
        'notify_post_status',
        'notify_role_changes',
    ]);
});

test('a reviewer collaborator sees review requests but not authoring toggles', function () {
    $userId = UserFactory::new($this->userModel)->create();
    ($this->makeCollaborator)($userId, 'reviewer');

    expect($this->scope->applicableKeys($userId))->toBe([
        'notify_comment_replies',
        'notify_review_requests',
        'notify_role_changes',
    ]);
});

test('an editor collaborator sees moderation but not the owner-only firehose or invites', function () {
    $userId = UserFactory::new($this->userModel)->create();
    ($this->makeCollaborator)($userId, 'editor');

    expect($this->scope->applicableKeys($userId))->toBe([
        'notify_comment_replies',
        'notify_comments_authored',
        'notify_comments_moderation',
        'notify_post_status',
        'notify_review_requests',
        'notify_role_changes',
    ]);
});
