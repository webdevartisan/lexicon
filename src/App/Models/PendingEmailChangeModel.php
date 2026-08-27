<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Email changes waiting for the new address to confirm itself.
 *
 * Only the sha256 of the emailed token is stored, so a database read cannot be
 * replayed as a confirmation link. One pending change per user: asking for
 * another replaces it, which the unique key on user_id enforces.
 */
final class PendingEmailChangeModel extends AppModel
{
    protected ?string $table = 'pending_email_changes';

    /**
     * Record a pending change, discarding whatever the user had pending before.
     *
     * The unique key on user_id makes this one atomic statement rather than a
     * delete and an insert, so there is no window where a user has two pending
     * changes and no transaction to nest inside a caller's.
     *
     * @param  int  $userId  Account the change belongs to
     * @param  string  $newEmail  Address awaiting confirmation
     * @param  string  $tokenHash  sha256 of the token that was emailed
     * @param  string  $expiresAt  UTC 'Y-m-d H:i:s' after which the link is dead
     */
    public function replaceForUser(int $userId, string $newEmail, string $tokenHash, string $expiresAt): void
    {
        $sql = "INSERT INTO {$this->table} (user_id, new_email, token, expires_at, created_at)
                VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    new_email = VALUES(new_email),
                    token = VALUES(token),
                    expires_at = VALUES(expires_at),
                    created_at = UTC_TIMESTAMP()";

        $this->database->execute($sql, [$userId, $newEmail, $tokenHash, $expiresAt]);
    }

    /**
     * Look up an unexpired pending change by its token hash.
     *
     * @param  string  $tokenHash  sha256 of the token from the link
     * @return array{id: int, user_id: int, new_email: string, expires_at: string}|false
     */
    public function findValidByTokenHash(string $tokenHash): array|false
    {
        $sql = "SELECT id, user_id, new_email, expires_at
                FROM {$this->table}
                WHERE token = ?
                  AND expires_at > UTC_TIMESTAMP()
                LIMIT 1";

        return $this->database->query($sql, [$tokenHash])->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * The change this user is currently waiting on, expired ones included.
     *
     * Expired rows are returned deliberately: the settings page says "that link
     * has run out, send another" rather than pretending nothing was requested.
     * Expiry is decided by MySQL against UTC_TIMESTAMP() rather than in PHP,
     * because the database session zone and the PHP zone are not the same here.
     *
     * @return array{new_email: string, expires_at: string, is_expired: int}|false
     */
    public function findForUser(int $userId): array|false
    {
        $sql = "SELECT new_email, expires_at, (expires_at <= UTC_TIMESTAMP()) AS is_expired
                FROM {$this->table}
                WHERE user_id = ?
                LIMIT 1";

        return $this->database->query($sql, [$userId])->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Drop this user's pending change, if any.
     */
    public function deleteForUser(int $userId): bool
    {
        return $this->database->execute("DELETE FROM {$this->table} WHERE user_id = ?", [$userId]) > 0;
    }

    /**
     * Remove every pending change whose link has run out.
     *
     * @return int Rows removed
     */
    public function deleteExpired(): int
    {
        return $this->database->execute("DELETE FROM {$this->table} WHERE expires_at <= UTC_TIMESTAMP()");
    }
}
