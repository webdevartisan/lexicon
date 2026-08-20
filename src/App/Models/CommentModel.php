<?php

declare(strict_types=1);

namespace App\Models;

class CommentModel extends AppModel
{
    protected ?string $table = 'comments';

    private const ALLOWED_STATUSES = ['pending', 'approved', 'spam'];

    private const ALLOWED_REMOVERS = ['author', 'moderator'];

    public const SORT_TOP = 'top';

    public const SORT_NEW = 'new';

    public const ALLOWED_SORTS = [self::SORT_TOP, self::SORT_NEW];

    /**
     * Deepest a reply is allowed to sit, counting a top-level comment as 0.
     *
     * Four levels is where YouTube stops and it is about where a phone runs
     * out of horizontal room. Past it a reply becomes a sibling of what it
     * answered and says so with an @mention instead of indenting further.
     */
    public const MAX_DEPTH = 3;

    /**
     * Approved comments for a post as a flat list, oldest first.
     *
     * `author_profile_slug` and `author_avatar` are null for guest commenters
     * and for registered commenters whose profile is private. A private profile
     * gets the initial-letter fallback rather than their photo: hiding the
     * profile and then publishing the face attached to it would be odd.
     *
     * Removed comments stay in the result as tombstones so the replies hanging
     * off them survive.
     *
     * `answered_id` / `parent_name` carry what a reply was aimed at, which is
     * its parent except where the depth cap made it a sibling instead. Position
     * alone identifies the target only while the indent is still stepping; the
     * mention is what keeps the conversation followable after that.
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
                    NULLIF(up.slug, '') AS author_profile_slug,
                    up.avatar_url AS author_avatar,
                    COALESCE(
                        pu.display_name_cached,
                        NULLIF(CONCAT_WS(' ', pu.first_name, pu.last_name), ' '),
                        pu.username
                    ) AS parent_name,
                    COALESCE(c.reply_to_comment_id, c.parent_comment_id) AS answered_id,
                    parent.deleted_at AS answered_deleted_at,
                    COALESCE(
                        piu.display_name_cached,
                        NULLIF(CONCAT_WS(' ', piu.first_name, piu.last_name), ' '),
                        piu.username
                    ) AS pinned_by_name
                FROM {$this->getTable()} c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN user_profiles up ON up.user_id = c.user_id AND up.is_public = 1
                LEFT JOIN {$this->getTable()} parent
                    ON parent.id = COALESCE(c.reply_to_comment_id, c.parent_comment_id)
                -- The deleted check sits on the user join, not the comment one:
                -- a removed parent must not hand its author's name to the
                -- mention, but the reply still needs to know it is there.
                LEFT JOIN users pu ON pu.id = parent.user_id AND parent.deleted_at IS NULL
                LEFT JOIN users piu ON piu.id = c.pinned_by
                WHERE c.post_id = ? AND c.status = 'approved'
                ORDER BY c.created_at ASC";

        $stmt = $this->database->query($sql, [$postId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Approved comments as a nested tree, ready to render.
     *
     * Nesting is unbounded in the data. Capping it here would repeat the
     * mistake the old two-level flattening made: a reply gets re-homed under a
     * stranger and loses the thing it was answering. The view caps how far the
     * indent steps instead, which is where the constraint actually lives.
     *
     * @param  int  $postId  Post whose thread to build
     * @param  string  $sort  self::SORT_TOP or self::SORT_NEW, applied to roots only
     * @return array<int, array<string, mixed>>
     */
    public function forPostThreaded(int $postId, string $sort = self::SORT_TOP): array
    {
        $sort = in_array($sort, self::ALLOWED_SORTS, true) ? $sort : self::SORT_TOP;

        // localized=false: a comment thread reads the same in every locale.
        // Busted from create/updateStatus/delete when this post's comments change.
        return fragment()->rememberData(
            'post-comments:'.$postId.':'.$sort,
            fn (): array => $this->buildThread($postId, $sort),
            3600,
            false
        );
    }

    /**
     * Assemble the flat query result into a tree.
     *
     * Rows are indexed first, then hung on their parents walking backwards, so
     * a child is always finished before the parent that will carry it. A reply
     * whose parent is missing from the set (held for moderation, or on another
     * post) stays hidden rather than being promoted to the top level, where it
     * would read as a statement rather than an answer.
     *
     * @param  int  $postId  Post id
     * @param  string  $sort  Ordering for top-level comments
     * @return array<int, array<string, mixed>> Roots, each carrying a `replies` array
     */
    private function buildThread(int $postId, string $sort): array
    {
        $rows = [];
        foreach ($this->forPost($postId) as $comment) {
            $comment['replies'] = [];
            $rows[(int) $comment['id']] = $comment;
        }

        foreach (array_reverse(array_keys($rows)) as $id) {
            $parentId = (int) ($rows[$id]['parent_comment_id'] ?? 0);

            if ($parentId > 0 && isset($rows[$parentId])) {
                array_unshift($rows[$parentId]['replies'], $rows[$id]);
                unset($rows[$id]);
            }
        }

        $roots = [];
        foreach ($rows as $row) {
            // Anything still holding a parent id is an orphan, not a root.
            if (empty($row['parent_comment_id'])) {
                $roots[] = $row;
            }
        }

        return $this->sortRoots($roots, $sort);
    }

