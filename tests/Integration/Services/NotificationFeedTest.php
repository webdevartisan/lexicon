<?php

declare(strict_types=1);

use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Services\BlogRoleLabels;
use App\Services\NotificationFeed;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * NotificationFeed fills in what a payload only names: the blog's current
 * name, and the actor's name and picture, in batched queries.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->feed = new NotificationFeed($this->blogs, new UserProfileModel($this->db), new BlogRoleLabels(new RoleModel($this->db)), new BlogInvitationModel($this->db));
});

function feedRow(string $type, string $scope, array $payload): array
{
    return ['id' => 1, 'type' => $type, 'scope' => $scope, 'data' => json_encode($payload), 'read_at' => null, 'created_at' => '2026-10-14 10:00:00'];
}

test('a content row names its blog by the blog\'s current name, not the stored one', function () {
    $owner = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new($this->blogs)->withAttributes(['blog_name' => 'Field Notes'])->create($owner);

    [$item] = $this->feed->present([feedRow('analytics.spike', 'content', ['blog_id' => $blogId, 'blog_name' => 'Old Name'])]);

    expect($item['source'])->toBe('Field Notes')
        ->and($item['sentence']['target'])->toBe('Field Notes');
});

test('a personal row carries no blog source, and a deleted blog leaves the fallback to the view', function () {
    [$personal, $orphan] = $this->feed->present([
        feedRow('comment.reply', 'personal', ['post_title' => 'x']),
        feedRow('comment.on_blog', 'content', ['blog_id' => 99999999]),
    ]);

    expect($personal['source'])->toBeNull()
        ->and($orphan['source'])->toBeNull()
        ->and($orphan['sentence']['targetFallback'])->toBe('notifications.aPost');
});

test('an actor\'s picture shows only when their profile is public', function () {
    $public = UserFactory::new($this->users)->create();
    $private = UserFactory::new($this->users)->create();
    $this->db->execute('INSERT INTO user_profiles (user_id, avatar_url, is_public) VALUES (?, ?, 1), (?, ?, 0)', [
        $public, '/uploads/a.png', $private, '/uploads/b.png',
    ]);
    $publicHandle = $this->users->findById($public)['handle'];
    $privateHandle = $this->users->findById($private)['handle'];

    [$shown, $hidden] = $this->feed->present([
        feedRow('collaborator.removed', 'personal', ['actor_handle' => $publicHandle]),
        feedRow('collaborator.removed', 'personal', ['actor_handle' => $privateHandle]),
    ]);

    expect($shown['actor']['avatar_url'])->toBe('/uploads/a.png')
        ->and($hidden['actor']['avatar_url'])->toBeNull()
        ->and($hidden['actor']['name'])->not->toBe('');
});

test('an actor known only by name keeps that name and gets no picture', function () {
    [$item] = $this->feed->present([feedRow('comment.reply', 'personal', ['commenter_name' => 'A guest'])]);

    expect($item['actor'])->toBe(['name' => 'A guest', 'avatar_url' => null])
        ->and($item['sentence']['actor'])->toBe('A guest');
});

test('an invitation offers Accept and Decline only while it can still be answered', function () {
    $owner = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new($this->blogs)->create($owner);
    $this->db->execute(
        'INSERT INTO blog_invitations (blog_id, email, role, token, invited_by, expires_at, accepted_at)
         VALUES (?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY), NULL),
                (?, ?, ?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY), UTC_TIMESTAMP())',
        [$blogId, 'a@example.test', 'author', hash('sha256', 'aa11'), $owner,
            $blogId, 'b@example.test', 'author', hash('sha256', 'bb22'), $owner]
    );

    [$open, $answered, $unknown] = $this->feed->present([
        feedRow('blog.invite', 'personal', ['blog_id' => $blogId, 'token' => 'aa11']),
        feedRow('blog.invite', 'personal', ['blog_id' => $blogId, 'token' => 'bb22']),
        feedRow('blog.invite', 'personal', ['blog_id' => $blogId, 'token' => 'cc33']),
    ]);

    expect($open['invite'])->toBe(['accept' => '/invite/aa11/accept', 'decline' => '/invite/aa11/decline'])
        ->and($answered['invite'])->toBeNull()
        ->and($unknown['invite'])->toBeNull()
        ->and($open)->not->toHaveKey('inviteToken');
});
