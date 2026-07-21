<?php

declare(strict_types=1);

namespace App\Models;

class CommentModel extends AppModel
{
    protected ?string $table = 'comments';

    private const ALLOWED_STATUSES = ['pending', 'approved', 'spam'];

    /**
     * Approved comments for a post, oldest first.
     *
     * `author_profile_slug` is null for guest commenters and for registered
     * commenters whose profile is private or has no slug.
     *
     * @return array<int, array<string, mixed>> Approved comments with user_name, oldest first
     */
    public function forPost(int $postId): array
    {
        // is_public belongs in the ON clause: in WHERE it would turn the LEFT
        // JOIN into an inner join and drop guest comments entirely
        $sql = "SELECT
                    c.*,
                    COALESCE(
                        u.display_name_cached,
                        NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                        u.username
                    ) AS user_name,
                    NULLIF(up.slug, '') AS author_profile_slug
                FROM {$this->getTable()} c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN user_profiles up ON up.user_id = c.user_id AND up.is_public = 1
                WHERE c.post_id = ? AND c.status = 'approved'
                ORDER BY c.created_at ASC";

        $stmt = $this->database->query($sql, [$postId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Approved comments grouped TikTok-style: top-level rows carrying a
     * 'replies' array, both oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPostThreaded(int $postId): array
    {
        $cacheKey = 'post-comments:'.$postId;

        // localized=false: a comment thread reads the same in every locale.
        // Busted from create/updateStatus/delete when this post's comments change.
        return fragment()->rememberData(
            $cacheKey,
            fn (): array => $this->buildThread($postId),
            3600,
            false
        );
    }

    /**
     * Assemble a post's approved comments into top-level entries with replies.
     *
     * @param  int  $postId  Post id
     * @return array<int, array<string, mixed>> Top-level comments, each with a 'replies' array
     */
    private function buildThread(int $postId): array
    {
        $threaded = [];
        $replies = [];

        foreach ($this->forPost($postId) as $comment) {
            if (empty($comment['parent_comment_id'])) {
                $comment['replies'] = [];
                $threaded[$comment['id']] = $comment;
            } else {
                $replies[] = $comment;
            }
        }

        foreach ($replies as $reply) {
            $parentId = (int) $reply['parent_comment_id'];

            // Parent may be unapproved or deleted; orphaned replies stay hidden
            if (isset($threaded[$parentId])) {
                $threaded[$parentId]['replies'][] = $reply;
            }
        }

        return array_values($threaded);
    }

    /**
     * Approved comment usable as a reply target on the given post.
     *
     * @return array<string, mixed>|null
     */
    public function findApprovedParent(int $commentId, int $postId): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()}
                WHERE id = ? AND post_id = ? AND status = 'approved'
                LIMIT 1";

        $row = $this->database->query($sql, [$commentId, $postId])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
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

    /**
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
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
                    u.email AS user_email,
                    parent.content AS parent_content
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN {$this->getTable()} parent ON parent.id = c.parent_comment_id
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

    /**
     * Site-wide comment listing with filters for the admin moderation queue.
     *
     * Mirrors findByBlogIdWithFilters but spans every blog, so administrators
     * can moderate the whole site from one screen.
     *
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function findAllWithFilters(
        string $status = '',
        string $q = '',
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if ($q !== '') {
            $where[] = '(c.content LIKE :q_content OR p.title LIKE :q_title)';
            $params[':q_content'] = '%'.$q.'%';
            $params[':q_title'] = '%'.$q.'%';
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*)
                     FROM {$this->getTable()} c
                     INNER JOIN posts p ON c.post_id = p.id
                     {$whereSql}";

        $total = (int) $this->database->query($countSql, $params)->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT
                    c.*,
                    p.title AS post_title,
                    p.slug AS post_slug,
                    p.blog_id,
                    b.blog_name,
                    b.blog_slug,
                    COALESCE(
                        u.display_name_cached,
                        NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                        u.username,
                        'Anonymous'
                    ) AS user_name,
                    u.email AS user_email,
                    parent.content AS parent_content
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN {$this->getTable()} parent ON parent.id = c.parent_comment_id
                {$whereSql}
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $rows = $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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

    /**
     * Site-wide comment counts keyed by status, plus an 'all' total.
     *
     * @return array{all: int, pending: int, approved: int, spam: int}
     */
    public function countsByStatus(): array
    {
        $counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'spam' => 0];

        $sql = "SELECT status, COUNT(*) AS cnt FROM {$this->getTable()} GROUP BY status";
        foreach ($this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['cnt'];
            }
            $counts['all'] += (int) $row['cnt'];
        }

        return $counts;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $this->assertValidStatus($status);

        $sql = "UPDATE {$this->getTable()} SET status = ? WHERE id = ?";
        $affected = $this->database->execute($sql, [$status, $id]);

        if ($affected > 0) {
            // A moderated comment appears/disappears in the public thread.
            $postId = $this->postIdForComment($id);
            if ($postId !== null) {
                $this->forgetThreadCache($postId);
            }
        }

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

    /**
     * Resolve which post a comment belongs to.
     *
     * Moderation methods only receive a comment id, so they use this to find
     * the post whose cached thread needs dropping.
     *
     * @param  int  $commentId  Comment id
     * @return int|null Owning post id, or null if the comment is gone
     */
    private function postIdForComment(int $commentId): ?int
    {
        $sql = "SELECT post_id FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $row = $this->database->query($sql, [$commentId])->fetch(\PDO::FETCH_ASSOC);

        return $row ? (int) $row['post_id'] : null;
    }

    /**
     * Distinct owning post ids for a set of comments, for bulk moderation.
     *
     * @param  int[]  $commentIds  Comment ids
     * @return int[] Unique post ids touched by those comments
     */
    private function postIdsForComments(array $commentIds): array
    {
        if ($commentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        $sql = "SELECT DISTINCT post_id FROM {$this->getTable()} WHERE id IN ({$placeholders})";
        $rows = $this->database->query($sql, $commentIds)->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): int => (int) $r['post_id'], $rows);
    }

