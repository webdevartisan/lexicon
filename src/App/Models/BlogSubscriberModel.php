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
     * Idempotent: re-subscribing an existing address succeeds quietly.
     *
     * @return bool True when subscribed (new or already present)
     */
    public function subscribe(int $blogId, string $email, ?int $userId = null): bool
    {
        try {
            $this->database->query(
                "INSERT INTO {$this->getTable()} (blog_id, user_id, email, token) VALUES (?, ?, ?, ?)",
                [$blogId, $userId, $email, bin2hex(random_bytes(32))]
            );
        } catch (\PDOException $e) {
            // Unique key on (blog_id, email): already subscribed is a success
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

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
}
