<?php

declare(strict_types=1);

namespace App\Models;

class CommentModel extends AppModel
{
    protected ?string $table = 'comments';

    private const ALLOWED_STATUSES = ['pending', 'approved', 'spam'];

    public function forPost(int $postId): array
    {
        $sql = "SELECT
                    c.*,
                    COALESCE(
                        u.display_name_cached,
                        NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                        u.username
                    ) AS user_name
                FROM {$this->getTable()} c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.post_id = ? AND c.status = 'approved'
                ORDER BY c.created_at ASC";

        $stmt = $this->database->query($sql, [$postId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countByBlogIdAndStatus(int $blogId, string $status): int
    {
        $sql = "SELECT COUNT(*)
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                WHERE p.blog_id = ? AND c.status = ?";

        $stmt = $this->database->query($sql, [$blogId, $status]);

        return (int) $stmt->fetchColumn();
    }

    public function findByBlogIdWithFilters(
        int $blogId,
        string $status = '',
        string $q = '',
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = ['p.blog_id = :blog_id'];
        $params = [':blog_id' => $blogId];

        if ($status !== '') {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if ($q !== '') {
            $where[] = '(c.content LIKE :q_content OR p.title LIKE :q_title)';
            $params[':q_content'] = '%'.$q.'%';
            $params[':q_title'] = '%'.$q.'%';
        }

        $whereSql = 'WHERE '.implode(' AND ', $where);

        $countSql = "SELECT COUNT(*)
                     FROM {$this->getTable()} c
                     INNER JOIN posts p ON c.post_id = p.id
                     {$whereSql}";

        $countStmt = $this->database->query($countSql, $params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    c.*,
                    p.title AS post_title,
                    p.slug AS post_slug,
                    p.blog_id,
                    b.blog_slug,
                    COALESCE(
                        u.display_name_cached,
                        NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                        u.username,
                        'Anonymous'
                    ) AS user_name,
                    u.email AS user_email
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users u ON c.user_id = u.id
                {$whereSql}
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $totalPages = (int) ceil($total / $perPage);

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    public function updateStatus(int $id, string $status): bool
    {
        $this->assertValidStatus($status);

        $sql = "UPDATE {$this->getTable()} SET status = ? WHERE id = ?";
        $affected = $this->database->execute($sql, [$status, $id]);

        return $affected > 0;
    }

    public function blogIdForComment(int $commentId): ?int
    {
        $sql = "SELECT p.blog_id
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                WHERE c.id = ?
                LIMIT 1";

        $stmt = $this->database->query($sql, [$commentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int) $row['blog_id'] : null;
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if ($ids === []) {
            return 0;
        }

        $this->assertValidStatus($status);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$this->getTable()} SET status = ? WHERE id IN ({$placeholders})";

        return (int) $this->database->execute($sql, array_merge([$status], $ids));
    }

    public function bulkDelete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM {$this->getTable()} WHERE id IN ({$placeholders})";

        return (int) $this->database->execute($sql, $ids);
    }

    public function deleteById(int $id): bool
    {
        $sql = "DELETE FROM {$this->getTable()} WHERE id = ?";
        $affected = $this->database->execute($sql, [$id]);

        return $affected > 0;
    }

    public function byUser(int $userId): array
    {
        $sql = "SELECT c.*, p.title AS post_title
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC";

        $stmt = $this->database->query($sql, [$userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function create(array $data): int|false
    {
        return $this->insert($data);
    }

    public function countForPost(int $postId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE post_id = ?";
        $stmt = $this->database->query($sql, [$postId]);

        return (int) $stmt->fetchColumn();
    }

    public function countByBlogId(int $blogId): int
    {
        $sql = "SELECT COUNT(*)
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                WHERE p.blog_id = ?";

        $stmt = $this->database->query($sql, [$blogId]);

        return (int) $stmt->fetchColumn();
    }

    public function countApprovedByBlogId(int $blogId): int
    {
        return $this->countByBlogIdAndStatus($blogId, 'approved');
    }

    public function recentForAuthor(int $authorId, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "SELECT
                    c.id,
                    c.post_id,
                    c.content,
                    c.status,
                    c.created_at,
                    p.title AS post_title,
                    p.slug AS post_slug,
                    p.blog_id,
                    b.blog_slug,
                    COALESCE(
                        u.display_name_cached,
                        NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                        u.username,
                        'Anonymous'
                    ) AS user_name
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE p.author_id = ? AND c.status = 'pending'
                ORDER BY c.created_at DESC
                LIMIT {$limit}";

        $stmt = $this->database->query($sql, [$authorId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function deleteByPostId(int $postId): bool
    {
        $sql = "DELETE FROM {$this->getTable()} WHERE post_id = ?";
        $affected = $this->database->execute($sql, [$postId]);

        return $affected > 0;
    }

    private function assertValidStatus(string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
    }
}
