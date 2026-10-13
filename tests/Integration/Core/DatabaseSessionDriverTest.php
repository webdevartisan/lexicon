<?php

declare(strict_types=1);

use App\Models\UserModel;
use Framework\Database;
use Framework\Session\Drivers\DatabaseSessionDriver;
use Framework\Session\SessionRegistry;
use Tests\Factories\UserFactory;
use Tests\Helpers\SessionTestHelper;

/**
 * The database driver against real MySQL: what it stores, when it refuses a
 * session, and that two requests on one session take turns.
 */
beforeEach(function () {
    $this->makeDriver = fn (?Database $db = null, int $lockSeconds = 5, int $interval = 60) => new DatabaseSessionDriver(
        $db ?? $this->db,
        'user_sessions',
        $lockSeconds,
        7200,
        $interval
    );
    $this->registry = SessionTestHelper::registry($this->db);
    $this->id = bin2hex(random_bytes(16));
    $this->hash = SessionRegistry::hash($this->id);
});

test('a new session is stored under its hash with the guest limits and no address', function () {
    $driver = ($this->makeDriver)();
    $driver->setClientIp('198.51.100.7');

    expect($driver->validateId($this->id))->toBeFalse();
    $driver->write($this->id, 'locale|s:2:"en";');
    $driver->close();

    $row = $this->db->query('SELECT * FROM user_sessions WHERE id = ?', [$this->hash])->fetch(PDO::FETCH_ASSOC);

    expect($row)->not->toBeFalse()
        ->and((int) $row['idle_seconds'])->toBe(7200)
        ->and($row['user_id'])->toBeNull()
        ->and($row['ip_address'])->toBeNull()
        ->and((int) $this->db->query('SELECT COUNT(*) FROM user_sessions WHERE id = ?', [$this->id])->fetchColumn())->toBe(0);
});

test('binary session data comes back byte for byte', function () {
    $data = 'blob|s:256:"'.implode('', array_map('chr', range(0, 255))).'";';

    $writer = ($this->makeDriver)();
    $writer->write($this->id, $data);
    $writer->close();

    $reader = ($this->makeDriver)();
    expect($reader->validateId($this->id))->toBeTrue()
        ->and($reader->read($this->id))->toBe($data);
    $reader->close();
});

test('an expired session is refused and its row removed', function () {
    $writer = ($this->makeDriver)();
    $writer->write($this->id, 'a|i:1;');
    $writer->close();

    $this->db->execute('UPDATE user_sessions SET expires_at = ? WHERE id = ?', [time() - 1, $this->hash]);

    $reader = ($this->makeDriver)();
    expect($reader->validateId($this->id))->toBeFalse()
        ->and($reader->read($this->id))->toBe('');
    $reader->close();

    expect($this->registry->find($this->hash))->toBeNull();
});

test('a session revoked while its request runs is not written back', function () {
    $userId = UserFactory::new(new UserModel($this->db))->create();
    $this->registry->open($this->hash, ['user_id' => $userId, 'idle_seconds' => 3600], time());

    $driver = ($this->makeDriver)();
    expect($driver->validateId($this->id))->toBeTrue();
    $driver->read($this->id);

    // Signed out from another device mid-request.
    $this->registry->deleteForUser($userId);

    $driver->write($this->id, 'user_id|i:'.$userId.';');
    $driver->close();

    expect($this->registry->find($this->hash))->toBeNull();
});

test('a signed-in row keeps the latest address, a guest row none', function () {
    $userId = UserFactory::new(new UserModel($this->db))->create();
    $this->registry->open($this->hash, ['user_id' => $userId, 'idle_seconds' => 3600, 'ip_address' => '203.0.113.1'], time());

    $driver = ($this->makeDriver)();
    $driver->setClientIp('198.51.100.7');
    $driver->validateId($this->id);
    $driver->write($this->id, 'x|i:1;');
    $driver->close();

    $ip = $this->db->query('SELECT ip_address FROM user_sessions WHERE id = ?', [$this->hash])->fetchColumn();
    expect($ip)->toBe('198.51.100.7');
});

test('a second request on the same session waits for the first, then gives up', function () {
    $writer = ($this->makeDriver)();
    $writer->write($this->id, 'a|i:1;');
    $writer->close();

    $holder = ($this->makeDriver)();
    expect($holder->validateId($this->id))->toBeTrue();

    $other = new Database(require ROOT_PATH.'/tests/config/database.php');
    $waiting = ($this->makeDriver)($other, 1);

    expect(fn () => $waiting->validateId($this->id))->toThrow(RuntimeException::class);

    $holder->close();

    $afterRelease = ($this->makeDriver)($other, 1);
    expect($afterRelease->validateId($this->id))->toBeTrue();
    $afterRelease->close();
});

test('activity is recorded at most once per interval', function () {
    $writer = ($this->makeDriver)();
    $writer->write($this->id, 'a|i:1;');
    $writer->close();

    $recent = time() - 10;
    $this->db->execute('UPDATE user_sessions SET last_activity = ? WHERE id = ?', [$recent, $this->hash]);

    $quiet = ($this->makeDriver)();
    $quiet->validateId($this->id);
    $quiet->updateTimestamp($this->id, 'a|i:1;');
    $quiet->close();

    expect((int) $this->registry->find($this->hash)['last_activity'])->toBe($recent);

    $this->db->execute('UPDATE user_sessions SET last_activity = ? WHERE id = ?', [time() - 120, $this->hash]);

    $busy = ($this->makeDriver)();
    $busy->validateId($this->id);
    $busy->updateTimestamp($this->id, 'a|i:1;');
    $busy->close();

    expect((int) $this->registry->find($this->hash)['last_activity'])->toBeGreaterThanOrEqual(time() - 1);
});

test('destroy deletes the row and gc leaves pruning to the task', function () {
    $driver = ($this->makeDriver)();
    $driver->write($this->id, 'a|i:1;');
    $driver->destroy($this->id);
    $driver->close();

    expect($this->registry->find($this->hash))->toBeNull()
        ->and($driver->gc(0))->toBe(0);
});
