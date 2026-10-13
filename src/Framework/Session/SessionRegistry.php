<?php

declare(strict_types=1);

namespace Framework\Session;

use Framework\Database;

/**
 * The user_sessions rows: one per signed-in session on every driver.
 *
 * A signed-in session is only valid while its row exists, so deleting a row is
 * how a session is revoked whatever driver holds the data. Rows are keyed by
 * the SHA-256 of the session id, so reading this table never hands anyone a
 * cookie that works.
 */
class SessionRegistry
{
    /** Columns a caller may set through open(); everything else is owned here. */
    private const RECORD_COLUMNS = [
        'user_id', 'impersonated_by', 'ip_address', 'user_agent', 'device', 'browser',
        'browser_version', 'os', 'os_version', 'country', 'region', 'city', 'signed_in_at',
        'idle_seconds', 'absolute_expires_at',
    ];

    public function __construct(
        private Database $db,
        private string $table = 'user_sessions',
    ) {}

    /**
     * The key a session id is stored under.
     */
    public static function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $hash): ?array
    {
        $row = $this->db->query(
            "SELECT id, user_id, impersonated_by, last_activity, idle_seconds, absolute_expires_at, expires_at
               FROM {$this->table} WHERE id = ?",
            [$hash]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Create or replace the row for a session that just signed in, or whose
     * identity changed (impersonation starting or ending).
     *
     * @param  array<string, mixed>  $record  Keys from RECORD_COLUMNS
     */
    public function open(string $hash, array $record, int $now): void
    {
        $record = array_intersect_key($record, array_flip(self::RECORD_COLUMNS));
        $record['last_activity'] = $now;
        $record['expires_at'] = self::expiry(
            $now,
            (int) $record['idle_seconds'],
            isset($record['absolute_expires_at']) ? (int) $record['absolute_expires_at'] : null
        );

        $columns = array_keys($record);
        $updates = implode(', ', array_map(static fn (string $c): string => "{$c} = VALUES({$c})", $columns));

        $this->db->execute(
            sprintf(
                'INSERT INTO %s (id, created_at, %s) VALUES (?, ?, %s) ON DUPLICATE KEY UPDATE %s',
                $this->table,
                implode(', ', $columns),
                implode(', ', array_fill(0, count($columns), '?')),
                $updates
            ),
            [$hash, $now, ...array_values($record)]
        );
    }

    /**
     * Move a row to a regenerated session id, keeping everything it records.
     */
    public function rekey(string $oldHash, string $newHash): void
    {
        $this->db->execute("UPDATE {$this->table} SET id = ? WHERE id = ?", [$newHash, $oldHash]);
    }

    /**
     * Record activity, at most once per $interval seconds, plus the latest address.
     *
     * @param  array<string, mixed>  $row  As returned by find()
     */
    public function touch(array $row, int $now, int $interval, ?string $ip): void
    {
        if ($now - (int) $row['last_activity'] < $interval) {
            return;
        }

        $expires = self::expiry(
            $now,
            (int) $row['idle_seconds'],
            $row['absolute_expires_at'] !== null ? (int) $row['absolute_expires_at'] : null
        );

        $this->db->execute(
            "UPDATE {$this->table} SET last_activity = ?, expires_at = ?, ip_address = COALESCE(?, ip_address) WHERE id = ?",
            [$now, $expires, $ip, $row['id']]
        );
    }

    public function forget(string $hash): void
    {
        $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$hash]);
    }

    /**
     * Signed-in sessions of one user, most recently active first.
     *
     * Impersonated sessions belong to the administrator using them, so the
     * account owner's own list leaves them out.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, bool $includeImpersonated, int $now): array
    {
        $sql = "SELECT id, impersonated_by, ip_address, device, browser, browser_version, os, os_version,
                       country, region, city, created_at, signed_in_at, last_activity
                  FROM {$this->table}
                 WHERE user_id = ? AND expires_at > ?";

        if (!$includeImpersonated) {
            $sql .= ' AND impersonated_by IS NULL';
        }

        return $this->db->query($sql.' ORDER BY last_activity DESC', [$userId, $now])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Sessions an administrator opened on other accounts.
     *
     * @return list<array<string, mixed>>
     */
    public function impersonatedBy(int $adminId, int $now): array
    {
        return $this->db->query(
            "SELECT id, user_id, ip_address, device, browser, browser_version, os, os_version,
                    country, region, city, created_at, signed_in_at, last_activity
               FROM {$this->table}
              WHERE impersonated_by = ? AND expires_at > ?
              ORDER BY last_activity DESC",
            [$adminId, $now]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete a user's own sessions, never the ones an administrator is
     * impersonating them in.
     *
     * @param  string|null  $exceptHash  A session to keep, usually the current one
     * @param  string|null  $onlyHash  Limit to this one session
     * @return list<string> The hashes that were deleted
     */
    public function deleteForUser(int $userId, ?string $exceptHash = null, ?string $onlyHash = null): array
    {
        $where = 'user_id = ? AND impersonated_by IS NULL';
        $params = [$userId];

        if ($exceptHash !== null) {
            $where .= ' AND id <> ?';
            $params[] = $exceptHash;
        }

        if ($onlyHash !== null) {
            $where .= ' AND id = ?';
            $params[] = $onlyHash;
        }

        $hashes = $this->db->query("SELECT id FROM {$this->table} WHERE {$where}", $params)->fetchAll(\PDO::FETCH_COLUMN);

        if ($hashes === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($hashes), '?'));
        $this->db->execute("DELETE FROM {$this->table} WHERE id IN ({$marks})", $hashes);

        return array_map('strval', $hashes);
    }

    /**
     * Delete every session whose time is up.
     *
     * @return int Rows removed
     */
    public function prune(int $now): int
    {
        return $this->db->execute("DELETE FROM {$this->table} WHERE expires_at <= ?", [$now]);
    }

    /**
     * When the oldest row that should already have been pruned expired, or
     * null when there is none. Hours old means session:prune is not running.
     */
    public function oldestExpired(int $now): ?int
    {
        $oldest = $this->db->query("SELECT MIN(expires_at) FROM {$this->table} WHERE expires_at <= ?", [$now])->fetchColumn();

        return $oldest !== null && $oldest !== false ? (int) $oldest : null;
    }

    /**
     * The earlier of the idle deadline and the absolute one.
     */
    public static function expiry(int $lastActivity, int $idleSeconds, ?int $absoluteExpiresAt): int
    {
        $idle = $lastActivity + $idleSeconds;

        return $absoluteExpiresAt === null ? $idle : min($idle, $absoluteExpiresAt);
    }
}
