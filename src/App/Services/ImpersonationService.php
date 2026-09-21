<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use Framework\Database;
use Framework\Security\Csrf;
use Framework\Session;
use RuntimeException;

/**
 * Admin impersonation: starting, ending, and describing a "log in as" session.
 *
 * Sessions here are the ordinary ones. Lexicon keeps no server-side session
 * store, so there is nothing to pair two logins against: impersonating swaps
 * the user_id the session already carries and remembers the real administrator
 * beside it. Exiting swaps it back, which is why the admin never has to sign in
 * again and can never be stranded.
 *
 * Every entry and exit regenerates the session id, so the impersonated context
 * and the admin's own context never share a cookie or a CSRF token.
 */
class ImpersonationService
{
    /** The real administrator, parked while someone else's id sits in user_id. */
    private const KEY_ADMIN = 'impersonator_id';

    /** Row in impersonation_sessions, so the exit can close the one it opened. */
    private const KEY_RECORD = 'impersonation_record_id';

    /** Unix timestamp the session stops being valid. */
    private const KEY_EXPIRES = 'impersonation_expires_at';

    /**
     * The admin's own session epoch, put back on exit. Restoring the stored
     * value rather than re-reading it means that if the admin's sessions were
     * invalidated meanwhile, returning to them is refused as it should be.
     */
    private const KEY_ADMIN_EPOCH = 'impersonator_session_epoch';

    /**
     * Impersonated sessions expire well before an ordinary one. An admin who
     * walks away should not leave someone else's account open indefinitely.
     */
    public const MAX_MINUTES = 30;

    public function __construct(
        private Session $session,
        private Database $database,
        private UserModel $users,
        private Csrf $csrf,
    ) {}

    /**
     * Whether the actor may impersonate the target at all.
     *
     * Re-read from the database rather than taken from the session, so a role
     * revoked a moment ago cannot still be spent here.
     *
     * @param  array<string, mixed>  $actor  The signed-in administrator
     * @return string|null The reason it is refused, or null when allowed
     */
    public function refusalReason(array $actor, int $targetId): ?string
    {
        if (!in_array('administrator', $this->users->getUserRoles((int) $actor['id']), true)) {
            return 'Only administrators can sign in as another account.';
        }

        if ((int) $actor['id'] === $targetId) {
            return 'You are already signed in as yourself.';
        }

        if ($this->isImpersonating()) {
            return 'You are already signed in as someone else. Exit that session first.';
        }

        $target = $this->users->find($targetId);

        if (!$target || $target['deleted_at'] !== null) {
            return 'That account no longer exists.';
        }

        // Equal or higher privilege is the escalation the guard exists for.
        // Administrator is the top tier, so admin-on-admin is the whole of it.
        if (in_array('administrator', $this->users->getUserRoles($targetId), true)) {
            return 'You cannot sign in as another administrator.';
        }

        if ((int) $target['is_active'] !== 1) {
            return 'That account is suspended. Lift the suspension before signing in as them.';
        }

        return null;
    }

    /**
     * Begin impersonating. The caller must have checked refusalReason() first.
     *
     * @param  array<string, mixed>  $actor  The signed-in administrator
     *
     * @throws RuntimeException When the guard was not satisfied
     */
    public function start(array $actor, int $targetId, ?string $reason, ?string $ip): void
    {
        $refusal = $this->refusalReason($actor, $targetId);

        if ($refusal !== null) {
            throw new RuntimeException($refusal);
        }

        $adminId = (int) $actor['id'];

        $this->database->execute(
            'INSERT INTO impersonation_sessions (admin_id, target_user_id, reason, started_at, ip_address)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), ?)',
            [$adminId, $targetId, $reason, $ip]
        );

        $recordId = (int) $this->database->lastInsertId();

        // Logged before the swap, while the session still belongs to the admin:
        // afterwards AuditService would rewrite this row as acting_as itself.
        audit()->log(
            $adminId,
            'user.impersonation_started',
            'user',
            $targetId,
            ['impersonation_id' => $recordId, 'reason' => $reason],
            $ip
        );

        // New id before the swap: the cookie that carried the admin context must
        // not be the one that carries the impersonated context.
        $this->session->regenerate(true);
        $this->csrf->rotate();

        $target = $this->users->find($targetId);

        $this->session->set(self::KEY_ADMIN, $adminId);
        $this->session->set(self::KEY_ADMIN_EPOCH, (int) $this->session->get('session_epoch', 0));
        $this->session->set(self::KEY_RECORD, $recordId);
        $this->session->set(self::KEY_EXPIRES, time() + (self::MAX_MINUTES * 60));
        $this->session->set('user_id', $targetId);
        $this->session->set('session_epoch', (int) ($target['session_epoch'] ?? 0));
    }

