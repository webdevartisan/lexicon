<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Lists and counts posts for the dashboard and the admin area: the filtered
 * post tables, status badges, the review queue and workflow counts.
 *
 * Every row status is included here, drafts and private posts too, so callers
 * scope by blog or author before showing anything.
 */
class PostListingModel extends AppModel
{
    protected ?string $table = 'posts';

    /**
     * Find posts by author with filters and pagination.
     *
     * Separates data retrieval from count query for efficiency and clarity.
     * Returns both paginated results and metadata for navigation.
     *
     * @param  int|null  $authorId  Author user ID, or null for every author
     * @param  int  $page  Current page number (1-based indexing)
     * @param  int  $perPage  Number of records per page
     * @param  int|null  $blogId  Optional blog ID filter
     * @param  string  $status  Optional status filter
     * @param  string  $searchQuery  Optional search term for title/content
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function findByAuthorWithFiltersPagination(
        ?int $authorId,
        int $page = 1,
        int $perPage = 10,
        ?int $blogId = null,
        string $status = '',
        string $searchQuery = '',
        string $sort = 'newest',
        ?int $categoryId = null,
        ?int $tagId = null,
        string $workflowState = '',
        ?string $orderBy = null
    ): array {
        // Validate and sanitize pagination parameters to prevent abuse
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100); // Cap between 1-100 to prevent memory issues

        // Build the WHERE clause and parameters once to follow DRY principle
        [$whereClause, $params] = $this->buildFilterClauses($authorId, $blogId, $status, $searchQuery, $categoryId, $tagId, $workflowState);

        // Get total count first for pagination metadata
        $totalRecords = $this->getTotalCount($whereClause, $params);

        // Calculate offset for LIMIT clause
        $offset = ($page - 1) * $perPage;

        // $orderBy, when given, is a TableSort whitelist body; the named options are the older callers' path.
        $orderBy ??= match ($sort) {
            'oldest' => 'p.published_at ASC',
            'title_asc' => 'p.title ASC',
            'title_desc' => 'p.title DESC',
            default => 'p.published_at DESC',
        };

        // v1 enforces single reviewer per post, so a plain LEFT JOIN is safe (no row multiplication).
        $sql = "SELECT p.*, b.blog_name, c.name AS category_name,
                       au.handle AS author_handle,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                       pr.reviewer_id AS reviewer_id,
                       pr.assigned_at AS reviewer_assigned_at,
                       ru.handle AS reviewer_handle
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users au ON au.id = p.author_id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN post_reviewers pr ON pr.post_id = p.id
                LEFT JOIN users ru ON ru.id = pr.reviewer_id
                {$whereClause}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $data = $stmt->fetchAll() ?: [];

        // Calculate pagination metadata for frontend navigation
        $totalPages = (int) ceil($totalRecords / $perPage);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * Count posts in a blog by workflow_state (any author).
     *
     * Used for editor/owner Team-page workflow-health and Shared-page editor cards.
     */
    public function countByBlogAndWorkflow(int $blogId, string $workflowState): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->getTable()}
                WHERE blog_id = ? AND workflow_state = ?";
        $stmt = $this->database->query($sql, [$blogId, $workflowState]);

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Every post in a blog with the usual dashboard filters (status, workflow,
     * search, sort) and pagination. Author scope is deliberately absent. The
     * caller is expected to be an owner or editor and the surface (per-blog
     * posts index) already gates on that.
     *
     * @return array{data: array<int,array<string,mixed>>, pagination: array<string,mixed>}
     */
    public function findAllInBlogWithFilters(
        int $blogId,
        int $page = 1,
        int $perPage = 12,
        string $status = '',
        string $searchQuery = '',
        string $sort = 'newest',
        string $workflowState = ''
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = 'WHERE p.blog_id = :blog_id';
        $params = [':blog_id' => $blogId];

        if ($status !== '' && in_array($status, PostModel::STATUSES, true)) {
            $where .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        if ($workflowState !== '' && in_array($workflowState, PostModel::WORKFLOW_STATES, true)) {
            $where .= ' AND p.workflow_state = :workflow_state';
            $params[':workflow_state'] = $workflowState;
        }

        if ($searchQuery !== '') {
            $where .= ' AND (p.title LIKE :search_title OR p.content LIKE :search_content)';
            $term = '%'.$searchQuery.'%';
            $params[':search_title'] = $term;
            $params[':search_content'] = $term;
        }

        $orderBy = match ($sort) {
            'oldest' => 'p.published_at ASC',
            'title_asc' => 'p.title ASC',
            'title_desc' => 'p.title DESC',
            default => 'p.published_at DESC',
        };

        $countStmt = $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} p {$where}",
            $params
        );
        $totalRecords = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*, b.blog_name, c.name AS category_name,
                       au.handle AS author_handle,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                       pr.reviewer_id AS reviewer_id,
                       pr.assigned_at AS reviewer_assigned_at,
                       ru.handle AS reviewer_handle
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users au ON au.id = p.author_id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN post_reviewers pr ON pr.post_id = p.id
                LEFT JOIN users ru ON ru.id = pr.reviewer_id
                {$where}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $data = $stmt->fetchAll() ?: [];

        $totalPages = (int) ceil($totalRecords / $perPage);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * Site-wide post counts keyed by status, for the admin overview.
     *
     * @return array<string, int> status => count
     */
    public function countsByStatus(): array
    {
        $counts = [];
        $sql = "SELECT status, COUNT(*) AS cnt FROM {$this->getTable()} GROUP BY status";
        foreach ($this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Per-status totals for an author's posts, for the dashboard filter badges.
     *
     * Same filter semantics as findByAuthorWithFiltersPagination, but one
     * grouped query instead of a count query per status tab.
     *
     * @return array<string, int> Keys: all, published, draft, pending, needs_changes, archived
     */
    public function countsByStatusForAuthor(
        ?int $authorId,
        ?int $blogId = null,
        string $searchQuery = '',
        ?int $categoryId = null,
        ?int $tagId = null
    ): array {
        [$whereClause, $params] = $this->buildFilterClauses(
            $authorId, $blogId, '', $searchQuery, $categoryId, $tagId
        );

        return $this->groupedStatusCounts($whereClause, $params);
    }

    /**
     * Run one GROUP BY status query and shape it into the badge-count array.
     *
     * @param  string  $whereClause  Prepared WHERE clause (aliased as p)
     * @param  array<string, mixed>  $params  Bindings for the WHERE clause
     * @return array<string, int>
     */
    private function groupedStatusCounts(string $whereClause, array $params): array
    {
        $sql = "SELECT p.status, COUNT(*) AS cnt,
                       SUM(CASE WHEN p.workflow_state = 'needs_changes' THEN 1 ELSE 0 END) AS needs_changes
                FROM {$this->getTable()} p
                {$whereClause}
                GROUP BY p.status";

        return $this->shapeStatusCounts($this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Fold GROUP BY status rows into the badge-count array.
     *
     * needs_changes is a workflow_state, not a status (those posts are pending),
     * so it is summed across the groups rather than counted as its own status.
     *
     * @param  array<int, array<string, mixed>>  $rows  Rows with status, cnt and needs_changes
     * @return array<string, int>
     */
    private function shapeStatusCounts(array $rows): array
    {
        $counts = [
            'all' => 0,
            'published' => 0,
            'scheduled' => 0,
            'draft' => 0,
            'pending' => 0,
            'needs_changes' => 0,
            'archived' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (isset($counts[$status])) {
                $counts[$status] = (int) $row['cnt'];
            }
            $counts['needs_changes'] += (int) $row['needs_changes'];
        }

        $counts['all'] = $counts['published'] + $counts['scheduled'] + $counts['draft']
            + $counts['pending'] + $counts['archived'];

        return $counts;
    }

    /**
     * Paginated site-wide post listing for the admin control panel.
     *
     * Same shape as findAllInBlogWithFilters but without the blog constraint,
     * so administrators can search and filter across every blog.
     *
     * @param  int  $page  Current page (1-based)
     * @param  int  $perPage  Rows per page (capped at 100)
     * @param  string  $status  Optional status filter
     * @param  string  $searchQuery  Optional title/content search term
     * @param  int|null  $blogId  Optional single-blog constraint
     * @param  string  $featured  '' | 'home' (featured on the front page) | 'blog' (featured in its blog)
     * @param  string  $visibility  Optional visibility filter
     * @param  string  $orderBy  Whitelisted ORDER BY body from TableSort
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function findAllForAdmin(
        int $page = 1,
        int $perPage = 20,
        string $status = '',
        string $searchQuery = '',
        ?int $blogId = null,
        string $featured = '',
        string $visibility = '',
        string $orderBy = 'p.updated_at DESC'
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = 'WHERE 1=1';
        $params = [];

        if ($status !== '' && in_array($status, PostModel::STATUSES, true)) {
            $where .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        if ($searchQuery !== '') {
            $where .= ' AND (p.title LIKE :search_title OR p.content LIKE :search_content)';
            $term = '%'.$searchQuery.'%';
            $params[':search_title'] = $term;
            $params[':search_content'] = $term;
        }

        if ($blogId !== null) {
            $where .= ' AND p.blog_id = :blog_id';
            $params[':blog_id'] = $blogId;
        }

        // Posts carry two independent featured flags: featured_on_home drives the
        // site front page, is_featured drives the blog's own landing page.
        if ($featured === 'home') {
            $where .= ' AND p.featured_on_home = 1';
        } elseif ($featured === 'blog') {
            $where .= ' AND p.is_featured = 1';
        } elseif ($featured === 'none') {
            $where .= ' AND p.featured_on_home = 0 AND p.is_featured = 0';
        }

        if ($visibility !== '' && in_array($visibility, PostModel::VISIBILITIES, true)) {
            $where .= ' AND p.visibility = :visibility';
            $params[':visibility'] = $visibility;
        }

        $countStmt = $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} p {$where}",
            $params
        );
        $totalRecords = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        // $orderBy comes from a TableSort whitelist, never from raw input.
        $sql = "SELECT p.id, p.title, p.slug, p.status, p.updated_at, p.blog_id,
                       p.featured_on_home, p.is_featured, p.visibility, p.published_at,
                       b.blog_name, b.blog_slug,
                       au.handle AS author_handle,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                       mc.id AS open_case_id, mc.report_count AS open_case_reports
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users au ON au.id = p.author_id
                LEFT JOIN moderation_cases mc ON mc.open_key = CONCAT('post:', p.id)
                {$where}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $data = $stmt->fetchAll() ?: [];

        $totalPages = (int) ceil($totalRecords / $perPage);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * Review-pipeline posts for a blog: ready to publish first, then unassigned submissions.
     *
     * One row per post. Older data can hold several reviewers on one post, so
     * only the latest assignment is shown, the one assignReviewer() would leave.
     *
     * @return array<int, array<string, mixed>> Post rows with author and reviewer info
     */
    public function findReviewQueueForBlog(int $blogId): array
    {
        $sql = "SELECT p.id, p.title, p.slug, p.workflow_state, p.updated_at, p.author_id,
                       au.handle AS author_handle,
                       pr.reviewer_id, ru.handle AS reviewer_handle,
                       pr.assigned_at
                FROM {$this->getTable()} p
                INNER JOIN users au ON au.id = p.author_id
                LEFT JOIN post_reviewers pr ON pr.id = (
                    SELECT MAX(latest.id) FROM post_reviewers latest WHERE latest.post_id = p.id
                )
                LEFT JOIN users ru ON ru.id = pr.reviewer_id
                WHERE p.blog_id = ?
                  AND p.status = 'pending'
                  AND p.workflow_state IN ('in_review', 'needs_changes', 'approved')
                ORDER BY (p.workflow_state = 'approved') DESC, (pr.reviewer_id IS NULL) DESC, p.updated_at DESC";

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Count in_review posts on a blog that have no reviewer assigned.
     *
     * The single number that tells an editor/owner whether someone needs to
     * pick up an unattended submission.
     */
    public function countInReviewUnassigned(int $blogId): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->getTable()} p
                WHERE p.blog_id = ?
                  AND p.workflow_state = 'in_review'
                  AND NOT EXISTS (SELECT 1 FROM post_reviewers pr WHERE pr.post_id = p.id)";
        $stmt = $this->database->query($sql, [$blogId]);

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Build WHERE clause and parameters for filtering.
     *
     * Extracted to a separate method following SRP (Single Responsibility Principle)
     * and to enable reuse between count and data queries.
     *
     * @param  int  $authorId  Author ID to filter by
     * @param  int|null  $blogId  Optional blog ID filter
     * @param  string  $status  Optional status filter
     * @param  string  $searchQuery  Optional search term
     * @return array{string, array<string, mixed>} Tuple of [WHERE clause, parameters]
     */
    private function buildFilterClauses(
        ?int $authorId,
        ?int $blogId,
        string $status,
        string $searchQuery,
        ?int $categoryId = null,
        ?int $tagId = null,
        string $workflowState = ''
    ): array {
        $whereClause = 'WHERE 1 = 1';
        $params = [];

        if ($authorId !== null) {
            $whereClause .= ' AND p.author_id = :author_id';
            $params[':author_id'] = $authorId;
        }

        // Apply blog filter if provided
        if ($blogId !== null) {
            $whereClause .= ' AND p.blog_id = :blog_id';
            $params[':blog_id'] = $blogId;
        }

        // Validate status against whitelist to prevent invalid database queries
        if ($status !== '' && in_array($status, PostModel::STATUSES, true)) {
            $whereClause .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        // Workflow state filter is separate from the status filter (a
        // 'needs_changes' post is pending). Validated against the
        // WORKFLOW_STATES whitelist before reaching SQL.
        if ($workflowState !== '' && in_array($workflowState, PostModel::WORKFLOW_STATES, true)) {
            $whereClause .= ' AND p.workflow_state = :workflow_state';
            $params[':workflow_state'] = $workflowState;
        }

        if ($categoryId !== null) {
            $whereClause .= ' AND p.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        if ($tagId !== null) {
            $whereClause .= ' AND EXISTS (SELECT 1 FROM post_tags pt WHERE pt.post_id = p.id AND pt.tag_id = :tag_id)';
            $params[':tag_id'] = $tagId;
        }

        // Use LIKE for flexible search but be aware this prevents index usage on large tables
        // TODO: Considering full-text search for better performance with large datasets
        if ($searchQuery !== '') {
            $whereClause .= ' AND (p.title LIKE :search_title OR p.content LIKE :search_content)';
            $searchTerm = '%'.$searchQuery.'%';
            $params[':search_title'] = $searchTerm;
            $params[':search_content'] = $searchTerm;
        }

        return [$whereClause, $params];
    }

    /**
     * Get total count of records matching filter criteria.
     *
     * Uses a separate COUNT query instead of SQL_CALC_FOUND_ROWS for better performance
     * in modern MySQL versions (5.7+) per MySQL documentation.
     *
     * @param  string  $whereClause  WHERE clause built by buildFilterClauses
     * @param  array<string, mixed>  $params  Parameter bindings for the WHERE clause
     * @return int Total number of matching records
     */
    private function getTotalCount(string $whereClause, array $params): int
    {
        $countSql = "SELECT COUNT(*) 
                    FROM {$this->getTable()} p
                    {$whereClause}";

        $stmt = $this->database->query($countSql, $params);

        return (int) $stmt->fetchColumn();
    }
}
