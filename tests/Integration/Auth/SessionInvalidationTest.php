<?php

declare(strict_types=1);

use App\Auth;
use App\Exceptions\AccountSuspendedException;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Services\PublicCacheInvalidator;
use App\Services\UserSuspensionService;
use Framework\Session;
use Tests\Factories\UserFactory;

/**
 * Ending an account's other sessions after an administrator resets its
 * password, and lifting an expired suspension at the moment of sign-in.
 *
 * A fresh Auth per step, because Auth caches the user for the request and a
 * real follow-up request would start without that cache.
 */
beforeEach(function () {
    $_SESSION = [];

    $this->users = new UserModel($this->db);
    $this->session = new Session();
    $this->suspensions = new UserSuspensionService(
        $this->db,
        Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing(),
        new CommentModel($this->db),
        new BlogModel($this->db),
    );

    $this->email = faker()->unique()->safeEmail();
    $this->userId = UserFactory::new($this->users)
        ->withAttributes(['email' => $this->email, 'password' => password_hash('password123', PASSWORD_DEFAULT)])
        ->create();
    $this->adminId = UserFactory::new($this->users)->admin()->create();

    $this->auth = fn (): Auth => new Auth($this->session, $this->users, new UserProfileModel($this->db), $this->suspensions);
});

afterEach(function () {
    $_SESSION = [];
    Mockery::close();
});

it('keeps a session whose epoch still matches', function () {
    expect(($this->auth)()->login($this->email, 'password123'))->toBeTrue()
        ->and(($this->auth)()->user()['id'] ?? null)->toBe($this->userId);
});

it('drops a session signed in before the epoch was bumped', function () {
    ($this->auth)()->login($this->email, 'password123');

    $this->users->bumpSessionEpoch($this->userId);

    expect(($this->auth)()->user())->toBeNull()
        ->and($this->session->get('user_id'))->toBeNull();
});

it('lets a new sign-in after the bump stay signed in', function () {
    ($this->auth)()->login($this->email, 'password123');
    $this->users->bumpSessionEpoch($this->userId);
    ($this->auth)()->user();

    expect(($this->auth)()->login($this->email, 'password123'))->toBeTrue()
        ->and(($this->auth)()->user()['id'] ?? null)->toBe($this->userId);
});

it('does not sign out sessions from before the column existed', function () {
    ($this->auth)()->login($this->email, 'password123');
    $this->session->remove('session_epoch');

    expect(($this->auth)()->user()['id'] ?? null)->toBe($this->userId);
});

it('refuses sign-in while a suspension is in force', function () {
    $this->suspensions->suspend($this->userId, '2030-01-01 00:00:00', 'Spam', $this->adminId);

    expect(fn () => ($this->auth)()->login($this->email, 'password123'))->toThrow(AccountSuspendedException::class);
});

it('lifts an expired suspension at sign-in without waiting for the scheduled task', function () {
    $this->suspensions->suspend($this->userId, '2030-01-01 00:00:00', 'Spam', $this->adminId);
    $this->db->execute('UPDATE users SET suspended_until = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$this->userId]);

    expect(($this->auth)()->login($this->email, 'password123'))->toBeTrue()
        ->and($this->suspensions->current($this->userId))->toBeNull();
});

it('never lifts a permanent suspension at sign-in', function () {
    $this->suspensions->suspend($this->userId, null, 'Spam', $this->adminId);

    expect(fn () => ($this->auth)()->login($this->email, 'password123'))->toThrow(AccountSuspendedException::class)
        ->and($this->suspensions->current($this->userId))->not->toBeNull();
});
