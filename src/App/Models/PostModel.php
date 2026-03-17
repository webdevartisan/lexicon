<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\PostResource;

/**
 * PostModel handles post CRUD operations and relationships.
 *
 * Manages posts table with cache invalidation, search, filtering, pagination,
 * and workflow state transitions. Includes cascade deletion helpers for blog cleanup.
 */
class PostModel extends AppModel
{
    protected ?string $table = 'posts';

    /**
     * Valid post status values.
     */
    public const STATUSES = ['draft', 'pending', 'published', 'archived', 'rejected', 'approved', 'pending_review'];

    /**
     * Valid workflow state values for collaborative editing.
     */
    public const WORKFLOW_STATES = ['idea', 'draft', 'in_review', 'needs_changes', 'approved', 'ready_to_publish'];

    /**
     * Allowed status transitions.
     */
    public const STATUS_TRANSITIONS = [
        'draft' => ['pending'],
        'pending' => ['published', 'draft', 'rejected'],
        'published' => ['archived'],
        'archived' => ['published'],
    ];

    /**
     * Create a new post and invalidate blog listing caches.
     *
     * Invalidates blog listings so visitors see the new post immediately.
     *
     * @param  array  $data  Post data
     * @return int Newly created post ID
     */
    public function create(array $data): int
    {
        $id = parent::insert($data);

        if ($id) {
            // Clear all blog listing cache (homepage, category pages, etc.)
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $id;
    }

    /**
     * Update an existing post and invalidate related caches.
     *
     * Invalidates both the specific post page and all listings.
     * Fetches the post before updating to get the current slug in case it changes.
     *
     * @param  int|string  $id  Post ID
     * @param  array  $data  Updated post data
     * @return bool True on success
     */
    public function update(int|string $id, array $data): bool
    {
        // Fetch the post before updating to get current slugs
        $post = $this->findResource($id);

        if (!$post) {
            return false;
        }

        $result = parent::update($id, $data);

        if ($result) {
            $blog = $post->blog();

            // Invalidate the current post URL
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/{$post->slug()}*");

            // If the slug changed, invalidate the new URL too
            if (isset($data['slug']) && $data['slug'] !== $post->slug()) {
                cache()->deletePattern("*:GET:/blog/{$blog->slug()}/{$data['slug']}*");
            }

            // Invalidate all blog listings (post might appear in multiple lists)
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Delete a post and invalidate related caches.
     *
     * Invalidates both the specific post page and all listings.
     *
     * @param  int|string  $id  Post ID
     * @return bool True on success
     */
    public function delete(int|string $id): bool
    {
        // Fetch the post before deleting to get its URL for cache invalidation
        $post = $this->findResource($id);

        $result = parent::delete($id);

        if ($result && $post) {
            $blog = $post->blog();

            // Invalidate the deleted post's URL
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/{$post->slug()}*");

            // Invalidate all blog listings (post removed from lists)
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Find posts by author ID.
     *
     * @param  int  $authorId  Author user ID
     * @return array Array of post records
     */
    public function findByAuthorId(int $authorId): array
    {
        return $this->findBy('author_id', $authorId);
    }

    /**
     * Get only published posts ordered by publish date.
     *
     * @return array Array of published post records
     */
    public function published(): array
    {
        $sql = "SELECT * FROM {$this->getTable()} 
                WHERE status = 'published' 
                ORDER BY published_at DESC";
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * Find a post by slug.
     *
     * @param  string  $slug  Post slug
     * @return array|null Post record, or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = :slug LIMIT 1";
        $stmt = $this->database->query($sql, [':slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get the author (User) of a post.
     *
     * @param  int  $userId  User ID
     * @return array|null User record, or null if not found
     */
    public function author(int $userId): ?array
    {
        $user = new UserModel($this->database);

        return $user->find((string) $userId) ?: null;
    }

    /**
     * Get the category of a post.
     *
     * @param  int|null  $categoryId  Category ID
     * @return array|null Category record, or null if not found or no category
     */
    public function category(?int $categoryId): ?array
    {
        if (!$categoryId) {
            return null;
        }
        $category = new CategoryModel($this->database);

        return $category->find((string) $categoryId) ?: null;
    }

    /**
     * Get tags for a post.
     *
     * @param  int  $postId  Post ID
     * @return array Array of tag records
     */
    public function tags(int $postId): array
    {
        $sql = 'SELECT t.* 
                FROM tags t
                INNER JOIN post_tags pt ON t.id = pt.tag_id
                WHERE pt.post_id = :post_id';
        $stmt = $this->database->query($sql, [':post_id' => $postId]);

        return $stmt->fetchAll();
    }

    /**
     * Get comments for a post.
     *
     * @param  int  $postId  Post ID
     * @return array Array of comment records
     */
    public function comments(int $postId): array
    {
        $commentModel = new CommentModel($this->database);

        return $commentModel->forPost($postId);
    }

    /**
     * Get random published posts.
     *
     * @param  int  $limit  Maximum number of posts to return
     * @return array Array of published post records
     */
    public function findRandomPublicPosts(int $limit = 6): array
    {
        $sql = "SELECT * FROM {$this->getTable()}
                WHERE status = 'published'
                ORDER BY RAND()
                LIMIT :limit";

        $stmt = $this->database->query($sql, [':limit' => $limit]);

        return $stmt->fetchAll();
    }

    /**
     * List published posts by author filtered by visibility.
     *
     * @param  int  $authorId  Author user ID
     * @param  array  $visibilities  Array of visibility values to include
     * @param  int  $limit  Maximum number of posts
     * @return array Array of post records
     */
    public function listByAuthorVisibility(int $authorId, array $visibilities, int $limit = 10): array
    {
        // Build IN list with named params (convert array to multiple params)
        $params = [':author_id' => $authorId];
        $placeholders = [];

        foreach ($visibilities as $index => $visibility) {
            $key = ":visibility_{$index}";
            $placeholders[] = $key;
            $params[$key] = $visibility;
        }

        $inClause = implode(',', $placeholders);
        $params[':limit'] = $limit;

        $sql = "SELECT id, blog_id, slug, title, excerpt, featured_image, visibility, published_at
                FROM posts
                WHERE author_id = :author_id
                AND status = 'published'
                AND visibility IN ($inClause)
                ORDER BY published_at DESC
                LIMIT :limit";

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find previous published post for an author before a given UTC timestamp.
     *
     * @param  int  $authorId  Author user ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findPreviousByAuthorAndDate(int $authorId, string $publishedAtUtc): ?array
    {
        $sql = "SELECT id, slug, title, published_at
                FROM {$this->getTable()}
                WHERE author_id = :author_id
                AND status = 'published'
                AND published_at < :ts
                ORDER BY published_at DESC
                LIMIT 1";
        $stmt = $this->database->query($sql, [
            ':author_id' => $authorId,
            ':ts' => $publishedAtUtc,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Find previous published post for a blog before a given UTC timestamp.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findPreviousByBlogIdAndDate(int $blogId, string $publishedAtUtc): ?array
    {
        $sql = "SELECT id, slug, title, published_at
                FROM {$this->getTable()}
                WHERE blog_id = :blog_id
                AND status = 'published'
                AND published_at < :ts
                ORDER BY published_at DESC
                LIMIT 1";
        $stmt = $this->database->query($sql, [
            ':blog_id' => $blogId,
            ':ts' => $publishedAtUtc,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Find next published post for an author after a given UTC timestamp.
     *
     * @param  int  $authorId  Author user ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findNextByAuthorAndDate(int $authorId, string $publishedAtUtc): ?array
    {
        $sql = "SELECT id, slug, title, published_at
                FROM {$this->getTable()}
                WHERE author_id = :author_id
                AND status = 'published'
                AND published_at > :ts
                ORDER BY published_at ASC
                LIMIT 1";
        $stmt = $this->database->query($sql, [
            ':author_id' => $authorId,
            ':ts' => $publishedAtUtc,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Find next published post for a blog after a given UTC timestamp.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findNextByBlogIdAndDate(int $blogId, string $publishedAtUtc): ?array
    {
        $sql = "SELECT id, slug, title, published_at
                FROM {$this->getTable()}
                WHERE blog_id = :blog_id
                AND status = 'published'
                AND published_at > :ts
                ORDER BY published_at ASC
                LIMIT 1";
        $stmt = $this->database->query($sql, [
            ':blog_id' => $blogId,
            ':ts' => $publishedAtUtc,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Find recent published posts by author excluding a specific slug.
     *
     * @param  int  $authorId  Author user ID
     * @param  string  $excludeSlug  Slug to exclude from results
     * @param  int  $limit  Maximum number of posts
     * @return array Array of post records
     */
    public function findRecentByAuthorExcludingSlug(int $authorId, string $excludeSlug, int $limit = 4): array
    {
        $sql = "SELECT id, slug, title, excerpt, featured_image AS cover_url, published_at
                FROM {$this->getTable()}
                WHERE author_id = :author_id
                AND status = 'published'
                AND slug <> :slug
                ORDER BY published_at DESC
                LIMIT :limit";
        $stmt = $this->database->query($sql, [
            ':author_id' => $authorId,
            ':slug' => $excludeSlug,
            ':limit' => $limit,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Find recent published posts by blog excluding a specific slug.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $excludeSlug  Slug to exclude from results
     * @param  int  $limit  Maximum number of posts
     * @return array Array of post records
     */
    public function findRecentByBlogIdExcludingSlug(int $blogId, string $excludeSlug, int $limit = 4): array
    {
        $sql = "SELECT id, slug, title, excerpt, featured_image AS cover_url, published_at
                FROM {$this->getTable()}
                WHERE blog_id = :blog_id
                AND status = 'published'
                AND slug <> :slug
                ORDER BY published_at DESC
                LIMIT :limit";
        $stmt = $this->database->query($sql, [
            ':blog_id' => $blogId,
            ':slug' => $excludeSlug,
            ':limit' => $limit,
        ]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get published posts by blog ID with pagination.
     *
     * @param  int  $blogId  Blog ID to filter by
     * @param  int  $page  Page number (starting from 1)
     * @param  int  $perPage  Number of posts per page
     * @return array Array with 'data' (posts) and pagination metadata
     */
    public function findPublishedByBlogIdWithPagination(int $blogId, int $page = 1, int $perPage = 5): array
    {
        $offset = ($page - 1) * $perPage;

        // Get total count of published posts for this blog
        $countSql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE blog_id = :blog_id AND status = 'published'";
        $countStmt = $this->database->query($countSql, [':blog_id' => $blogId]);
        $totalPosts = (int) $countStmt->fetchColumn();

        $totalPages = (int) ceil($totalPosts / $perPage);

        // Get paginated results
        $sql = "SELECT * FROM {$this->getTable()}
                WHERE blog_id = :blog_id AND status = 'published'
                ORDER BY published_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->database->query($sql, [
            ':blog_id' => $blogId,
            ':limit' => $perPage,
            ':offset' => $offset,
        ]);
        $posts = $stmt->fetchAll();

        return [
            'data' => $posts,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPosts' => $totalPosts,
        ];
    }

    /**
     * Find posts by author with optional filters.
     *
     * @param  int  $authorId  Author user ID
     * @param  int|null  $blogId  Optional blog ID filter
     * @param  string  $status  Optional status filter
     * @param  string  $searchQuery  Optional search term for title/content
     * @return array Array of post records with blog_name
     */
    public function findByAuthorWithFilters(int $authorId, ?int $blogId = null, string $status = '', string $searchQuery = ''): array
    {
        $sql = "SELECT p.*, b.blog_name
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
                WHERE p.author_id = :author_id";

        $params = [':author_id' => $authorId];

        // Apply blog filter if given
        if ($blogId !== null) {
            $sql .= ' AND p.blog_id = :blog_id';
            $params[':blog_id'] = $blogId;
        }

        // Apply status filter if given and valid
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $sql .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        // Apply search filter if query is non-empty
        if ($searchQuery !== '') {
            $sql .= ' AND (p.title LIKE :search_title OR p.content LIKE :search_content)';
            $searchTerm = '%'.$searchQuery.'%';
            $params[':search_title'] = $searchTerm;
            $params[':search_content'] = $searchTerm;
        }

        $sql .= ' ORDER BY p.published_at DESC';

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Search published posts with pagination and optional category filter.
     *
     * @param  string  $query  Search term
     * @param  int  $page  Page number
     * @param  int  $perPage  Posts per page
     * @param  int|null  $categoryId  Optional category filter
     * @return array Array with 'data' (posts) and pagination metadata
     */
    public function searchPublishedPosts(string $query, int $page = 1, int $perPage = 8, ?int $categoryId = null): array
    {
        $offset = ($page - 1) * $perPage;
        $likeQuery = '%'.$query.'%';

        // Build filtering clause
        $categoryClause = '';
        $params = [
            ':title' => $likeQuery,
            ':content' => $likeQuery,
            ':blog_name' => $likeQuery,
        ];

        if ($categoryId !== null) {
            $categoryClause = ' AND p.category_id = :categoryId ';
            $params[':categoryId'] = $categoryId;
        }

        // Count query
        $countSql = "
            SELECT COUNT(*)
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id
            WHERE p.status = 'published' 
            AND (p.title LIKE :title OR p.content LIKE :content OR b.blog_name LIKE :blog_name)
            {$categoryClause}
        ";

        $countStmt = $this->database->query($countSql, $params);
        $totalPosts = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($totalPosts / $perPage);

        // Data query
        $sql = "
            SELECT p.*, b.blog_name, c.name as category_name, c.slug as category_slug
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'published' 
            AND (p.title LIKE :title OR p.content LIKE :content OR b.blog_name LIKE :blog_name)
            {$categoryClause}
            ORDER BY p.published_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $posts = $stmt->fetchAll();

        return [
            'data' => $posts,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPosts' => $totalPosts,
        ];
    }

    /**
     * Get recent published posts with pagination and optional category filter.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Posts per page
     * @param  int|null  $categoryId  Optional category filter
     * @return array Array with 'data' (posts) and pagination metadata
     */
    public function getRecentPublishedWithPagination(int $page = 1, int $perPage = 8, ?int $categoryId = null): array
    {
        $offset = ($page - 1) * $perPage;

        $categoryClause = '';
        $params = [];

        if ($categoryId !== null) {
            $categoryClause = ' AND p.category_id = :categoryId ';
            $params[':categoryId'] = $categoryId;
        }

        // Count total published posts with optional category filter
        $countSql = "SELECT COUNT(*) FROM posts p WHERE p.status = 'published' {$categoryClause}";
        $countStmt = $this->database->query($countSql, $params);
        $totalPosts = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($totalPosts / $perPage);

        // Fetch posts query
        $sql = "
            SELECT p.*, b.blog_name, c.name AS category_name, c.slug AS category_slug
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'published'
        ";

        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = :categoryId ';
        }

        $sql .= ' ORDER BY p.published_at DESC LIMIT :limit OFFSET :offset';

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->database->query($sql, $params);
        $posts = $stmt->fetchAll();

        return [
            'data' => $posts,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPosts' => $totalPosts,
        ];
    }

    /**
     * Get index feed with search or recent posts.
     *
     * Delegates to search or recent listing based on query parameter.
     *
     * @param  array  $options  Options array with page, perPage, categoryId, query keys
     * @return array Array with 'data' (posts) and pagination metadata
     */
    public function getIndexFeed(array $options = []): array
    {
        $page = $options['page'] ?? 1;
        $perPage = $options['perPage'] ?? 8;
        $categoryId = $options['categoryId'] ?? null;
        $query = $options['query'] ?? '';

        if ($query !== '') {
            return $this->searchPublishedPosts($query, $page, $perPage, $categoryId);
        }

        return $this->getRecentPublishedWithPagination($page, $perPage, $categoryId);
    }

    /**
     * Change post status to draft.
     *
     * @param  int  $id  Post ID
     * @return bool True on success
     */
    public function unpublishPost(int $id): bool
    {
        $sql = "UPDATE posts SET status = 'draft' WHERE id = :id";
        $affected = $this->database->execute($sql, [':id' => $id]);

        return $affected > 0;
    }

    /**
     * Change post status to published.
     *
     * @param  int  $id  Post ID
     * @return bool True on success
     */
    public function publishPost(int $id): bool
    {
        $sql = "UPDATE posts SET status = 'published' WHERE id = :id";
        $affected = $this->database->execute($sql, [':id' => $id]);

        return $affected > 0;
    }

    /**
     * Update post status.
     *
     * @param  int  $id  Post ID
     * @param  string  $status  New status value
     * @return bool True on success
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = 'UPDATE posts SET status = :status WHERE id = :id';
        $affected = $this->database->execute($sql, [
            ':status' => $status,
            ':id' => $id,
        ]);

        return $affected > 0;
    }

    /**
     * Find a post and wrap it in PostResource.
     *
     * Resolves the blog relationship and returns a resource object.
     *
     * @param  string|int  $id  Post ID
     * @return PostResource|false PostResource instance, or false if not found
     */
    public function findResource(string|int $id): PostResource|false
    {
        if (!$found = parent::find($id)) {
            return false;
        }

        // Resolve the blog this post belongs to
        $blogId = (int) ($found['blog_id'] ?? 0);
        if ($blogId <= 0) {
            return false;
        }

        // Reuse the same database instance for BlogModel
        $blogModel = new BlogModel($this->database);

        $blogResource = $blogModel->getBlog($blogId);
        if ($blogResource === false) {
            return false;
        }

        return new PostResource($found, $this, $blogResource);
    }

    /**
     * Transition a post to a new workflow state.
     *
     * Keeps this logic in the model so controllers stay thin and transitions are audited.
     *
     * @param  int  $postId  Post ID
     * @param  string  $newState  New workflow state (must be in WORKFLOW_STATES constant)
     * @param  int  $byUserId  User ID performing the transition
     * @return bool True on success
     *
     * @throws \InvalidArgumentException If workflow state is invalid
     */
    public function transitionWorkflow(int $postId, string $newState, int $byUserId): bool
    {
        if (!in_array($newState, self::WORKFLOW_STATES, true)) {
            throw new \InvalidArgumentException("Invalid workflow state: {$newState}");
        }

        $sql = 'UPDATE posts
                SET workflow_state = :state,
                    last_workflow_by = :by,
                    last_workflow_at = NOW()
                WHERE id = :id';

        $affected = $this->database->execute($sql, [
            ':state' => $newState,
            ':by' => $byUserId,
            ':id' => $postId,
        ]);

        $ok = $affected > 0;

        if ($ok) {
            audit()->log(
                $byUserId,
                'transition_workflow',
                'post',
                $postId,
                ["Changed workflow_state to {$newState}"],
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        }

        return $ok;
    }

    /**
     * Find posts by author with filters and pagination.
     *
     * Separates data retrieval from count query for efficiency and clarity.
     * Returns both paginated results and metadata for navigation.
     *
     * @param  int  $authorId  Author user ID
     * @param  int  $page  Current page number (1-based indexing)
     * @param  int  $perPage  Number of records per page
     * @param  int|null  $blogId  Optional blog ID filter
     * @param  string  $status  Optional status filter
     * @param  string  $searchQuery  Optional search term for title/content
     * @return array Array with 'data' (posts) and 'pagination' metadata
     */
    public function findByAuthorWithFiltersPagination(
        int $authorId,
        int $page = 1,
        int $perPage = 10,
        ?int $blogId = null,
        string $status = '',
        string $searchQuery = ''
    ): array {
        // Validate and sanitize pagination parameters to prevent abuse
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100); // Cap between 1-100 to prevent memory issues

        // Build the WHERE clause and parameters once to follow DRY principle
        [$whereClause, $params] = $this->buildFilterClauses($authorId, $blogId, $status, $searchQuery);

        // Get total count first for pagination metadata
        $totalRecords = $this->getTotalCount($whereClause, $params);

        // Calculate offset for LIMIT clause
        $offset = ($page - 1) * $perPage;

        // Build the main query with pagination
        $sql = "SELECT p.*, b.blog_name
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
                {$whereClause}
                ORDER BY p.published_at DESC
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
     * Count posts by blog ID.
     *
     * Use this to show deletion impact before removing a blog.
     *
     * @param  int  $blogId  Blog ID
     * @return int Number of posts
     */
    public function countByBlogId(int $blogId): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->getTable()} WHERE blog_id = :blog_id";
        $stmt = $this->database->query($sql, [':blog_id' => $blogId]);
        $result = $stmt->fetch();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get all posts for a blog.
     *
     * Used to find all uploaded files before deletion.
     *
     * @param  int  $blogId  Blog ID
     * @return array Array of post records
     */
    public function getAllByBlogId(int $blogId): array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE blog_id = :blog_id";
        $stmt = $this->database->query($sql, [':blog_id' => $blogId]);

        return $stmt->fetchAll();
    }

    /**
     * Delete all posts for a blog.
     *
     * Cascade delete when removing a blog.
     *
     * @param  int  $blogId  Blog ID
     * @return int Number of rows deleted
     */
    public function deleteByBlogId(int $blogId): int
    {
        $sql = "DELETE FROM {$this->getTable()} WHERE blog_id = :blog_id";

        return $this->database->execute($sql, [':blog_id' => $blogId]);
    }

    /**
     * Count comments across all posts for a blog.
     *
     * Use this to show deletion impact before removing a blog.
     *
     * @param  int  $blogId  Blog ID
     * @return int Number of comments
     */
    public function countCommentsByBlogId(int $blogId): int
    {
        $sql = "SELECT COUNT(*) as count FROM comments c 
                INNER JOIN {$this->getTable()} p ON c.post_id = p.id 
                WHERE p.blog_id = :blog_id";
        $stmt = $this->database->query($sql, [':blog_id' => $blogId]);
        $result = $stmt->fetch();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Delete all comments for all posts in a blog.
     *
     * Cascade delete comments before deleting posts.
     *
     * @param  int  $blogId  Blog ID
     * @return int Number of rows deleted
     */
    public function deleteCommentsByBlogId(int $blogId): int
    {
        $sql = "DELETE c FROM comments c 
                INNER JOIN {$this->getTable()} p ON c.post_id = p.id 
                WHERE p.blog_id = :blog_id";

        return $this->database->execute($sql, [':blog_id' => $blogId]);
    }

    /**
     * Delete all post-tag relationships for posts in a blog.
     *
     * Cascade delete post_tags before deleting posts.
     *
     * @param  int  $blogId  Blog ID
     * @return int Number of rows deleted
     */
    public function deletePostTagsByBlogId(int $blogId): int
    {
        $sql = "DELETE pt FROM post_tags pt 
                INNER JOIN {$this->getTable()} p ON pt.post_id = p.id 
                WHERE p.blog_id = :blog_id";

        return $this->database->execute($sql, [':blog_id' => $blogId]);
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
     * @return array Tuple of [WHERE clause, parameters]
     */
    private function buildFilterClauses(
        int $authorId,
        ?int $blogId,
        string $status,
        string $searchQuery
    ): array {
        $whereClause = 'WHERE p.author_id = :author_id';
        $params = [':author_id' => $authorId];

        // Apply blog filter if provided
        if ($blogId !== null) {
            $whereClause .= ' AND p.blog_id = :blog_id';
            $params[':blog_id'] = $blogId;
        }

        // Validate status against whitelist to prevent invalid database queries
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $whereClause .= ' AND p.status = :status';
            $params[':status'] = $status;
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
     * @param  array  $params  Parameter bindings for the WHERE clause
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
