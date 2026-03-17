<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Manages one-time password reset tokens.
 *
 * Ensures only one active token per email. Handles creation, validation, cleanup.
 */
final class PasswordResetModel extends AppModel
{
    protected ?string $table = 'password_resets';

    /**
     * Create/replace the active reset token for an email.
     *
     * Only one active token per email - older links immediately invalidated.
     * Uses transaction to ensure atomicity across deletion and insertion.
     *
     * @param  string  $email  User email
     * @param  string  $tokenHash  Hashed token
     * @param  string  $expiresAt  ISO 8601 datetime
     * @return bool Success
     */
    public function replaceForEmail(string $email, string $tokenHash, string $expiresAt): bool
    {
        return $this->transaction(function () use ($email, $tokenHash, $expiresAt): bool {
            // delete previous tokens so only the most recent reset link works.
            $deleteSql = "DELETE FROM {$this->table} WHERE email = ?";
            $this->database->execute($deleteSql, [$email]);

            $insertSql = "INSERT INTO {$this->table} (email, token, expires_at, created_at)
                          VALUES (?, ?, ?, UTC_TIMESTAMP())";

            $rowCount = $this->database->execute($insertSql, [$email, $tokenHash, $expiresAt]);

            return $rowCount > 0;
        });
    }

    /**
     * Find valid (non-expired) reset token by hash.
     *
     * @param  string  $tokenHash  Token hash
     * @return array{email: string, token: string, expires_at: string}|false Valid token or false
     */
    public function findValidByTokenHash(string $tokenHash): array|false
    {
        $sql = "SELECT email, token, expires_at
                FROM {$this->table}
                WHERE token = ?
                  AND expires_at > UTC_TIMESTAMP()
                LIMIT 1";

        $stmt = $this->database->query($sql, [$tokenHash]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete reset token by hash.
     *
     * @param  string  $tokenHash  Token hash
     * @return bool True if token was deleted
     */
    public function deleteByTokenHash(string $tokenHash): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE token = ?";

        $rowCount = $this->database->execute($sql, [$tokenHash]);

        return $rowCount > 0;
    }

    /**
     * Clean up all expired reset tokens.
     *
     * @return int Number deleted
     */
    public function deleteExpired(): int
    {
        $sql = "DELETE FROM {$this->table} WHERE expires_at <= UTC_TIMESTAMP()";

        return $this->database->execute($sql);
    }
}