    /**
     * Drop a post's cached comment thread after it changes.
     *
     * Clears the key written by forPostThreaded() (localized=false).
     *
     * @param  int  $postId  Post whose thread cache to clear
     */
    private function forgetThreadCache(int $postId): void
    {
        fragment()->forget('post-comments:'.$postId, false);
    }

    /**
     * @param  int[]  $ids  Comment IDs to update
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if ($ids === []) {
            return 0;
        }

        $this->assertValidStatus($status);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$this->getTable()} SET status = ? WHERE id IN ({$placeholders})";
        $affected = (int) $this->database->execute($sql, array_merge([$status], $ids));

        if ($affected > 0) {
            foreach ($this->postIdsForComments($ids) as $postId) {
                $this->forgetThreadCache($postId);
            }
        }

        return $affected;
    }

    /**
     * @param  int[]  $ids  Comment IDs to delete
     */
    public function bulkDelete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        // Resolve owning posts before the rows are gone.
        $postIds = $this->postIdsForComments($ids);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM {$this->getTable()} WHERE id IN ({$placeholders})";
        $affected = (int) $this->database->execute($sql, $ids);

        if ($affected > 0) {
            foreach ($postIds as $postId) {
                $this->forgetThreadCache($postId);
            }
        }

        return $affected;
    }

    public function deleteById(int $id): bool
    {
        // Resolve the owning post before the row is gone.
        $postId = $this->postIdForComment($id);

        $sql = "DELETE FROM {$this->getTable()} WHERE id = ?";
        $affected = $this->database->execute($sql, [$id]);

        if ($affected > 0 && $postId !== null) {
            $this->forgetThreadCache($postId);
        }

        return $affected > 0;
    }

    /**
     * @return array<int, array<string, mixed>> User's comments with post_title, newest first
     */
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

    /**
     * The user's comments with enough post/blog context to link back to them.
     *
     * @return array<int, array<string, mixed>> Newest first
     */
    public function byUserWithContext(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "SELECT c.id, c.content, c.status, c.created_at, c.parent_comment_id,
                       p.title AS post_title, p.slug AS post_slug,
                       b.blog_slug, b.blog_name
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                LEFT JOIN blogs b ON p.blog_id = b.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC
                LIMIT {$limit}";

        return $this->database->query($sql, [$userId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Approved replies other people left on the user's comments.
     *
     * @return array<int, array<string, mixed>> Newest first
     */
    public function repliesToUser(int $userId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));

        $sql = "SELECT r.id, r.content, r.created_at, r.parent_comment_id,
                       p.title AS post_title, p.slug AS post_slug,
                       b.blog_slug, b.blog_name,
                       COALESCE(
                           u.display_name_cached,
                           NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                           u.username,
                           'A guest'
                       ) AS author_name
                FROM {$this->getTable()} r
                INNER JOIN {$this->getTable()} parent ON r.parent_comment_id = parent.id
                INNER JOIN posts p ON r.post_id = p.id
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users u ON r.user_id = u.id
                WHERE parent.user_id = ?
                  AND (r.user_id IS NULL OR r.user_id != ?)
                  AND r.status = 'approved'
                ORDER BY r.created_at DESC
                LIMIT {$limit}";

        return $this->database->query($sql, [$userId, $userId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null Comment row or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * @param  array<string, mixed>  $data  Comment data to insert
     */
    public function create(array $data): int|false
    {
        $id = $this->insert($data);

        if ($id !== false && !empty($data['post_id'])) {
            $this->forgetThreadCache((int) $data['post_id']);
        }

        return $id;
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

    /**
     * @return array<int, array<string, mixed>> Pending comments on the author's posts
     */
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

        if ($affected > 0) {
            $this->forgetThreadCache($postId);
        }

        return $affected > 0;
    }

    private function assertValidStatus(string $status): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
    }
}