    /**
     * Order top-level comments, pinned first whatever the sort.
     *
     * Replies keep their chronological order at every depth: a conversation
     * only reads correctly forwards.
     *
     * @param  array<int, array<string, mixed>>  $roots  Top-level comments
     * @param  string  $sort  self::SORT_TOP or self::SORT_NEW
     * @return array<int, array<string, mixed>>
     */
    private function sortRoots(array $roots, string $sort): array
    {
        $score = static fn (array $c): int => (int) ($c['upvotes'] ?? 0) - (int) ($c['downvotes'] ?? 0);

        usort($roots, static function (array $a, array $b) use ($sort, $score): int {
            $pinned = (int) !empty($b['pinned_at']) <=> (int) !empty($a['pinned_at']);
            if ($pinned !== 0) {
                return $pinned;
            }

            if ($sort === self::SORT_NEW) {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            }

            // Equal scores fall back to newest, so a thread of untouched
            // comments still puts fresh conversation where people look.
            return $score($b) <=> $score($a)
                ?: strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $roots;
    }

    /**
     * Pin one comment on a post, replacing whatever was pinned before.
     *
     * One pin per post: a second "most important" comment is not a thing.
     * Only a top-level, non-removed comment qualifies.
     */
    public function pin(int $commentId, int $postId, int $pinnedBy): bool
    {
        return (bool) $this->transaction(function () use ($commentId, $postId, $pinnedBy): bool {
            $this->database->execute(
                "UPDATE {$this->getTable()} SET pinned_at = NULL, pinned_by = NULL
                 WHERE post_id = ? AND pinned_at IS NOT NULL",
                [$postId]
            );

            $affected = $this->database->execute(
                "UPDATE {$this->getTable()}
                 SET pinned_at = NOW(), pinned_by = ?
                 WHERE id = ? AND post_id = ? AND parent_comment_id IS NULL AND deleted_at IS NULL",
                [$pinnedBy, $commentId, $postId]
            );

            $this->forgetThreadCache($postId);

            return $affected > 0;
        });
    }

    /**
     * Clear the pin on a post.
     */
    public function unpin(int $postId): bool
    {
        $affected = $this->database->execute(
            "UPDATE {$this->getTable()} SET pinned_at = NULL, pinned_by = NULL
             WHERE post_id = ? AND pinned_at IS NOT NULL",
            [$postId]
        );

        if ($affected > 0) {
            $this->forgetThreadCache($postId);
        }

        return $affected > 0;
    }

    /**
     * Which comment a reply aimed at $targetId should actually hang from.
     *
     * Below the cap that is the target itself. At or past it the reply becomes
     * a sibling of the target rather than another indent, and the caller records
     * the real target in reply_to_comment_id so the @mention can still name it.
     *
     * @param  int  $targetId  Comment being replied to
     * @return int Comment id to store as parent_comment_id
     */
    public function parentForReply(int $targetId): int
    {
        $chain = [$targetId];
        $seen = [$targetId => true];
        $current = $targetId;

        // Guard against a cycle in parent_comment_id rather than trusting the
        // data: one bad row would otherwise spin here forever.
        while (($parent = $this->parentIdOf($current)) !== null && !isset($seen[$parent])) {
            $chain[] = $parent;
            $seen[$parent] = true;
            $current = $parent;
        }

        $depth = count($chain) - 1;

        if ($depth < self::MAX_DEPTH) {
            return $targetId;
        }

        // Step back up the chain until the new reply lands exactly at the cap.
        return $chain[$depth - self::MAX_DEPTH + 1];
    }

    /**
     * Approved comment usable as a reply target on the given post.
     *
     * Tombstones are excluded: a removed comment still renders so its replies
     * keep their context, but it is no longer something to answer.
     *
     * @return array<string, mixed>|null
     */
    public function findApprovedParent(int $commentId, int $postId): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()}
                WHERE id = ? AND post_id = ? AND status = 'approved' AND deleted_at IS NULL
                LIMIT 1";

        $row = $this->database->query($sql, [$commentId, $postId])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Whether anything answers this comment.
     *
     * Both columns count. parent_comment_id is the structural one, but a reply
     * held at the depth cap is a sibling of what it answered and names its
     * target in reply_to_comment_id alone; removing that target outright would
     * leave the reply pointing at nothing, which is exactly the orphan the
     * tombstone exists to prevent.
     *
     * Replies in every moderation state count too: a hard delete cascades down
     * parent_comment_id, so a pending reply would go with its parent just as
     * surely as an approved one.
     */
    public function hasReplies(int $id): bool
    {
        $sql = "SELECT 1 FROM {$this->getTable()}
                WHERE parent_comment_id = ? OR reply_to_comment_id = ?
                LIMIT 1";

        return (bool) $this->database->query($sql, [$id, $id])->fetchColumn();
    }

    /**
     * Replace a comment with a tombstone, keeping its place in the thread.
     *
     * The body is blanked rather than kept and hidden: an author removing
     * their own words should not leave them readable in the moderation queue.
     * Votes go with it, so a removed comment cannot carry a score.
     *
     * @param  int  $id  Comment to remove
     * @param  string  $by  'author' or 'moderator', drives the tombstone wording
     */
    public function softDelete(int $id, string $by): bool
    {
        $this->assertValidRemover($by);

        $postId = $this->postIdForComment($id);

        // NOW(), not UTC_TIMESTAMP(): a TIMESTAMP column reads its input in the
        // session timezone, so NOW() agrees with created_at's default whatever
        // that session happens to be.
        // The pin goes with it: a tombstone at the top of the thread is the
        // worst possible thing to lead with.
        $sql = "UPDATE {$this->getTable()}
                SET content = '', deleted_at = NOW(), deleted_by = ?,
                    upvotes = 0, downvotes = 0, reports_count = 0, pinned_at = NULL, pinned_by = NULL
                WHERE id = ? AND deleted_at IS NULL";

        $affected = $this->database->execute($sql, [$by, $id]);

        if ($affected > 0) {
            $this->database->execute('DELETE FROM comment_votes WHERE comment_id = ?', [$id]);
            $this->database->execute('DELETE FROM comment_reports WHERE comment_id = ?', [$id]);

            if ($postId !== null) {
                $this->forgetThreadCache($postId);
            }
        }

        return $affected > 0;
    }

    /**
     * Thread root of a comment, or null when it is already top-level.
     */
    public function parentIdOf(int $id): ?int
    {
        $sql = "SELECT parent_comment_id FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $row = $this->database->query($sql, [$id])->fetch(\PDO::FETCH_ASSOC);

        return empty($row['parent_comment_id']) ? null : (int) $row['parent_comment_id'];
    }

    /**
     * How many comments on this blog readers have flagged.
     */
    public function countReportedByBlogId(int $blogId): int
    {
        $sql = "SELECT COUNT(*)
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                WHERE p.blog_id = ? AND c.reports_count > 0";

        return (int) $this->database->query($sql, [$blogId])->fetchColumn();
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

        // 'reported' is a view of the queue, not a moderation state: a flagged
        // comment is still pending or approved until somebody rules on it.
        if ($status === 'reported') {
            $where[] = 'c.reports_count > 0';
        } elseif ($status !== '') {
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
                ORDER BY c.reports_count DESC, c.created_at DESC
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

        // 'reported' is a view of the queue, not a moderation state: a flagged
        // comment is still pending or approved until somebody rules on it.
        if ($status === 'reported') {
            $where[] = 'c.reports_count > 0';
        } elseif ($status !== '') {
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
                ORDER BY c.reports_count DESC, c.created_at DESC
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

        // Reported cuts across the statuses rather than being one of them, so
        // it is counted separately and never folded into 'all'.
        $counts['reported'] = (int) $this->database
            ->query("SELECT COUNT(*) FROM {$this->getTable()} WHERE reports_count > 0")
            ->fetchColumn();

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
     * Public because a vote invalidates it too: the totals ride inside the
     * cached rows and "Top" orders by them, so a stale thread would show both
     * the wrong number and the wrong order.
     *
     * Clears the keys written by forPostThreaded() (localized=false).
     *
     * @param  int  $postId  Post whose thread cache to clear
     */
    public function forgetThreadCache(int $postId): void
    {
        // One entry per sort order, so dropping a single key would leave the
        // other view of the same thread serving a stale tree.
        foreach (self::ALLOWED_SORTS as $sort) {
            fragment()->forget('post-comments:'.$postId.':'.$sort, false);
        }
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
     * Removed comments are left out: a tombstone is a placeholder the thread
     * needs, not something its author should still find in their own activity.
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
                WHERE c.user_id = ? AND c.deleted_at IS NULL
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
                  AND r.deleted_at IS NULL
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

    private function assertValidRemover(string $by): void
    {
        if (!in_array($by, self::ALLOWED_REMOVERS, true)) {
            throw new \InvalidArgumentException("Invalid remover: {$by}");
        }
    }
}
