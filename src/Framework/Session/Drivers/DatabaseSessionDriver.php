<?php

declare(strict_types=1);

namespace Framework\Session\Drivers;

use Framework\Database;
use Framework\Session\SessionDriver;
use Framework\Session\SessionRegistry;
use RuntimeException;

/**
 * Sessions in the user_sessions table, guests included.
 *
 * Each session holds a MySQL advisory lock (GET_LOCK) from the moment it is
 * read until it is closed, which serialises requests on one session the way
 * the file handler's flock does. A row lock inside a transaction would do the
 * same, but would hold a transaction open for the whole request on the
 * connection the application also uses.
 *
 * A row that existed when the request started is only ever UPDATEd, so a
 * session revoked from another device while this request was running stays
 * deleted instead of being written back.
 */
final class DatabaseSessionDriver implements SessionDriver
{
    /** Guests are not listed anywhere, so only a signed-in row keeps an address. */
    private const IP_UPDATE = 'IF(user_id IS NULL, NULL, COALESCE(?, ip_address))';

    /** @var array<string, array<string, mixed>|false> Rows read this request, by hash */
    private array $rows = [];

    /** @var array<string, true> Lock names this connection holds */
    private array $locks = [];

    private ?string $clientIp = null;

    public function __construct(
        private Database $db,
        private string $table,
        private int $lockSeconds,
        private int $guestIdleSeconds,
        private int $activityInterval,
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        foreach (array_keys($this->locks) as $lock) {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }

        $this->locks = [];

        return true;
    }

    public function create_sid(): string
    {
        $id = bin2hex(random_bytes(32));

        // Nobody else can hold an id minted just now, so there is nothing to lock or read.
        $this->rows[SessionRegistry::hash($id)] = false;

        return $id;
    }

    public function validateId(string $id): bool
    {
        $row = $this->load($id);

        if ($row === false) {
            $this->unlock($id);

            return false;
        }

        if ((int) $row['expires_at'] <= time()) {
            $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$row['id']]);
            $this->rows[$row['id']] = false;
            $this->unlock($id);

            return false;
        }

        return true;
    }

    public function read(string $id): string
    {
        $row = $this->load($id);

        if ($row === false || (int) $row['expires_at'] <= time() || $row['payload'] === null) {
            return '';
        }

        $data = base64_decode((string) $row['payload'], true);

        if ($data === false) {
            throw new RuntimeException('Stored session data is not valid base64.');
        }

        return $data;
    }

    public function write(string $id, string $data): bool
    {
        $hash = SessionRegistry::hash($id);
        $now = time();
        $payload = base64_encode($data);
        $expires = 'LEAST(last_activity + idle_seconds, COALESCE(absolute_expires_at, 4294967295))';
        $ip = self::IP_UPDATE;

        if (($this->rows[$hash] ?? false) !== false) {
            $this->db->execute(
                "UPDATE {$this->table}
                    SET payload = ?, last_activity = ?, ip_address = {$ip}, expires_at = {$expires}
                  WHERE id = ?",
                [$payload, $now, $this->clientIp, $hash]
            );

            return true;
        }

        // New this request. The row may already exist if the session signed in
        // during the request, in which case only the data and activity change.
        $this->db->execute(
            "INSERT INTO {$this->table} (id, payload, created_at, last_activity, idle_seconds, expires_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_activity = VALUES(last_activity), expires_at = {$expires}",
            [$hash, $payload, $now, $now, $this->guestIdleSeconds, $now + $this->guestIdleSeconds]
        );

        return true;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $hash = SessionRegistry::hash($id);
        $row = $this->rows[$hash] ?? false;

        if ($row === false) {
            return $this->write($id, $data);
        }

        if (time() - (int) $row['last_activity'] < $this->activityInterval) {
            return true;
        }

        $this->db->execute(
            "UPDATE {$this->table}
                SET last_activity = ?, ip_address = ".self::IP_UPDATE.',
                    expires_at = LEAST(? + idle_seconds, COALESCE(absolute_expires_at, 4294967295))
              WHERE id = ?',
            [time(), $this->clientIp, time(), $hash]
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $hash = SessionRegistry::hash($id);
        $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$hash]);
        $this->rows[$hash] = false;

        return true;
    }

    public function gc(int $max_lifetime): int
    {
        // Expired rows are removed by the session:prune task, not on page views.
        return 0;
    }

    public function storesRecords(): bool
    {
        return true;
    }

    public function record(string $id): ?array
    {
        $row = $this->rows[SessionRegistry::hash($id)] ?? false;

        return $row === false ? null : $row;
    }

    public function forgetHashes(array $hashes): void
    {
        // The rows are the data, and the registry already deleted them.
    }

    public function setClientIp(?string $ip): void
    {
        $this->clientIp = $ip;
    }

    /**
     * Lock the session, then read its row once.
     *
     * @return array<string, mixed>|false
     */
    private function load(string $id): array|false
    {
        $hash = SessionRegistry::hash($id);

        if (array_key_exists($hash, $this->rows)) {
            return $this->rows[$hash];
        }

        $this->lock($id);

        $row = $this->db->query(
            "SELECT id, user_id, impersonated_by, payload, last_activity, idle_seconds, absolute_expires_at, expires_at
               FROM {$this->table} WHERE id = ?",
            [$hash]
        )->fetch(\PDO::FETCH_ASSOC);

        return $this->rows[$hash] = $row ?: false;
    }

    private function lock(string $id): void
    {
        $name = $this->lockName($id);

        if (isset($this->locks[$name])) {
            return;
        }

        $got = $this->db->query('SELECT GET_LOCK(?, ?)', [$name, $this->lockSeconds])->fetchColumn();

        if ((int) $got !== 1) {
            throw new RuntimeException("Another request held this session for more than {$this->lockSeconds} seconds.");
        }

        $this->locks[$name] = true;
    }

    private function unlock(string $id): void
    {
        $name = $this->lockName($id);

        if (isset($this->locks[$name])) {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$name]);
            unset($this->locks[$name]);
        }
    }

    /**
     * MySQL caps lock names at 64 characters, and a hash prefix of 40 hex is
     * still far beyond collision range for live sessions.
     */
    private function lockName(string $id): string
    {
        return 'lx_sess:'.substr(SessionRegistry::hash($id), 0, 40);
    }
}
