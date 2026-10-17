<?php

declare(strict_types=1);

use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\InvitationService;
use App\Services\LocaleRegistry;
use App\Services\MailQueueService;
use App\Services\NotificationService;
use App\Services\RecipientLocale;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for InvitationService.
 *
 * Exercises the invite lifecycle against a real database with a mocked
 * mail queue (nothing is actually enqueued for delivery).
 */
beforeEach(function () {
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->inviteModel = new BlogInvitationModel($this->db);
    $this->notifModel = new NotificationModel($this->db);

    // NotificationService wraps NotificationModel — wire with real DB models so
    // dispatch() writes rows that notifModel->findForUser() can read back.
    $notifMailQueue = Mockery::mock(MailQueueService::class);
    $notifMailQueue->shouldReceive('enqueue')->andReturn(1)->byDefault();

    $notificationService = new NotificationService(
        $this->notifModel,
        $this->userModel,
        new UserPreferencesModel($this->db),
        $notifMailQueue,
        new RecipientLocale($this->db, LocaleRegistry::instance()),
    );

    $this->mailQueue = Mockery::mock(MailQueueService::class);
    $this->mailQueue->shouldReceive('enqueue')->andReturn(1)->byDefault();

    $this->service = new InvitationService(
        $this->inviteModel,
        $this->blogModel,
        $this->userModel,
        $notificationService,
        $this->mailQueue,
        new UserPreferencesModel($this->db),
        new RecipientLocale($this->db, LocaleRegistry::instance())
    );

    $this->ownerId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->create($this->ownerId);
});

afterEach(fn () => Mockery::close());

test('invite creates a pending invitation', function () {
    $this->service->invite($this->blogId, 'new@example.com', 'author', $this->ownerId, '127.0.0.1');

    $pending = $this->inviteModel->getPendingForBlog($this->blogId);
    expect($pending)->toHaveCount(1)
        ->and($pending[0]['email'])->toBe('new@example.com')
        ->and($pending[0]['role'])->toBe('author');
});

test('invite with an invalid role throws', function () {
    expect(fn () => $this->service->invite(
        $this->blogId, 'x@example.com', 'viewer', $this->ownerId, '127.0.0.1'
    ))->toThrow(InvalidArgumentException::class);
});

test('invite notifies an existing user in-app', function () {
    $inviteeId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => 'member@example.com'])
        ->create();

    $this->service->invite($this->blogId, 'member@example.com', 'editor', $this->ownerId, '127.0.0.1');

    $notifications = $this->notifModel->findForUser($inviteeId);
    expect($notifications)->toHaveCount(1)
        ->and($notifications[0]['type'])->toBe('blog.invite');
});

test('accept adds the user to blog_users and consumes the invite', function () {
    $inviteeId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => 'member@example.com'])
        ->create();

    $raw = 'raw-accept-token';
    $hash = hash('sha256', $raw);
    $this->inviteModel->create(
        $this->blogId, 'member@example.com', 'contributor',
        $hash, $this->ownerId, date('Y-m-d H:i:s', strtotime('+7 days'))
    );

    $this->service->accept($raw, $inviteeId, '127.0.0.1');

    $blog = $this->blogModel->getBlog($this->blogId);
    expect($blog->roleForUser($inviteeId))->toBe('contributor');
    // Invite consumed.
    expect($this->inviteModel->findValidByToken($hash))->toBeFalse();
});

test('accept refuses an account the invitation was not sent to', function () {
    $otherId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => 'someone-else@example.com'])
        ->create();

    $raw = 'raw-wrong-account-token';
    $hash = hash('sha256', $raw);
    $this->inviteModel->create(
        $this->blogId, 'member@example.com', 'author',
        $hash, $this->ownerId, date('Y-m-d H:i:s', strtotime('+7 days'))
    );

    expect(fn () => $this->service->accept($raw, $otherId, '127.0.0.1'))->toThrow(RuntimeException::class);

    $blog = $this->blogModel->getBlog($this->blogId);
    expect($blog->roleForUser($otherId))->toBeNull()
        ->and($this->inviteModel->findValidByToken($hash))->not->toBeFalse();
});

