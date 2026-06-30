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

    /**
     * Create a notification for a user.
     *
     * @param  int  $userId  Recipient
     * @param  string  $type  e.g. 'blog.invite', 'post.approved', 'collaborator.role_changed'
     * @param  array  $data  JSON-serialisable payload
     * @return bool True on success
     */
    public function create(int $userId, string $type, array $data): bool
    {
        $sql = 'INSERT INTO notifications (user_id, type, data) VALUES (?, ?, ?)';

        return $this->database->execute($sql, [$userId, $type, json_encode($data)]) > 0;
    }

    /**
     * Get recent notifications for a user (newest first).
     *
     * @param  int  $userId  Recipient
     * @param  int  $limit  Max rows
     * @param  bool  $onlyUnread  Limit to rows where read_at is NULL
     * @return array List of notifications
     */
    public function findForUser(int $userId, int $limit = 20, bool $onlyUnread = false): array
    {
        $sql = 'SELECT id, type, data, read_at, created_at
                FROM notifications
                WHERE user_id = :user_id'
                .($onlyUnread ? ' AND read_at IS NULL' : '').'
                ORDER BY created_at DESC
                LIMIT :limit';

        return $this->database->query($sql, [':user_id' => $userId, ':limit' => $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark a notification as read (scoped to its owner for safety).
     *
     * @param  int  $id  Notification ID
     * @param  int  $userId  Owner — prevents marking another user's notification
     * @return bool True if an unread notification was marked
     */
    public function markRead(int $id, int $userId): bool
    {
        $sql = 'UPDATE notifications SET read_at = UTC_TIMESTAMP()
                WHERE id = ? AND user_id = ? AND read_at IS NULL';

        return $this->database->execute($sql, [$id, $userId]) > 0;
    }

    /**
     * Count unread notifications for a user.
     *
     * @param  int  $userId  Recipient
     * @return int
     */
    public function unreadCount(int $userId): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL';
        $row = $this->database->query($sql, [$userId])->fetch(\PDO::FETCH_ASSOC);

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Mark every unread notification for the user as read.
     *
     * @param  int  $userId  Recipient
     * @return int Rows affected
     */
    public function markAllRead(int $userId): int
    {
        $sql = 'UPDATE notifications SET read_at = UTC_TIMESTAMP()
                WHERE user_id = ? AND read_at IS NULL';

        return $this->database->execute($sql, [$userId]);
    }

    /**
     * Return paginated notifications for a user with total count.
     *
     * @param  int  $userId  Recipient
     * @param  int  $perPage Page size
     * @param  int  $page    1-based page index
     * @return array{items: array<int, array>, total: int, page: int, perPage: int}
     */
    public function findPageForUser(int $userId, int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $countRow = $this->database
            ->query('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ?', [$userId])
            ->fetch(\PDO::FETCH_ASSOC);

        $items = $this->database
            ->query(
                'SELECT id, type, data, read_at, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT '.(int) $perPage.' OFFSET '.(int) $offset,
                [$userId]
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items'   => $items,
            'total'   => (int) ($countRow['c'] ?? 0),
            'page'    => $page,
            'perPage' => $perPage,
        ];
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
            'old_pruned'  => (int) $oldPruned,
        ];
    }
}
