<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\CommentModel;
use Framework\Database;
use RuntimeException;

/**
 * Applies and lifts account suspensions, including the cascade over the
 * account's own blogs and comments.
 *
 * users.is_active stays the single sign-in gate, so Auth blocks a suspended
 * account and drops its open sessions without knowing this class exists. The
 * columns beside it record why and until when; user_suspensions keeps the
 * history the account detail page reads.
 */
class UserSuspensionService
{
    public const TYPE_TEMPORARY = 'temporary';

    public const TYPE_PERMANENT = 'permanent';

    /** Marks the comments this cascade hid, so lifting cannot un-hide a comment hidden for some other reason later. */
    private const COMMENT_REASON = 'author_suspended';

    public function __construct(
        private Database $database,
        private PublicCacheInvalidator $cacheInvalidator,
        private CommentModel $comments,
        private BlogModel $blogs,
    ) {}

    /**
     * Suspend an account and hide the content the cascade covers.
     *
     * @param  string|null  $expiresAt  UTC 'Y-m-d H:i:s' for a temporary suspension, null for permanent
     * @return array{blogs: int, comments: int} What the cascade actually hid, for the admin to read back
     *
     * @throws RuntimeException When the account is already suspended or the cascade cannot complete
     */
    public function suspend(int $userId, ?string $expiresAt, ?string $reason, int $actorId): array
    {
        $user = $this->database
            ->query('SELECT id, suspended_at FROM users WHERE id = ? AND deleted_at IS NULL', [$userId])
            ->fetch();

        if (!$user) {
            throw new RuntimeException('That account no longer exists.');
        }

        if ($user['suspended_at'] !== null) {
            throw new RuntimeException('That account is already suspended. Lift the current suspension first.');
        }

        $type = $expiresAt === null ? self::TYPE_PERMANENT : self::TYPE_TEMPORARY;

        $result = $this->atomically(function () use ($userId, $expiresAt, $reason, $actorId, $type): array {
            $this->database->execute(
                'UPDATE users
                    SET is_active = 0,
                        suspended_at = UTC_TIMESTAMP(),
                        suspended_until = ?,
                        suspension_reason = ?,
                        suspended_by = ?
                  WHERE id = ?',
                [$expiresAt, $reason, $actorId, $userId]
            );

            $blogs = $this->hideSoloBlogs($userId);
            $comments = $this->hideComments($userId);

            $this->database->execute(
                'INSERT INTO user_suspensions
                    (user_id, type, reason, suspended_by, suspended_at, expires_at, blogs_hidden, comments_hidden)
                 VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), ?, ?, ?)',
                [$userId, $type, $reason, $actorId, $expiresAt, $blogs, $comments]
            );

            return ['blogs' => $blogs, 'comments' => $comments];
        });

        $this->purgeAfterVisibilityChange($userId);

        return $result;
    }

    /**
     * Lift a suspension and put the hidden content back exactly as it was.
     *
     * @param  int|null  $actorId  Null when the scheduler lifted it rather than a person
     * @return array{blogs: int, comments: int} What was restored
     *
     * @throws RuntimeException When the account is not suspended or a blog cannot be restored
     */
    public function lift(int $userId, ?int $actorId, string $kind = 'manual'): array
    {
        $user = $this->database
            ->query('SELECT id, suspended_at FROM users WHERE id = ?', [$userId])
            ->fetch();

        if (!$user) {
            throw new RuntimeException('That account no longer exists.');
        }

        if ($user['suspended_at'] === null) {
            throw new RuntimeException('That account is not currently suspended.');
        }

        $result = $this->atomically(function () use ($userId, $actorId, $kind): array {
            $this->database->execute(
                'UPDATE users
                    SET is_active = 1,
                        suspended_at = NULL,
                        suspended_until = NULL,
                        suspension_reason = NULL,
                        suspended_by = NULL
                  WHERE id = ?',
                [$userId]
            );

            $blogs = $this->restoreBlogs($userId);
            $comments = $this->restoreComments($userId);

            $this->database->execute(
                'UPDATE user_suspensions
                    SET lifted_at = UTC_TIMESTAMP(), lifted_by = ?, lift_kind = ?
                  WHERE user_id = ? AND lifted_at IS NULL',
                [$actorId, $kind, $userId]
            );

            return ['blogs' => $blogs, 'comments' => $comments];
        });

        $this->purgeAfterVisibilityChange($userId);

        return $result;
    }

    /**
     * What suspending this account would hide, for the confirmation screen.
     *
     * Uses the same conditions as the cascade itself, so the numbers the admin
     * agrees to are the numbers that get applied.
     *
     * @return array{solo_blogs: list<array{id: int, blog_name: string}>, shared_blogs: list<array{id: int, blog_name: string}>, comments: int}
     */
    public function impact(int $userId): array
    {
        $blogs = $this->database
            ->query(
                "SELECT b.id, b.blog_name,
                        EXISTS (
                            SELECT 1 FROM blog_users bu
                             WHERE bu.blog_id = b.id AND bu.is_active = 1 AND bu.user_id <> ?
                        ) AS is_shared
                   FROM blogs b
                  WHERE b.owner_id = ? AND b.status <> 'suspended'
                  ORDER BY b.blog_name",
                [$userId, $userId]
            )
            ->fetchAll();

        $solo = [];
        $shared = [];

        foreach ($blogs as $blog) {
            $row = ['id' => (int) $blog['id'], 'blog_name' => (string) $blog['blog_name']];

            if ((int) $blog['is_shared'] === 1) {
                $shared[] = $row;
            } else {
                $solo[] = $row;
            }
        }

        $comments = (int) $this->database
            ->query('SELECT COUNT(*) FROM comments WHERE user_id = ? AND hidden_at IS NULL', [$userId])
            ->fetchColumn();

        return ['solo_blogs' => $solo, 'shared_blogs' => $shared, 'comments' => $comments];
    }

    /**
     * The open suspension for an account, or null when it is not suspended.
     *
     * @return array<string, mixed>|null
     */
    public function current(int $userId): ?array
    {
        $row = $this->database
            ->query(
                'SELECT s.*, a.handle AS suspended_by_handle
                   FROM user_suspensions s
                   LEFT JOIN users a ON a.id = s.suspended_by
                  WHERE s.user_id = ? AND s.lifted_at IS NULL
                  ORDER BY s.suspended_at DESC
                  LIMIT 1',
                [$userId]
            )
            ->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Every suspension an account has ever had, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $userId): array
    {
        return $this->database
            ->query(
                'SELECT s.*, a.handle AS suspended_by_handle, l.handle AS lifted_by_handle
                   FROM user_suspensions s
                   LEFT JOIN users a ON a.id = s.suspended_by
                   LEFT JOIN users l ON l.id = s.lifted_by
                  WHERE s.user_id = ?
                  ORDER BY s.suspended_at DESC',
                [$userId]
            )
            ->fetchAll();
    }

    /**
     * Account ids whose temporary suspension has run out.
     *
     * Compared with UTC_TIMESTAMP() rather than PHP's clock: the MySQL host and
     * PHP do not share a timezone in this project.
     *
     * @return int[]
     */
    public function dueForLift(): array
    {
        $ids = $this->database
            ->query(
                'SELECT id FROM users
                  WHERE suspended_at IS NOT NULL
                    AND suspended_until IS NOT NULL
                    AND suspended_until <= UTC_TIMESTAMP()'
            )
            ->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $ids);
    }

    /**
     * Lift the suspension first if it has already run out, so an account whose
     * time is up can sign in without waiting for the hourly task to notice.
     *
     * @return bool True when a suspension was lifted by this call
     */
    public function liftIfExpired(int $userId): bool
    {
        $expired = $this->database
            ->query(
                'SELECT 1 FROM users
                  WHERE id = ?
                    AND suspended_at IS NOT NULL
                    AND suspended_until IS NOT NULL
                    AND suspended_until <= UTC_TIMESTAMP()',
                [$userId]
            )
            ->fetchColumn();

        if ($expired === false) {
            return false;
        }

        $this->lift($userId, null, 'automatic');

        return true;
    }

    /**
     * Run the cascade in one transaction, joining the caller's if there is one.
     * Database refuses to nest them, and a caller doing more work around a
     * suspension should be able to make the whole thing atomic.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function atomically(callable $work): mixed
    {
        return $this->database->inTransaction() ? $work() : $this->database->transaction($work);
    }

    /**
     * Hide only the blogs this account owns alone.
     *
     * A blog with other active collaborators stays published: their work should
     * not disappear because one member was suspended, and the suspended owner
     * cannot sign in to touch it anyway.
     *
     * The blog goes to status = 'suspended' rather than carrying a second flag,
     * because every public query already filters on status = 'published'. One
     * enum value removes it from all of them at once, with nothing to forget.
     */
    private function hideSoloBlogs(int $userId): int
    {
        return $this->database->execute(
            "UPDATE blogs b
                SET b.status_before_suspension = b.status,
                    b.status = 'suspended'
              WHERE b.owner_id = ?
                AND b.status <> 'suspended'
                AND NOT EXISTS (
                    SELECT 1 FROM blog_users bu
                     WHERE bu.blog_id = b.id AND bu.is_active = 1 AND bu.user_id <> ?
                )",
            [$userId, $userId]
        );
    }

    /**
     * @throws RuntimeException When a suspended blog has no recorded prior status to go back to
     */
    private function restoreBlogs(int $userId): int
    {
        $restored = $this->database->execute(
            "UPDATE blogs
                SET status = status_before_suspension,
                    status_before_suspension = NULL
              WHERE owner_id = ?
                AND status = 'suspended'
                AND status_before_suspension IS NOT NULL",
            [$userId]
        );

        $stranded = (int) $this->database
            ->query(
                "SELECT COUNT(*) FROM blogs WHERE owner_id = ? AND status = 'suspended'",
                [$userId]
            )
            ->fetchColumn();

        // Rolling back is the honest answer: a blog left suspended with nothing
        // to restore to needs a person, not a guessed status.
        if ($stranded > 0) {
            throw new RuntimeException(
                "Lifted nothing: {$stranded} of this account's blogs are suspended with no recorded previous status. "
                .'Set their status by hand on the blogs page, then lift the suspension again.'
            );
        }

        return $restored;
    }

    private function hideComments(int $userId): int
    {
        return $this->database->execute(
            'UPDATE comments
                SET hidden_at = UTC_TIMESTAMP(), hidden_reason = ?
              WHERE user_id = ? AND hidden_at IS NULL',
            [self::COMMENT_REASON, $userId]
        );
    }

    private function restoreComments(int $userId): int
    {
        return $this->database->execute(
            'UPDATE comments
                SET hidden_at = NULL, hidden_reason = NULL
              WHERE user_id = ? AND hidden_reason = ?',
            [$userId, self::COMMENT_REASON]
        );
    }

    /**
     * Blog pages and comment threads are both cached, so a visibility change
     * that skipped this would keep serving the hidden content until the TTL.
     */
    private function purgeAfterVisibilityChange(int $userId): void
    {
        $handle = $this->database
            ->query('SELECT handle FROM users WHERE id = ?', [$userId])
            ->fetchColumn();

        $this->cacheInvalidator->purgeBlogSurfaces();

        // The blog status is written with raw SQL so the prior status is saved in
        // the same statement, which skips BlogModel::update() and the cache
        // clearing inside it. Without this the cached row keeps serving the blog.
        $slugs = $this->database
            ->query('SELECT blog_slug FROM blogs WHERE owner_id = ?', [$userId])
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($slugs as $slug) {
            $this->blogs->forgetPublicCaches((string) $slug);
        }

        if (is_string($handle) && $handle !== '') {
            $this->cacheInvalidator->purgeAuthorSurfaces($handle);
        }

        foreach ($this->postIdsCommentedOn($userId) as $postId) {
            $this->comments->forgetThreadCache($postId);
        }
    }

    /**
     * @return int[]
     */
    private function postIdsCommentedOn(int $userId): array
    {
        $ids = $this->database
            ->query('SELECT DISTINCT post_id FROM comments WHERE user_id = ?', [$userId])
            ->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $ids);
    }
}
