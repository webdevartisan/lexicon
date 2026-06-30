<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-blog index of uploaded images.
 *
 * Files still live under /public/uploads/... — this model just records
 * what's on disk so the library can browse, search and clean them up.
 */
class MediaModel extends AppModel
{
    protected ?string $table = 'media';

    /**
     * Browse a blog's media with optional search + filter + sort.
     *
     * Filters supported: q (filename LIKE), type ('image' for now),
     * source (upload|post_image|branding|backfill), sort (newest|oldest|largest).
     *
     * Pagination uses simple limit/offset — fine for the library where
     * the grid pages 24 at a time.
     */
    public function listForBlog(int $blogId, array $filters = []): array
    {
        $where = ['blog_id = ?'];
        $params = [$blogId];

        if (!empty($filters['q'])) {
            $where[] = '(filename LIKE ? OR original_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['source'])) {
            $where[] = 'source = ?';
            $params[] = $filters['source'];
        }

        $order = match ($filters['sort'] ?? 'newest') {
            'oldest' => 'created_at ASC',
            'largest' => 'size_bytes DESC',
            default => 'created_at DESC',
        };

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 24)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT * FROM ' . $this->getTable()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $order
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countForBlog(int $blogId, array $filters = []): int
    {
        $where = ['blog_id = ?'];
        $params = [$blogId];

        if (!empty($filters['q'])) {
            $where[] = '(filename LIKE ? OR original_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['source'])) {
            $where[] = 'source = ?';
            $params[] = $filters['source'];
        }

        $sql = 'SELECT COUNT(*) FROM ' . $this->getTable()
            . ' WHERE ' . implode(' AND ', $where);

        return (int) $this->database->query($sql, $params)->fetchColumn();
    }

    /**
     * Look up a single item but only if it belongs to the given blog,
     * so a caller can't reach into another blog's library by id.
     */
    public function findForBlog(int $id, int $blogId): ?array
    {
        $sql = 'SELECT * FROM ' . $this->getTable() . ' WHERE id = ? AND blog_id = ? LIMIT 1';
        $stmt = $this->database->query($sql, [$id, $blogId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Has this exact file already been recorded for this blog?
     *
     * Used by the backfill (don't double-insert on re-run) and by
     * register() so existing upload flows don't create duplicate rows
     * when the same file is saved more than once.
     */
    public function existsByPath(int $blogId, string $diskPath): bool
    {
        $sql = 'SELECT 1 FROM ' . $this->getTable() . ' WHERE blog_id = ? AND disk_path = ? LIMIT 1';

        return (bool) $this->database->query($sql, [$blogId, $diskPath])->fetchColumn();
    }

    public function createRecord(array $data): int
    {
        $this->insert($data);

        return (int) $this->getInsertID();
    }

    public function deleteForBlog(int $id, int $blogId): bool
    {
        $sql = 'DELETE FROM ' . $this->getTable() . ' WHERE id = ? AND blog_id = ?';
        $stmt = $this->database->query($sql, [$id, $blogId]);

        return $stmt->rowCount() > 0;
    }
}
