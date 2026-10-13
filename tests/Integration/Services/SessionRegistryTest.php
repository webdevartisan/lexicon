<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Services\Sessions\SessionRevoker;
use Framework\Session;
use Tests\Factories\UserFactory;
use Tests\Helpers\SessionTestHelper;

/**
 * The user_sessions rows decide who is signed in where, so these pin down
 * which rows each list and each revoke may touch.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->registry = SessionTestHelper::registry($this->db);
    $this->userId = UserFactory::new($this->users)->create();
    $this->otherId = UserFactory::new($this->users)->create();
    $this->adminId = UserFactory::new($this->users)->admin()->create();

    $this->revokerAs = function (string $currentHash): SessionRevoker {
        $session = Mockery::mock(Session::class);
        $session->shouldReceive('hashedId')->andReturn($currentHash);
        $session->shouldReceive('clientIp')->andReturn(null);

        return SessionTestHelper::revoker($this->db, $session);
    };
});

afterEach(function () {
    Mockery::close();
});

test('expiry is the earlier of the idle and absolute deadlines', function () {
    $hash = SessionTestHelper::signedInRow($this->db, $this->userId, [
        'idle_seconds' => 3600,
        'absolute_expires_at' => time() + 600,
    ]);

    $row = $this->registry->find($hash);

    expect((int) $row['expires_at'])->toBe((int) $row['absolute_expires_at']);
});

test('the owner list leaves out expired rows and support sessions', function () {
    $own = SessionTestHelper::signedInRow($this->db, $this->userId);
    SessionTestHelper::signedInRow($this->db, $this->userId, ['impersonated_by' => $this->adminId]);
    $expired = SessionTestHelper::signedInRow($this->db, $this->userId);
    $this->db->execute('UPDATE user_sessions SET expires_at = ? WHERE id = ?', [time() - 1, $expired]);

    $ownerView = array_column($this->registry->forUser($this->userId, false, time()), 'id');
    $adminView = $this->registry->forUser($this->userId, true, time());

    expect($ownerView)->toBe([$own])
        ->and($adminView)->toHaveCount(2)
        ->and(array_column($this->registry->impersonatedBy($this->adminId, time()), 'user_id'))->toEqual([$this->userId]);
});

test('rekey moves a row to the new id and keeps what it recorded', function () {
    $old = SessionTestHelper::signedInRow($this->db, $this->userId, ['city' => 'Patras']);
    $new = hash('sha256', 'regenerated');

    $this->registry->rekey($old, $new);

    expect($this->registry->find($old))->toBeNull()
        ->and((int) $this->registry->find($new)['user_id'])->toBe($this->userId);
});

test('prune removes only rows whose time is up, and reports the oldest one first', function () {
    $live = SessionTestHelper::signedInRow($this->db, $this->userId);
    $stale = SessionTestHelper::signedInRow($this->db, $this->userId);
    $now = time();
    $this->db->execute('UPDATE user_sessions SET expires_at = ? WHERE id = ?', [$now - 9000, $stale]);

    expect($this->registry->oldestExpired($now))->toBe($now - 9000);
    expect($this->registry->prune($now))->toBe(1)
        ->and($this->registry->find($live))->not->toBeNull()
        ->and($this->registry->oldestExpired($now))->toBeNull();
});

test('signing out the other devices keeps this one, support sessions and other people', function () {
    $current = SessionTestHelper::signedInRow($this->db, $this->userId);
    $phone = SessionTestHelper::signedInRow($this->db, $this->userId);
    $support = SessionTestHelper::signedInRow($this->db, $this->userId, ['impersonated_by' => $this->adminId]);
    $someoneElse = SessionTestHelper::signedInRow($this->db, $this->otherId);

    $ended = ($this->revokerAs)($current)->endOthers($this->userId, 'test');

    expect($ended)->toBe(1)
        ->and($this->registry->find($phone))->toBeNull()
        ->and($this->registry->find($current))->not->toBeNull()
        ->and($this->registry->find($support))->not->toBeNull()
        ->and($this->registry->find($someoneElse))->not->toBeNull();
});

test('a session that belongs to someone else cannot be ended by hash', function () {
    $theirs = SessionTestHelper::signedInRow($this->db, $this->otherId);

    expect(($this->revokerAs)('')->endOne($this->userId, $theirs, 'test'))->toBeFalse()
        ->and($this->registry->find($theirs))->not->toBeNull();
});

test('ending sessions is written to the activity log with the count, ending none is not', function () {
    SessionTestHelper::signedInRow($this->db, $this->userId);
    SessionTestHelper::signedInRow($this->db, $this->userId);

    $revoker = ($this->revokerAs)('');

    expect($revoker->endAll($this->userId, 'suspended'))->toBe(2)
        ->and($revoker->endAll($this->userId, 'suspended'))->toBe(0);

    $logged = $this->db->query(
        "SELECT details FROM activity_log WHERE action = 'user.sessions_ended' AND resource_id = ?",
        [$this->userId]
    )->fetchAll(PDO::FETCH_COLUMN);

    expect($logged)->toHaveCount(1)
        ->and(json_decode($logged[0], true))->toMatchArray(['reason' => 'suspended', 'count' => 2]);
});

test('deleting the account takes its sessions with it', function () {
    $hash = SessionTestHelper::signedInRow($this->db, $this->userId);

    $this->db->execute('DELETE FROM users WHERE id = ?', [$this->userId]);

    expect($this->registry->find($hash))->toBeNull();
});
