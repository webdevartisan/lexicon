<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Manages blog collaboration invitations.
 *
 * Tokens are stored as sha256 hashes — raw tokens travel only in email links,
 * mirroring the PasswordResetModel convention.
 */
class BlogInvitationModel extends AppModel
{
    protected ?string $table = 'blog_invitations';

    /**
     * Create an invitation, cancelling any existing pending invite for the same email+blog.
     *
     * @param  int  $blogId  Target blog
     * @param  string  $email  Invitee email
     * @param  string  $role  Must be in BlogModel::ROLES
     * @param  string  $tokenHash  sha256 of the raw token
     * @param  int  $invitedBy  User ID of the owner
     * @param  string  $expiresAt  ISO 8601 datetime
     * @return bool True on success
     */
    public function create(int $blogId, string $email, string $role, string $tokenHash, int $invitedBy, string $expiresAt): bool
    {
        // Only one pending invite per email+blog: cancel the previous one first.
        $this->cancelPendingForEmail($blogId, $email);

        $sql = 'INSERT INTO blog_invitations (blog_id, email, role, token, invited_by, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)';

        return $this->database->execute($sql, [$blogId, $email, $role, $tokenHash, $invitedBy, $expiresAt]) > 0;
    }

    /**
     * Find a valid (non-expired, not yet accepted/declined) invite by token hash.
     *
     * @param  string  $tokenHash  sha256 of the raw token
     * @return array{id: int, blog_id: int, email: string, role: string, invited_by: int, expires_at: string}|false
     */
    public function findValidByToken(string $tokenHash): array|false
    {
        $sql = 'SELECT id, blog_id, email, role, invited_by, expires_at
                FROM blog_invitations
                WHERE token = ?
                  AND expires_at > UTC_TIMESTAMP()
                  AND accepted_at IS NULL
                  AND declined_at IS NULL
                LIMIT 1';

        return $this->database->query($sql, [$tokenHash])->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark an invite as accepted.
     *
     * @param  int  $id  Invitation ID
     * @return bool True if a pending invite was marked
     */
    public function markAccepted(int $id): bool
    {
        $sql = 'UPDATE blog_invitations SET accepted_at = UTC_TIMESTAMP() WHERE id = ? AND accepted_at IS NULL';

        return $this->database->execute($sql, [$id]) > 0;
    }

    /**
     * Mark an invite as declined.
     *
     * @param  int  $id  Invitation ID
     * @return bool True if a pending invite was marked
     */
    public function markDeclined(int $id): bool
    {
        $sql = 'UPDATE blog_invitations SET declined_at = UTC_TIMESTAMP() WHERE id = ? AND declined_at IS NULL';

        return $this->database->execute($sql, [$id]) > 0;
    }

    /**
     * Cancel all unconsumed invites (pending or expired) for a given blog+email.
     *
     * Used before issuing a new invite and when the owner explicitly cancels.
     * Expired rows are included on purpose: the team page offers "Drop" on
     * them, and a resend should not leave a stale expired row behind either.
     *
     * @param  int  $blogId  Target blog
     * @param  string  $email  Invitee email
     * @return bool True if at least one invite was removed
     */
    public function cancelPendingForEmail(int $blogId, string $email): bool
    {
        $sql = 'DELETE FROM blog_invitations
                WHERE blog_id = ? AND email = ?
                  AND accepted_at IS NULL AND declined_at IS NULL';

        return $this->database->execute($sql, [$blogId, $email]) > 0;
    }

    /**
     * Hard-delete expired, unconsumed invitations after a grace period.
     *
     * The grace window keeps recently-expired invites visible on the team
     * page so the owner can see "expired" and resend, instead of the row
     * vanishing silently the moment the timer hits zero.
     *
     * @return int Number deleted
     */
    public function deleteExpired(): int
    {
        // 30 days past expiry: long enough to resend, short enough to keep the table tidy.
        $sql = 'DELETE FROM blog_invitations
                WHERE expires_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
                  AND accepted_at IS NULL
                  AND declined_at IS NULL';

        return $this->database->execute($sql);
    }

    /**
     * Get all pending invites for a blog (for the team management page).
     *
     * @param  int  $blogId  Target blog
     * @return array<int, array<string, mixed>> List of pending invitations
     */
    public function getPendingForBlog(int $blogId): array
    {
        $sql = 'SELECT id, email, role, invited_by, expires_at, created_at
                FROM blog_invitations
                WHERE blog_id = ?
                  AND accepted_at IS NULL AND declined_at IS NULL
                  AND expires_at > UTC_TIMESTAMP()
                ORDER BY created_at DESC';

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get expired-but-still-actionable invites for a blog.
     *
     * Visible on the team page so the owner can resend instead of being
     * left guessing why the row disappeared. Excludes accepted/declined.
     *
     * @param  int  $blogId  Target blog
     * @return array<int, array<string, mixed>> List of expired invitations within the grace window
     */
    public function getExpiredForBlog(int $blogId): array
    {
        $sql = 'SELECT id, email, role, invited_by, expires_at, created_at
                FROM blog_invitations
                WHERE blog_id = ?
                  AND accepted_at IS NULL AND declined_at IS NULL
                  AND expires_at <= UTC_TIMESTAMP()
                ORDER BY expires_at DESC';

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
