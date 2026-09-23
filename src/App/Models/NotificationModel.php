<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Manages in-app notifications.
 *
 * Plan 1 provides create/read primitives. Plan 3 adds unread counts,
 * email delivery, preference gates, and the prune command.
 */
class NotificationModel extends AppModel
{
    protected ?string $table = 'notifications';

    /** Valid values for the `scope` column, deciding which inbox a row belongs in. */
    public const SCOPES = ['personal', 'content', 'admin'];

    /**
     * Create a notification for a user.
     *
     * @param  int  $userId  Recipient
     * @param  string  $type  e.g. 'blog.invite', 'post.approved', 'collaborator.role_changed'
     * @param  array<string, mixed>  $data  JSON-serialisable payload
     * @param  string  $scope  One of self::SCOPES; defaults to the safest choice
     * @return bool True on success
     */
    public function create(int $userId, string $type, array $data, string $scope = 'personal'): bool
    {
        if (!in_array($scope, self::SCOPES, true)) {
            $scope = 'personal';
        }

        $sql = 'INSERT INTO notifications (user_id, type, scope, data) VALUES (?, ?, ?, ?)';

        return $this->database->execute($sql, [$userId, $type, $scope, json_encode($data)]) > 0;
    }

    /**
     * Get recent notifications for a user (newest first).
     *
     * @param  int  $userId  Recipient
     * @param  int  $limit  Max rows
     * @param  bool  $onlyUnread  Limit to rows where read_at is NULL
     * @param  string  $scope  Restrict to one scope, '' for every scope
     * @return array<int, array<string, mixed>> List of notifications
     */
    public function findForUser(int $userId, int $limit = 20, bool $onlyUnread = false, string $scope = ''): array
    {
        $params = [$userId];
        $sql = 'SELECT id, type, scope, data, read_at, created_at
                FROM notifications
                WHERE user_id = ?'
                .($onlyUnread ? ' AND read_at IS NULL' : '');

        if ($scope !== '') {
            $sql .= ' AND scope = ?';
            $params[] = $scope;
        }

        // id breaks the tie: several notifications can land in the same second,
        // and without it the newest few are whichever ones the engine felt like.
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT '.max(0, $limit);

        return $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark a notification as read (scoped to its owner for safety).
     *
     * @param  int  $id  Notification ID
     * @param  int  $userId  Owner — prevents marking another user's notification
     * @param  string  $scope  Restrict to one scope, '' for any. Callers pass their
     *                         own inbox's scope so an id copied from one inbox can't
     *                         be replayed against another.
     * @return bool True if an unread notification was marked
     */
    public function markRead(int $id, int $userId, string $scope = ''): bool
    {
        $sql = 'UPDATE notifications SET read_at = UTC_TIMESTAMP()
                WHERE id = ? AND user_id = ? AND read_at IS NULL'
                .($scope !== '' ? ' AND scope = ?' : '');
        $params = $scope !== '' ? [$id, $userId, $scope] : [$id, $userId];

        return $this->database->execute($sql, $params) > 0;
    }

    /**
     * Count unread notifications for a user.
     *
     * @param  int  $userId  Recipient
     * @param  string  $scope  Restrict to one scope, '' for every scope
     */
    public function unreadCount(int $userId, string $scope = ''): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL'
                .($scope !== '' ? ' AND scope = ?' : '');
        $params = $scope !== '' ? [$userId, $scope] : [$userId];
        $row = $this->database->query($sql, $params)->fetch(\PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Mark every unread notification for the user as read.
     *
     * @param  int  $userId  Recipient
     * @param  string  $scope  Restrict to one scope, '' for every scope
     * @return int Rows affected
     */
    public function markAllRead(int $userId, string $scope = ''): int
    {
        $sql = 'UPDATE notifications SET read_at = UTC_TIMESTAMP()
                WHERE user_id = ? AND read_at IS NULL'
                .($scope !== '' ? ' AND scope = ?' : '');
        $params = $scope !== '' ? [$userId, $scope] : [$userId];

        return $this->database->execute($sql, $params);
    }

    /**
     * Delete one notification belonging to a user.
     *
     * @param  int  $id  Notification ID
     * @param  int  $userId  Owner — prevents deleting another user's notification
     * @param  string  $scope  Restrict to one scope, '' for any. Callers pass their
     *                         own inbox's scope so an id copied from one inbox can't
     *                         be replayed against another.
     * @return bool True if a row was removed
     */
    public function deleteForUser(int $id, int $userId, string $scope = ''): bool
    {
        $sql = 'DELETE FROM notifications WHERE id = ? AND user_id = ?'
                .($scope !== '' ? ' AND scope = ?' : '');
        $params = $scope !== '' ? [$id, $userId, $scope] : [$id, $userId];

        return $this->database->execute($sql, $params) > 0;
    }

    /**
     * Delete every notification belonging to a user.
     *
     * @param  int  $userId  Recipient
     * @param  string  $scope  Restrict to one scope, '' for every scope. Callers
     *                         always pass their own inbox's scope in practice,
     *                         since leaving it empty would let "clear all" on
     *                         one inbox wipe the other two.
     * @return int Rows deleted
     */
    public function deleteAllForUser(int $userId, string $scope = ''): int
    {
        $sql = 'DELETE FROM notifications WHERE user_id = ?'.($scope !== '' ? ' AND scope = ?' : '');
        $params = $scope !== '' ? [$userId, $scope] : [$userId];

        return $this->database->execute($sql, $params);
    }

    /**
     * What makes a reply worth showing: it still exists and is still public.
     *
     * Held apart from the join because two statements need the same answer and
     * ask it in opposite directions. The listing joins through and keeps what
     * matches; the sweep below looks for rows where nothing matches. If these
     * ever drifted, the badge would count rows the list cannot show, which is
     * exactly the stuck badge this const exists to prevent.
     *
     * The comment id lives inside the JSON payload, so the join extracts it
     * rather than reading a column. Joining at all is not optional: it is also
     * where the live slugs come from. The slugs stored in the payload are a
     * snapshot and point at a 404 once a post is renamed.
     */
    private const REPLY_LIVE = "c.deleted_at IS NULL
                   AND c.status = 'approved'
                   AND c.hidden_at IS NULL
                   AND p.status = 'published' AND p.visibility = 'public'
                   AND b.status = 'published'";

    /**
     * The join every reply listing shares.
     */
    private const REPLY_JOIN = "FROM notifications n
                 INNER JOIN comments c ON c.id = CAST(n.data->>'$.comment_id' AS UNSIGNED)
                 INNER JOIN posts p ON p.id = c.post_id
                 INNER JOIN blogs b ON b.id = p.blog_id
                 WHERE n.user_id = ?
                   AND ".self::REPLY_LIVE;

    /**
     * One page of replies to things the viewer wrote.
     *
     * Read rows stay in the list. It is a history, not a queue to drain, so
     * read_at only decides whether a row is marked, never whether it appears.
     *
     * @param  int  $userId  Recipient
     * @param  array<int, string>  $types  Notification types that count as a reply
     * @param  int  $page  1-based page index
     * @param  int  $perPage  Rows per page
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function pageOfRepliesForUser(int $userId, array $types, int $page = 1, int $perPage = 20): array
    {
        if ($types === []) {
            return ['items' => [], 'total' => 0, 'page' => max(1, $page), 'perPage' => $perPage];
        }

        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $placeholders = implode(', ', array_fill(0, count($types), '?'));
        $from = self::REPLY_JOIN." AND n.type IN ({$placeholders})";
        $bindings = array_merge([$userId], array_values($types));

        $total = (int) $this->database->query("SELECT COUNT(*) {$from}", $bindings)->fetchColumn();

        $items = $this->database->query(
            "SELECT n.id, n.data, n.read_at, n.created_at,
                    p.slug AS post_slug, b.blog_slug, b.blog_name
             {$from}
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ".(int) $perPage.' OFFSET '.(int) $offset,
            $bindings
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Unread replies for the masthead badge.
     *
     * Deliberately does not join: the badge answers "is there anything new",
     * and it runs on every page. idx_user_read covers it outright. A reply
     * whose comment has since been removed therefore still counts until it is
     * marked read, which is the cheaper wrong answer of the two.
     *
     * @param  array<int, string>  $types  Notification types that count as a reply
     */
    public function unreadReplyCount(int $userId, array $types): int
    {
        if ($types === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($types), '?'));

        return (int) $this->database->query(
            "SELECT COUNT(*) FROM notifications
             WHERE user_id = ? AND read_at IS NULL AND type IN ({$placeholders})",
            array_merge([$userId], array_values($types))
        )->fetchColumn();
    }

    /**
     * Mark read every reply the list can never show.
     *
     * A reply notification outlives its comment: the comment is deleted,
     * unapproved, or its post or blog is taken down, and the row stays unread
     * forever while never appearing in the list. The badge would then sit on a
     * number the reader has no way to clear, which is worse than a wrong count.
     *
     * Called alongside the ordinary mark-read, so visiting Replies settles both
     * the rows the reader just saw and the ones they never could.
     *
     * @param  array<int, string>  $types  Notification types that count as a reply
     * @return int Rows marked
     */
    public function markUnreachableRepliesRead(int $userId, array $types): int
    {
        if ($types === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($types), '?'));

        return $this->database->execute(
            "UPDATE notifications n SET n.read_at = UTC_TIMESTAMP()
             WHERE n.user_id = ? AND n.read_at IS NULL AND n.type IN ({$placeholders})
               AND NOT EXISTS (
                   SELECT 1 FROM comments c
                   INNER JOIN posts p ON p.id = c.post_id
                   INNER JOIN blogs b ON b.id = p.blog_id
                   WHERE c.id = CAST(n.data->>'$.comment_id' AS UNSIGNED)
                     AND ".self::REPLY_LIVE.'
               )',
            array_merge([$userId], array_values($types))
        );
    }

