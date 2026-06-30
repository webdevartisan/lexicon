<?php

declare(strict_types=1);

use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;

/**
 * Integration tests for BlogInvitationModel.
 *
 * Exercises invite CRUD against a real database: creation, token lookup,
 * accept/decline state transitions, cancellation, and expiry pruning.
 */
beforeEach(function () {
    $this->blogModel = new BlogModel($this->db);
    $this->userModel = new UserModel($this->db);
    $this->model = new BlogInvitationModel($this->db);

    $this->ownerId = UserFactory::new($this->userModel)->create();
    $this->blogId = BlogFactory::new($this->blogModel)->create($this->ownerId);
});

/** Helper: future/past expiry timestamps. */
function inviteExpiry(string $modifier): string
{
    return date('Y-m-d H:i:s', strtotime($modifier));
}

test('create stores invite and findValidByToken retrieves it', function () {
    $hash = hash('sha256', bin2hex(random_bytes(8)));

    $result = $this->model->create(
        $this->blogId, 'invited@example.com', 'author',
        $hash, $this->ownerId, inviteExpiry('+7 days')
    );

    expect($result)->toBeTrue();

    $invite = $this->model->findValidByToken($hash);
    expect($invite)->toBeArray()
        ->and($invite['email'])->toBe('invited@example.com')
        ->and($invite['role'])->toBe('author')
        ->and((int) $invite['blog_id'])->toBe($this->blogId);
});

test('findValidByToken returns false for expired token', function () {
    $hash = hash('sha256', 'expired-token');
    $this->model->create(
        $this->blogId, 'old@example.com', 'author',
        $hash, $this->ownerId, inviteExpiry('-1 day')
    );

    expect($this->model->findValidByToken($hash))->toBeFalse();
});

test('markAccepted consumes the invite', function () {
    $hash = hash('sha256', bin2hex(random_bytes(8)));
    $this->model->create(
        $this->blogId, 'a@example.com', 'author',
        $hash, $this->ownerId, inviteExpiry('+7 days')
    );
    $invite = $this->model->findValidByToken($hash);

    expect($this->model->markAccepted((int) $invite['id']))->toBeTrue();
    // Once accepted it is no longer "valid"
    expect($this->model->findValidByToken($hash))->toBeFalse();
});

test('markDeclined consumes the invite', function () {
    $hash = hash('sha256', bin2hex(random_bytes(8)));
    $this->model->create(
        $this->blogId, 'b@example.com', 'contributor',
        $hash, $this->ownerId, inviteExpiry('+7 days')
    );
    $invite = $this->model->findValidByToken($hash);

    expect($this->model->markDeclined((int) $invite['id']))->toBeTrue();
    expect($this->model->findValidByToken($hash))->toBeFalse();
});

test('create cancels a previous pending invite for the same email+blog', function () {
    $firstHash = hash('sha256', 'first');
    $secondHash = hash('sha256', 'second');

    $this->model->create($this->blogId, 'dup@example.com', 'author',
        $firstHash, $this->ownerId, inviteExpiry('+7 days'));
    $this->model->create($this->blogId, 'dup@example.com', 'editor',
        $secondHash, $this->ownerId, inviteExpiry('+7 days'));

    // The first is gone; only the second (with the new role) remains valid.
    expect($this->model->findValidByToken($firstHash))->toBeFalse();
    $second = $this->model->findValidByToken($secondHash);
    expect($second)->toBeArray()->and($second['role'])->toBe('editor');
});

test('cancelPendingForEmail removes pending invite', function () {
    $hash = hash('sha256', bin2hex(random_bytes(8)));
    $this->model->create($this->blogId, 'c@example.com', 'editor',
        $hash, $this->ownerId, inviteExpiry('+7 days'));

    expect($this->model->cancelPendingForEmail($this->blogId, 'c@example.com'))->toBeTrue();
    expect($this->model->findValidByToken($hash))->toBeFalse();
});

test('deleteExpired removes only expired unconsumed invites', function () {
    $expiredHash = hash('sha256', 'exp');
    $validHash = hash('sha256', 'val');

    $this->model->create($this->blogId, 'exp@example.com', 'author',
        $expiredHash, $this->ownerId, inviteExpiry('-1 hour'));
    $this->model->create($this->blogId, 'val@example.com', 'author',
        $validHash, $this->ownerId, inviteExpiry('+7 days'));

    $deleted = $this->model->deleteExpired();

    expect($deleted)->toBe(1);
    expect($this->model->findValidByToken($validHash))->toBeArray();
});

test('getPendingForBlog lists active invites newest first', function () {
    $this->model->create($this->blogId, 'p1@example.com', 'author',
        hash('sha256', 'p1'), $this->ownerId, inviteExpiry('+7 days'));
    $this->model->create($this->blogId, 'p2@example.com', 'reviewer',
        hash('sha256', 'p2'), $this->ownerId, inviteExpiry('+7 days'));

    $pending = $this->model->getPendingForBlog($this->blogId);
    expect($pending)->toHaveCount(2);
});
