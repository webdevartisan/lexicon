<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-blog index of uploaded images.
 *
 * Files live under /storage/uploads/... This model just records
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
     *
     * @param  array<string, mixed>  $filters  q, source, sort, limit, offset
     * @return array<int, array<string, mixed>> Media rows for the page
     */
    public function listForBlog(int $blogId, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($blogId, $filters);

        $order = match ($filters['sort'] ?? 'newest') {
            'oldest' => 'created_at ASC',
            'largest' => 'size_bytes DESC',
            default => 'created_at DESC',
        };

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 24)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = 'SELECT * FROM '.$this->getTable()
            .' WHERE '.implode(' AND ', $where)
            .' ORDER BY '.$order
            .' LIMIT '.$limit.' OFFSET '.$offset;

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param  array<string, mixed>  $filters  Same q/source/usage filters as listForBlog()
     */
    public function countForBlog(int $blogId, array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($blogId, $filters);

        $sql = 'SELECT COUNT(*) FROM '.$this->getTable()
            .' WHERE '.implode(' AND ', $where);

        return (int) $this->database->query($sql, $params)->fetchColumn();
    }

    /**
     * Build the shared WHERE fragments and bound parameters for browse/count.
     *
     * Supports q (filename/name LIKE), source (enum), and usage='unused' which
     * excludes anything referenced by a post (featured/social/inline) or a branding
     * slot — a NOT IN over the exact-match columns plus a NOT EXISTS for inline body
     * references.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: array<int, string>, 1: array<int, mixed>} [where fragments, params]
     */
    private function buildFilters(int $blogId, array $filters): array
    {
        $table = $this->getTable();
        $where = ['blog_id = ?'];
        $params = [$blogId];

        if (!empty($filters['q'])) {
            $where[] = '(filename LIKE ? OR original_name LIKE ?)';
            $like = '%'.$filters['q'].'%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['source'])) {
            $where[] = 'source = ?';
            $params[] = $filters['source'];
        }

        if (($filters['usage'] ?? '') === 'unused') {
            $where[] = 'url NOT IN ('
                .'SELECT featured_image FROM posts WHERE blog_id = ? AND featured_image IS NOT NULL'
                .' UNION SELECT og_image FROM posts WHERE blog_id = ? AND og_image IS NOT NULL'
                .' UNION SELECT twitter_image FROM posts WHERE blog_id = ? AND twitter_image IS NOT NULL'
                .' UNION SELECT banner_path FROM blog_settings WHERE blog_id = ? AND banner_path IS NOT NULL'
                .' UNION SELECT logo_path FROM blog_settings WHERE blog_id = ? AND logo_path IS NOT NULL'
                .' UNION SELECT favicon_path FROM blog_settings WHERE blog_id = ? AND favicon_path IS NOT NULL'
                .') AND NOT EXISTS ('
                ."SELECT 1 FROM posts p WHERE p.blog_id = ? AND p.content LIKE CONCAT('%', {$table}.url, '%')"
                .')';
            array_push($params, $blogId, $blogId, $blogId, $blogId, $blogId, $blogId, $blogId);
        }

        return [$where, $params];
    }

    /**
     * Look up a single item but only if it belongs to the given blog,
     * so a caller can't reach into another blog's library by id.
     *
     * @return array<string, mixed>|null Media row or null if not found
     */
    public function findForBlog(int $id, int $blogId): ?array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' WHERE id = ? AND blog_id = ? LIMIT 1';
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
    /**
     * Fetch a row by its disk path within a blog, or null if not indexed.
     *
     * @return array<string, mixed>|null
     */
    public function findByPathForBlog(int $blogId, string $diskPath): ?array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' WHERE blog_id = ? AND disk_path = ? LIMIT 1';

        return $this->database->query($sql, [$blogId, $diskPath])->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function existsByPath(int $blogId, string $diskPath): bool
    {
        $sql = 'SELECT 1 FROM '.$this->getTable().' WHERE blog_id = ? AND disk_path = ? LIMIT 1';

        return (bool) $this->database->query($sql, [$blogId, $diskPath])->fetchColumn();
    }

    /**
     * @param  array<string, mixed>  $data  Media row to insert
     */
    public function createRecord(array $data): int
    {
        $this->insert($data);

        return (int) $this->getInsertID();
    }

    public function deleteForBlog(int $id, int $blogId): bool
    {
        $sql = 'DELETE FROM '.$this->getTable().' WHERE id = ? AND blog_id = ?';
        $stmt = $this->database->query($sql, [$id, $blogId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update selected columns of a blog-scoped media row.
     *
     * @param  array<string, mixed>  $data  Column => value pairs to write
     *
     * @throws \InvalidArgumentException If a column name is not a valid identifier
     */
    public function updateForBlog(int $id, int $blogId, array $data): bool
    {
        if ($data === []) {
            return false;
        }

        $sets = [];
        $params = [];
        foreach ($data as $col => $value) {
            // validate column names to prevent SQL injection via dynamic keys
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                throw new \InvalidArgumentException("Invalid column name '{$col}' in updateForBlog.");
            }
            $sets[] = "{$col} = ?";
            $params[] = $value;
        }

        $params[] = $id;
        $params[] = $blogId;

        $sql = 'UPDATE '.$this->getTable().' SET '.implode(', ', $sets).' WHERE id = ? AND blog_id = ?';

        return $this->database->query($sql, $params)->rowCount() >= 0;
    }
}