    /**
     * Mark the given notifications read, ignoring any the user does not own.
     *
     * Foreign ids are dropped by the WHERE clause rather than rejected, so a
     * stale tab posting ids from a previous session is a no-op and not an
     * error the reader has to see.
     *
     * @param  array<int, int>  $ids  Notification ids rendered on the page
     * @return int Rows marked
     */
    public function markReadForUser(int $userId, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        return $this->database->execute(
            "UPDATE notifications SET read_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND read_at IS NULL AND id IN ({$placeholders})",
            array_merge([$userId], $ids)
        );
    }

    /**
     * Return paginated notifications for a user with total count.
     *
     * @param  int  $userId  Recipient
     * @param  int  $perPage  Page size
     * @param  int  $page  1-based page index
     * @param  string  $scope  Restrict to one scope, '' for every scope
     * @param  bool  $onlyUnread  Limit to rows where read_at is NULL, for the unread filter
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function findPageForUser(int $userId, int $perPage = 20, int $page = 1, string $scope = '', bool $onlyUnread = false): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $scopeSql = $scope !== '' ? ' AND scope = ?' : '';
        $unreadSql = $onlyUnread ? ' AND read_at IS NULL' : '';
        $params = $scope !== '' ? [$userId, $scope] : [$userId];

        $countRow = $this->database
            ->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?{$scopeSql}{$unreadSql}", $params)
            ->fetch(\PDO::FETCH_ASSOC);

        $items = $this->database
            ->query(
                "SELECT id, type, scope, data, read_at, created_at
                 FROM notifications
                 WHERE user_id = ?{$scopeSql}{$unreadSql}
                 ORDER BY created_at DESC, id DESC
                 LIMIT ".(int) $perPage.' OFFSET '.(int) $offset,
                $params
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => (int) ($countRow['c'] ?? 0),
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * One notification belonging to this user.
     *
     * Scoped the same way markRead is: an id lifted from another inbox finds
     * nothing here either, so opening one cannot read across scopes.
     *
     * @param  int  $id  Notification ID
     * @param  int  $userId  Owner
     * @param  string  $scope  Restrict to one scope, '' for any
     * @return array<string, mixed>|null The row, or null when it is not theirs
     */
    public function findOneForUser(int $id, int $userId, string $scope = ''): ?array
    {
        $params = [$id, $userId];
        $sql = 'SELECT id, type, scope, data, read_at, created_at
                FROM notifications
                WHERE id = ? AND user_id = ?';

        if ($scope !== '') {
            $sql .= ' AND scope = ?';
            $params[] = $scope;
        }

        $row = $this->database->query($sql.' LIMIT 1', $params)->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Whether an admin-scoped notification of this type already exists inside
     * the cooldown window.
     *
     * Fan-out events (a burst of mail failures, a stalled scheduler) would
     * otherwise write one row per administrator on every check that finds the
     * condition still true, so AdminNotificationDispatcher checks this before
     * writing anything.
     *
     * @param  string  $type  Notification type, e.g. 'admin.mail_queue_failures'
     * @param  int  $withinMinutes  How recently is "already notified"
     * @param  string|null  $dedupeKey  When given, narrows the check to rows whose
     *                                  payload carries this same key (e.g. a case
     *                                  id) instead of every row of this type
     */
    public function existsRecentAdminNotification(string $type, int $withinMinutes, ?string $dedupeKey = null): bool
    {
        $sql = "SELECT 1 FROM notifications
                WHERE scope = 'admin' AND type = ?
                  AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE)";
        $params = [$type, $withinMinutes];

        if ($dedupeKey !== null) {
            $sql .= " AND data->>'$.dedupe_key' = ?";
            $params[] = $dedupeKey;
        }

        return (bool) $this->database->query($sql.' LIMIT 1', $params)->fetchColumn();
    }

    /**
     * Hard-delete stale notifications.
     *
     * Two passes per the retention policy:
     *   1. Read notifications older than 30 days.
     *   2. Any notification (read or unread) older than 90 days.
     *
     * Called from NotificationPruneCommand via the `notifications:prune` CLI entry.
     *
     * @return array{read_pruned:int, old_pruned:int} Counts deleted per pass
     */
    public function pruneStale(): array
    {
        $readPruned = $this->database->execute(
            'DELETE FROM notifications
             WHERE read_at IS NOT NULL
               AND read_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)'
        );

        $oldPruned = $this->database->execute(
            'DELETE FROM notifications
             WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)'
        );

        return [
            'read_pruned' => (int) $readPruned,
            'old_pruned' => (int) $oldPruned,
        ];
    }
}