test('accept matches the invited address case-insensitively', function () {
    $inviteeId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => 'Mixed.Case@example.com'])
        ->create();

    $raw = 'raw-case-token';
    $this->inviteModel->create(
        $this->blogId, 'mixed.case@example.com', 'author',
        hash('sha256', $raw), $this->ownerId, date('Y-m-d H:i:s', strtotime('+7 days'))
    );

    $this->service->accept($raw, $inviteeId, '127.0.0.1');

    expect($this->blogModel->getBlog($this->blogId)->roleForUser($inviteeId))->toBe('author');
});

test('accept writes one audit entry', function () {
    $inviteeId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => 'audited@example.com'])
        ->create();

    $raw = 'raw-audit-token';
    $this->inviteModel->create(
        $this->blogId, 'audited@example.com', 'author',
        hash('sha256', $raw), $this->ownerId, date('Y-m-d H:i:s', strtotime('+7 days'))
    );

    $this->service->accept($raw, $inviteeId, '127.0.0.1');

    $rows = $this->db->query(
        "SELECT COUNT(*) FROM activity_log WHERE user_id = ? AND action = 'blog.invite_accepted'",
        [$inviteeId]
    )->fetchColumn();
    expect((int) $rows)->toBe(1);
});

test('a consumed invite cannot be answered again', function () {
    $inviteeId = UserFactory::new($this->userModel)
        ->withAttributes(['email' => 'twice@example.com'])
        ->create();

    $raw = 'raw-twice-token';
    $hash = hash('sha256', $raw);
    $this->inviteModel->create(
        $this->blogId, 'twice@example.com', 'author',
        $hash, $this->ownerId, date('Y-m-d H:i:s', strtotime('+7 days'))
    );
    $invite = $this->inviteModel->findValidByToken($hash);

    $this->service->accept($raw, $inviteeId, '127.0.0.1');

    // What a racing second click hits once its read is stale: the claim refuses both answers.
    expect($this->inviteModel->markAccepted((int) $invite['id']))->toBeFalse()
        ->and($this->inviteModel->markDeclined((int) $invite['id']))->toBeFalse()
        ->and(fn () => $this->service->accept($raw, $inviteeId, '127.0.0.1'))->toThrow(RuntimeException::class);
});

test('accept throws for an expired token', function () {
    $raw = 'expired-accept-token';
    $hash = hash('sha256', $raw);
    $this->inviteModel->create(
        $this->blogId, 'x@example.com', 'author',
        $hash, $this->ownerId, date('Y-m-d H:i:s', strtotime('-1 day'))
    );

    expect(fn () => $this->service->accept($raw, 999, '127.0.0.1'))->toThrow(RuntimeException::class);
});

test('decline notifies the blog owner and consumes the invite', function () {
    $raw = 'raw-decline-token';
    $hash = hash('sha256', $raw);
    $this->inviteModel->create(
        $this->blogId, 'declines@example.com', 'author',
        $hash, $this->ownerId, date('Y-m-d H:i:s', strtotime('+7 days'))
    );

    $this->service->decline($raw, 0, '127.0.0.1');

    $ownerNotifications = $this->notifModel->findForUser($this->ownerId);
    expect($ownerNotifications)->toHaveCount(1)
        ->and($ownerNotifications[0]['type'])->toBe('blog.invite_declined');
    expect($this->inviteModel->findValidByToken($hash))->toBeFalse();
});

test('cancel removes a pending invite', function () {
    $this->service->invite($this->blogId, 'cancel@example.com', 'author', $this->ownerId, '127.0.0.1');
    $this->service->cancel($this->blogId, 'cancel@example.com', $this->ownerId, '127.0.0.1');

    expect($this->inviteModel->getPendingForBlog($this->blogId))->toHaveCount(0);
});
