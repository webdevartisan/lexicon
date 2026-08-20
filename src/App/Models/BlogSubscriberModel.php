<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-blog email subscribers for new post notifications.
 */
class BlogSubscriberModel extends AppModel
{
    protected ?string $table = 'blog_subscribers';

    /**
     * Add an email to a blog's subscriber list.
     *
     * Idempotent: re-subscribing an existing address succeeds quietly, whether
     * that is a guest submitting the form twice or a reader undoing an
     * unsubscribe they have already undone.
     *
     * The unique key is handled by the statement rather than by catching the
     * violation, because Database::query() rewraps PDOException as a
     * RuntimeException and a catch for the former never fires. The existing
     * token is deliberately left alone: it is live in an already-delivered
     * email and rotating it here would break that unsubscribe link. Only
     * user_id moves, which also adopts a row subscribed before the account
     * existed.
     *
     * @return bool True when subscribed (new or already present)
     */
    public function subscribe(int $blogId, string $email, ?int $userId = null): bool
    {
        $this->database->execute(
            "INSERT INTO {$this->getTable()} (blog_id, user_id, email, token) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = COALESCE(VALUES(user_id), user_id)",
            [$blogId, $userId, $email, bin2hex(random_bytes(32))]
        );

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE token = ? LIMIT 1";

        $row = $this->database->query($sql, [$token])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function deleteByToken(string $token): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE token = ? LIMIT 1",
            [$token]
        ) > 0;
    }

    /**
     * All subscribers of a blog, for the notification fan-out.
     *
     * @return array<int, array{email: string, token: string}>
     */
    public function forBlog(int $blogId): array
    {
        $sql = "SELECT email, token FROM {$this->getTable()} WHERE blog_id = ? ORDER BY id";

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countForBlog(int $blogId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE blog_id = ?";

        return (int) $this->database->query($sql, [$blogId])->fetchColumn();
    }

    /**
     * Paginated subscriber list for the dashboard management page.
     *
     * @param  string  $q  Optional email search
     * @return array{data: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function pageForBlog(int $blogId, int $page = 1, int $perPage = 25, string $q = ''): array
    {
        $where = 's.blog_id = ?';
        $params = [$blogId];

        if ($q !== '') {
            $where .= ' AND s.email LIKE ?';
            $params[] = '%'.$q.'%';
        }

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} s WHERE {$where}",
            $params
        )->fetchColumn();

        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $rows = $this->database->query(
            "SELECT s.id, s.email, s.user_id, s.created_at, u.username
             FROM {$this->getTable()} s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$where}
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /**
     * Remove a subscriber by row id, scoped to the blog so an owner can only
     * delete their own blog's subscribers.
     */
    public function deleteByIdForBlog(int $id, int $blogId): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE id = ? AND blog_id = ? LIMIT 1",
            [$id, $blogId]
        ) > 0;
    }

    /**
     * One page of the blogs the reader follows.
     *
     * Grouped by blog, not by row. A reader who subscribed once as a guest and
     * again after signing up owns two rows for one blog, and the page is a list
     * of blogs; the unsubscribe is scoped by blog for the same reason. An
     * unavailable blog is still listed, with its status, because a subscription
     * you cannot see is a subscription you cannot cancel.
     *
     * @param  int  $userId  Viewer
     * @param  string  $email  The viewer's own address
     * @param  int  $page  1-based page index
     * @param  int  $perPage  Rows per page
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function pageForUser(int $userId, string $email, int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $from = "FROM {$this->getTable()} s
                 INNER JOIN blogs b ON b.id = s.blog_id
                 WHERE s.user_id = ? OR s.email = ?";

        $total = (int) $this->database
            ->query("SELECT COUNT(DISTINCT b.id) {$from}", [$userId, $email])
            ->fetchColumn();

        $items = $this->database->query(
            "SELECT b.id AS blog_id, b.blog_name, b.blog_slug, b.description,
                    b.status AS blog_status,
                    MIN(s.created_at) AS subscribed_at
             {$from}
             GROUP BY b.id, b.blog_name, b.blog_slug, b.description, b.status
             ORDER BY subscribed_at DESC, b.id DESC
             LIMIT ".(int) $perPage.' OFFSET '.(int) $offset,
            [$userId, $email]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Attach the account to subscriptions it owns only by email.
     *
     * Called when the reader looks at their list, so the orphan state heals
     * itself instead of lasting forever. Without it a subscription made before
     * signing up stays invisible to every query that matches on user_id alone.
     *
     * @return int Rows claimed
     */
    public function claimOrphansForUser(int $userId, string $email): int
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()} SET user_id = ? WHERE email = ? AND user_id IS NULL",
            [$userId, $email]
        );
    }

    /**
     * Drop every subscription the reader holds on one blog.
     *
     * Scoped by the same either-identity rule as the list, so what the page
     * shows and what the button removes can never disagree.
     *
     * @return bool True when at least one row went away
     */
    public function deleteForUserAndBlog(int $blogId, int $userId, string $email): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE blog_id = ? AND (user_id = ? OR email = ?)",
            [$blogId, $userId, $email]
        ) > 0;
    }
}
