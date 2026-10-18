<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Confirmation links for new accounts.
 *
 * Only the sha256 of the emailed token is stored, so a database read cannot be
 * replayed as a link. One row per user: asking for another link replaces it.
 */
final class EmailVerificationModel extends AppModel
{
    protected ?string $table = 'email_verifications';

    /**
     * Record a link for this user, discarding the one they had before.
     *
     * @param  string  $tokenHash  sha256 of the token that was emailed
     * @param  string  $expiresAt  UTC 'Y-m-d H:i:s' after which the link is dead
     */
    public function replaceForUser(int $userId, string $tokenHash, string $expiresAt): void
    {
        $sql = "INSERT INTO {$this->table} (user_id, token, expires_at, created_at)
                VALUES (?, ?, ?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                    token = VALUES(token),
                    expires_at = VALUES(expires_at),
                    created_at = UTC_TIMESTAMP()";

        $this->database->execute($sql, [$userId, $tokenHash, $expiresAt]);
    }

    /**
     * The unexpired link behind a token, if any.
     *
     * @return array{id: int, user_id: int}|false
     */
    public function findValidByTokenHash(string $tokenHash): array|false
    {
        $sql = "SELECT id, user_id FROM {$this->table}
                WHERE token = ? AND expires_at > UTC_TIMESTAMP()
                LIMIT 1";

        return $this->database->query($sql, [$tokenHash])->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Drop this user's link, used or not.
     */
    public function deleteForUser(int $userId): void
    {
        $this->database->execute("DELETE FROM {$this->table} WHERE user_id = ?", [$userId]);
    }

    /**
     * Remove every link that has run out.
     *
     * @return int Rows removed
     */
    public function deleteExpired(): int
    {
        return $this->database->execute("DELETE FROM {$this->table} WHERE expires_at <= UTC_TIMESTAMP()");
    }
}