    /**
     * Hand the session back to the administrator who started it.
     *
     * @return int|null The administrator's id, or null when nothing was being impersonated
     */
    public function stop(string $kind = 'exited', ?string $ip = null): ?int
    {
        $adminId = $this->impersonatorId();

        if ($adminId === null) {
            return null;
        }

        $recordId = (int) $this->session->get(self::KEY_RECORD, 0);
        $targetId = (int) $this->session->get('user_id', 0);

        if ($recordId > 0) {
            $this->database->execute(
                'UPDATE impersonation_sessions
                    SET ended_at = UTC_TIMESTAMP(), end_kind = ?
                  WHERE id = ? AND ended_at IS NULL',
                [$kind, $recordId]
            );
        }

        $this->handBack($adminId);

        audit()->log(
            $adminId,
            'user.impersonation_ended',
            'user',
            $targetId,
            ['impersonation_id' => $recordId, 'end_kind' => $kind],
            $ip
        );

        return $adminId;
    }

    /**
     * True while the session belongs to an administrator acting as someone else.
     */
    public function isImpersonating(): bool
    {
        return $this->impersonatorId() !== null;
    }

    /**
     * The administrator behind the current session, or null when there is none.
     *
     * Ends an expired session here rather than letting it run on, so every
     * caller sees the same answer.
     */
    public function impersonatorId(): ?int
    {
        $adminId = $this->session->get(self::KEY_ADMIN);

        if ($adminId === null) {
            return null;
        }

        $expiresAt = (int) $this->session->get(self::KEY_EXPIRES, 0);

        // No user_id means Auth dropped the target mid-session (suspended, or
        // their sessions invalidated). Leaving the admin parked would let a later
        // login inherit the marker and mislabel its audit rows as impersonated.
        $targetGone = $this->session->get('user_id') === null;

        if ($targetGone || ($expiresAt > 0 && time() >= $expiresAt)) {
            $this->expire();

            return null;
        }

        return (int) $adminId;
    }

    /**
     * What the banner needs, or null when nobody is being impersonated.
     *
     * @return array{admin_handle: string, target_handle: string, minutes_left: int}|null
     */
    public function banner(): ?array
    {
        $adminId = $this->impersonatorId();

        if ($adminId === null) {
            return null;
        }

        $admin = $this->users->find($adminId);
        $target = $this->users->find((int) $this->session->get('user_id', 0));

        if (!$admin || !$target) {
            return null;
        }

        $expiresAt = (int) $this->session->get(self::KEY_EXPIRES, 0);

        return [
            'admin_handle' => (string) $admin['handle'],
            'target_handle' => (string) $target['handle'],
            'minutes_left' => max(0, (int) ceil(($expiresAt - time()) / 60)),
        ];
    }

    /**
     * Every impersonation that touched an account, as actor or as target.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyFor(int $userId): array
    {
        return $this->database
            ->query(
                'SELECT s.*, a.handle AS admin_handle, t.handle AS target_handle
                   FROM impersonation_sessions s
                   LEFT JOIN users a ON a.id = s.admin_id
                   LEFT JOIN users t ON t.id = s.target_user_id
                  WHERE s.admin_id = ? OR s.target_user_id = ?
                  ORDER BY s.started_at DESC
                  LIMIT 50',
                [$userId, $userId]
            )
            ->fetchAll();
    }

    /**
     * Close a session that ran past its time box, putting the admin back.
     */
    private function expire(): void
    {
        $adminId = (int) $this->session->get(self::KEY_ADMIN, 0);
        $recordId = (int) $this->session->get(self::KEY_RECORD, 0);

        if ($recordId > 0) {
            $this->database->execute(
                "UPDATE impersonation_sessions
                    SET ended_at = UTC_TIMESTAMP(), end_kind = 'expired'
                  WHERE id = ? AND ended_at IS NULL",
                [$recordId]
            );
        }

        $this->handBack($adminId);
    }

    /**
     * Put the admin's own identity back into the session, under a fresh id and
     * CSRF token so nothing issued to the impersonated context carries over.
     */
    private function handBack(int $adminId): void
    {
        $adminEpoch = (int) $this->session->get(self::KEY_ADMIN_EPOCH, 0);

        $this->session->remove(self::KEY_ADMIN);
        $this->session->remove(self::KEY_ADMIN_EPOCH);
        $this->session->remove(self::KEY_RECORD);
        $this->session->remove(self::KEY_EXPIRES);

        $this->session->regenerate(true);
        $this->csrf->rotate();

        if ($adminId > 0) {
            $this->session->set('user_id', $adminId);
            $this->session->set('session_epoch', $adminEpoch);
        } else {
            $this->session->remove('user_id');
        }
    }
}
