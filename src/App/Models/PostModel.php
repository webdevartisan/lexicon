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
     *
     * 'pending' = awaiting reviewer action (the canonical "needs review" signal).
     * 'scheduled' = approved to go live, waiting on published_at. Deliberately
     * distinct from 'published' so the public queries, which all filter on
     * status = 'published', exclude it without needing a date check.
     */
    public const STATUSES = ['draft', 'pending', 'scheduled', 'published', 'archived'];

    /**
     * Valid workflow state values for the editorial pipeline.
     */
    public const WORKFLOW_STATES = ['draft', 'in_review', 'needs_changes', 'approved'];

    /**
     * Allowed public-lifecycle status transitions.
     *
     * Authors push draft→pending; reviewers/editors push pending→published.
     * 'needs_changes' feedback drops the post back from pending→draft.
     */
    public const STATUS_TRANSITIONS = [
        'draft' => ['pending', 'scheduled', 'published'],
        'pending' => ['draft', 'scheduled', 'published', 'archived'],
        'scheduled' => ['draft', 'pending', 'published', 'archived'],
        'published' => ['archived', 'scheduled', 'draft'],
        'archived' => ['published', 'scheduled', 'draft'],
    ];

    /**
     * Create a new post and invalidate blog listing caches.
     *
     * Invalidates blog listings so visitors see the new post immediately.
     *
     * @param  array<string, mixed>  $data  Post data
     * @return int Newly created post ID
     */
    public function create(array $data): int
    {
        $id = parent::insert($data);

        if ($id) {
            // Clear all blog listing cache (homepage, category pages, etc.)
            cache()->deletePattern('*:GET:/blogs*');

            // A new post shifts every neighbour/related list in its blog.
            if (!empty($data['blog_id'])) {
                $this->forgetBlogPostFragments((int) $data['blog_id']);
            }
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
     * @param  array<string, mixed>  $data  Updated post data
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

            // Drop this post's own data fragments plus the blog-wide neighbour lists.
            $this->forgetBlogPostFragments((int) $blog->id(), (int) $post->id());
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

            // Drop this post's own data fragments plus the blog-wide neighbour lists.
            $this->forgetBlogPostFragments((int) $blog->id(), (int) $post->id());
        }

        return $result;
    }

    /**
     * Drop cached data fragments tied to a blog's posts.
     *
     * Neighbour and related lists are blog-scoped, so any post write in the
     * blog invalidates all of them. When a specific post id is given, its own
     * tag and comment fragments are dropped too.
     *
     * @param  int  $blogId  Blog whose neighbour/related fragments to clear
     * @param  int|null  $postId  Specific post whose tag/comment fragments to clear
     */
    public function forgetBlogPostFragments(int $blogId, ?int $postId = null): void
    {
        // Post-page neighbour and related lists.
        fragment()->forgetPattern('post-nav:'.$blogId.':*');
        fragment()->forgetPattern('post-related:'.$blogId.':*');

        // Landing/listing data: featured pick, paginated lists, category grids,
        // and the category chips (their post_count tracks published posts).
        fragment()->forget('blog-featured:'.$blogId, false);
        fragment()->forgetPattern('blog-posts:'.$blogId.':*');
        fragment()->forgetPattern('blog-catposts:'.$blogId.':*');
        fragment()->forget('blog-pubcats:'.$blogId, false);

        if ($postId !== null) {
            fragment()->forget('post-tags:'.$postId, false);
            fragment()->forget('post-comments:'.$postId, false);
        }
    }

    /**
     * Find posts by author ID.
     *
     * @param  int  $authorId  Author user ID
     * @return array<int, array<string, mixed>> Array of post records
     */
    public function findByAuthorId(int $authorId): array
    {
        return $this->findBy('author_id', $authorId);
    }

    /**
     * Get only published posts ordered by publish date.
     *
     * @return array<int, array<string, mixed>> Array of published post records
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
     * Scheduled posts whose publish time has arrived.
     *
     * Compared against UTC because published_at is normalised to UTC on save;
     * the author's timezone only ever affects how the date is displayed.
     *
     * @param  int  $limit  Maximum rows to return in one sweep
     * @return array<int, array<string, mixed>> Due post records
     */
    public function dueForPublishing(int $limit = 100): array
    {
        $sql = "SELECT id, blog_id, title, slug, published_at
                FROM {$this->getTable()}
                WHERE status = 'scheduled'
                AND published_at IS NOT NULL
                AND published_at <= UTC_TIMESTAMP()
                ORDER BY published_at ASC
                LIMIT :limit";
        $stmt = $this->database->query($sql, [':limit' => $limit]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Flip one scheduled post to published.
     *
     * The status guard makes this safe to run concurrently: a second sweep
     * touching the same row changes nothing and reports zero.
     *
     * @param  int  $postId  Post ID
     * @return bool True when this call was the one that published it
     */
    public function markScheduledAsPublished(int $postId): bool
    {
        $rows = $this->database->execute(
            "UPDATE {$this->getTable()} SET status = 'published' WHERE id = ? AND status = 'scheduled'",
            [$postId]
        );

        return $rows > 0;
    }

    /**
     * Find a post by slug.
     *
     * @param  string  $slug  Post slug
     * @return array<string, mixed>|null Post record, or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = :slug LIMIT 1";
        $stmt = $this->database->query($sql, [':slug' => $slug]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Find a post by slug within a specific blog.
     *
     * Slugs are only unique per blog (unique_blog_slug), so public blog routes
     * must scope the lookup; an unscoped search can return another blog's post
     * that happens to share the slug.
     *
     * @param  string  $slug  Post slug
     * @param  int  $blogId  Blog the post must belong to
     * @return array<string, mixed>|null Post record, or null if not found
     */
    public function findBySlugAndBlogId(string $slug, int $blogId): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = :slug AND blog_id = :blog_id LIMIT 1";
        $stmt = $this->database->query($sql, [':slug' => $slug, ':blog_id' => $blogId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Get the author (User) of a post.
     *
     * @param  int  $userId  User ID
     * @return array<string, mixed>|null User record, or null if not found
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
     * @return array<string, mixed>|null Category record, or null if not found or no category
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
     * @return array<int, array<string, mixed>> Array of tag records
     */
    public function tags(int $postId): array
    {
        $cacheKey = 'post-tags:'.$postId;

        $loadTags = function () use ($postId): array {
            $sql = 'SELECT t.*
                    FROM tags t
                    INNER JOIN post_tags pt ON t.id = pt.tag_id
                    WHERE pt.post_id = :post_id';

            return $this->database->query($sql, [':post_id' => $postId])->fetchAll();
        };

        // localized=false: tag rows read the same in every locale.
        // Busted from create/update/delete when a post's tags may change.
        return fragment()->rememberData($cacheKey, $loadTags, 3600, false);
    }

    /**
     * Get comments for a post.
     *
     * @param  int  $postId  Post ID
     * @return array<int, array<string, mixed>> Array of comment records
     */
    public function comments(int $postId): array
    {
        $commentModel = new CommentModel($this->database);

        return $commentModel->forPost($postId);
    }

    /**
     * Get comments for a post grouped into reply threads.
     *
     * @param  int  $postId  Post ID
     * @return array<int, array<string, mixed>> Top-level comments with a 'replies' array each
     */
    public function commentsThreaded(int $postId): array
    {
        // Caching lives in CommentModel::forPostThreaded, next to the comment
        // write paths that invalidate it.
        $commentModel = new CommentModel($this->database);

        return $commentModel->forPostThreaded($postId);
    }

    /**
     * Count published posts by author filtered by visibility.
     *
     * Companion to listByAuthorVisibility() so public pages can show a
     * truthful total instead of the denormalized users.posts_count, which
     * includes drafts and private posts.
     *
     * @param  int  $authorId  Author user ID
     * @param  string[]  $visibilities  Array of visibility values to include
     * @return int Number of matching posts
     */
    public function countByAuthorVisibility(int $authorId, array $visibilities): int
    {
        $params = [':author_id' => $authorId];
        $placeholders = [];

        foreach ($visibilities as $index => $visibility) {
            $key = ":visibility_{$index}";
            $placeholders[] = $key;
            $params[$key] = $visibility;
        }

        $inClause = implode(',', $placeholders);

        $sql = "SELECT COUNT(*)
                FROM posts
                WHERE author_id = :author_id
                AND status = 'published'
                AND visibility IN ($inClause)";

        $stmt = $this->database->query($sql, $params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count approved comments received on an author's public published posts.
     *
     * @param  int  $authorId  Author user ID
     * @return int Number of approved comments on publicly visible posts
     */
    public function countPublicCommentsReceived(int $authorId): int
    {
        $sql = "SELECT COUNT(c.id)
                FROM comments c
                JOIN posts p ON p.id = c.post_id
                WHERE p.author_id = ?
                AND p.status = 'published'
                AND p.visibility = 'public'
                AND c.status = 'approved'";

        $stmt = $this->database->query($sql, [$authorId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * List published posts by author filtered by visibility.
     *
     * @param  int  $authorId  Author user ID
     * @param  string[]  $visibilities  Array of visibility values to include
     * @param  int  $limit  Maximum number of posts
     * @return array<int, array<string, mixed>> Array of post records
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
     * @return array<string, mixed>|null Post record with id, slug, title, published_at, or null if not found
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
     * @return array<string, mixed>|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findPreviousByBlogIdAndDate(int $blogId, string $publishedAtUtc): ?array
    {
        $cacheKey = 'post-nav:'.$blogId.':prev:'.$publishedAtUtc;

        $loadPrevious = function () use ($blogId, $publishedAtUtc): ?array {
            $sql = "SELECT id, slug, title, published_at
                    FROM {$this->getTable()}
                    WHERE blog_id = :blog_id
                    AND status = 'published'
                    AND published_at < :ts
                    ORDER BY published_at DESC
                    LIMIT 1";

            return $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':ts' => $publishedAtUtc,
            ])->fetch() ?: null;
        };

        // Blog-scoped neighbour lookup, busted for the whole blog on any post write.
        return fragment()->rememberData($cacheKey, $loadPrevious, 3600, false);
    }

    /**
     * Find next published post for an author after a given UTC timestamp.
     *
     * @param  int  $authorId  Author user ID
     * @param  string  $publishedAtUtc  UTC timestamp
     * @return array<string, mixed>|null Post record with id, slug, title, published_at, or null if not found
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
     * @return array<string, mixed>|null Post record with id, slug, title, published_at, or null if not found
     */
    public function findNextByBlogIdAndDate(int $blogId, string $publishedAtUtc): ?array
    {
        $cacheKey = 'post-nav:'.$blogId.':next:'.$publishedAtUtc;

        $loadNext = function () use ($blogId, $publishedAtUtc): ?array {
            $sql = "SELECT id, slug, title, published_at
                    FROM {$this->getTable()}
                    WHERE blog_id = :blog_id
                    AND status = 'published'
                    AND published_at > :ts
                    ORDER BY published_at ASC
                    LIMIT 1";

            return $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':ts' => $publishedAtUtc,
            ])->fetch() ?: null;
        };

        // Blog-scoped neighbour lookup, busted for the whole blog on any post write.
        return fragment()->rememberData($cacheKey, $loadNext, 3600, false);
    }

    /**
     * Find recent published posts by author excluding a specific slug.
     *
     * @param  int  $authorId  Author user ID
     * @param  string  $excludeSlug  Slug to exclude from results
     * @param  int  $limit  Maximum number of posts
     * @return array<int, array<string, mixed>> Array of post records
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
     * @return array<int, array<string, mixed>> Array of post records
     */
    public function findRecentByBlogIdExcludingSlug(int $blogId, string $excludeSlug, int $limit = 4): array
    {
        $cacheKey = 'post-related:'.$blogId.':'.$excludeSlug.':'.$limit;

        $loadRelated = function () use ($blogId, $excludeSlug, $limit): array {
            $sql = "SELECT id, slug, title, excerpt, featured_image AS cover_url, published_at
                    FROM {$this->getTable()}
                    WHERE blog_id = :blog_id
                    AND status = 'published'
                    AND slug <> :slug
                    ORDER BY published_at DESC
                    LIMIT :limit";

            return $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':slug' => $excludeSlug,
                ':limit' => $limit,
            ])->fetchAll() ?: [];
        };

        // "Related" list for the post page, busted for the whole blog on any post write.
        return fragment()->rememberData($cacheKey, $loadRelated, 3600, false);
    }

    /**
     * Get published posts by blog ID with pagination.
     *
     * @param  int  $blogId  Blog ID to filter by
     * @param  int  $page  Page number (starting from 1)
     * @param  int  $perPage  Number of posts per page
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function findPublishedByBlogIdWithPagination(int $blogId, int $page = 1, int $perPage = 5): array
    {
        $cacheKey = 'blog-posts:'.$blogId.':'.$page.':'.$perPage;

        $loadPage = function () use ($blogId, $page, $perPage): array {
            $offset = ($page - 1) * $perPage;

            // Get total count of published posts for this blog
            $countSql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE blog_id = :blog_id AND status = 'published'";
            $totalPosts = (int) $this->database->query($countSql, [':blog_id' => $blogId])->fetchColumn();

            $totalPages = (int) ceil($totalPosts / $perPage);

            // Get paginated results
            $sql = "SELECT * FROM {$this->getTable()}
                    WHERE blog_id = :blog_id AND status = 'published'
                    ORDER BY published_at DESC
                    LIMIT :limit OFFSET :offset";

            $posts = $this->database->query($sql, [
                ':blog_id' => $blogId,
                ':limit' => $perPage,
                ':offset' => $offset,
            ])->fetchAll();

            return [
                'data' => $posts,
                'totalPages' => $totalPages,
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalPosts' => $totalPosts,
            ];
        };

        // Blog-scoped published listing; busted on any post write for the blog.
        return fragment()->rememberData($cacheKey, $loadPage, 3600, false);
    }

    /**
     * Find posts by author with optional filters.
     *
     * @param  int  $authorId  Author user ID
     * @param  int|null  $blogId  Optional blog ID filter
     * @param  string  $status  Optional status filter
     * @param  string  $searchQuery  Optional search term for title/content
     * @return array<int, array<string, mixed>> Array of post records with blog_name
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
     * @param  string|null  $categoryName  Optional category name filter
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function searchPublishedPosts(string $query, int $page = 1, int $perPage = 8, ?string $categoryName = null): array
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

        if ($categoryName !== null && $categoryName !== '') {
            // Topic filter: match by category name across all blogs.
            $categoryClause = ' AND p.category_id IN (SELECT id FROM categories WHERE name = :categoryName) ';
            $params[':categoryName'] = $categoryName;
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
            SELECT p.*, b.blog_name, b.blog_slug, c.name as category_name, c.slug as category_slug
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
     * @param  string|null  $categoryName  Optional category name filter
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function getRecentPublishedWithPagination(int $page = 1, int $perPage = 8, ?string $categoryName = null): array
    {
        $offset = ($page - 1) * $perPage;

        $categoryClause = '';
        $params = [];

        if ($categoryName !== null && $categoryName !== '') {
            // Topic filter: match by category name across all blogs.
            $categoryClause = ' AND p.category_id IN (SELECT id FROM categories WHERE name = :categoryName) ';
            $params[':categoryName'] = $categoryName;
        }

        // Count total published posts with optional category filter
        $countSql = "SELECT COUNT(*) FROM posts p WHERE p.status = 'published' {$categoryClause}";
        $countStmt = $this->database->query($countSql, $params);
        $totalPosts = (int) $countStmt->fetchColumn();
        $totalPages = (int) ceil($totalPosts / $perPage);

        // Fetch posts query
        $sql = "
            SELECT p.*, b.blog_name, b.blog_slug, c.name AS category_name, c.slug AS category_slug
            FROM posts p
            JOIN blogs b ON p.blog_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'published'
        ";

        $sql .= $categoryClause;
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
     * The blog's featured headline: a featured, published post.
     *
     * Newest one takes priority if multiple entries are flagged,
     * acting as a safety net on top of the one‑per‑blog rule enforced by setFeatured().
     *
     * @return array<string, mixed>|null Featured post record, or null if none
     */
    public function findFeaturedByBlogId(int $blogId): ?array
    {
        $cacheKey = 'blog-featured:'.$blogId;

        $loadFeatured = function () use ($blogId): ?array {
            $sql = "SELECT * FROM posts
                    WHERE blog_id = ? AND is_featured = 1 AND status = 'published'
                    ORDER BY published_at DESC, id DESC
                    LIMIT 1";

            return $this->database->query($sql, [$blogId])->fetch(\PDO::FETCH_ASSOC) ?: null;
        };

        // Blog-scoped landing data; busted on any post write or a featured toggle.
        return fragment()->rememberData($cacheKey, $loadFeatured, 3600, false);
    }

    /**
     * Toggle a post's featured flag, keeping at most one featured post per blog.
     *
     * Turning a post on clears the flag from the blog's other posts in the same
     * transaction, so two posts can never end up featured at once.
     */
    public function setFeatured(int $postId, int $blogId, bool $on): bool
    {
        if (!$on) {
            $this->database->execute(
                'UPDATE posts SET is_featured = 0 WHERE id = ? AND blog_id = ?',
                [$postId, $blogId]
            );
            $this->forgetBlogPostFragments($blogId);

            return true;
        }

        $this->database->beginTransaction();
        try {
            $this->database->execute(
                'UPDATE posts SET is_featured = 0 WHERE blog_id = ? AND id <> ?',
                [$blogId, $postId]
            );
            $this->database->execute(
                'UPDATE posts SET is_featured = 1 WHERE id = ? AND blog_id = ?',
                [$postId, $blogId]
            );
            $this->database->commit();
            $this->forgetBlogPostFragments($blogId);

            return true;
        } catch (\Throwable $e) {
            $this->database->rollback();
            error_log('setFeatured failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Platform-wide count of published posts, for the front page stats strip.
     */
    public function countPublished(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE status = 'published'";

        return (int) $this->database->query($sql)->fetchColumn();
    }

    /**
     * Public post URLs for the sitemap: published, public, on published blogs.
     *
     * @param  int  $limit  Safety cap on sitemap size
     * @return array<int, array<string, mixed>> Rows with slug, blog_slug, updated_at
     */
    public function findPublicForSitemap(int $limit = 1000): array
    {
        $sql = "SELECT p.slug, p.updated_at, b.blog_slug
                FROM posts p
                JOIN blogs b ON p.blog_id = b.id
                WHERE p.status = 'published'
                  AND p.visibility = 'public'
                  AND b.status = 'published'
                ORDER BY p.published_at DESC
                LIMIT :limit";

        return $this->database->query($sql, [':limit' => $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Admin picks for the site front page showcase, newest first.
     *
     * The front page is a platform owned surface, so only posts an
     * administrator explicitly flagged appear here. Returns an empty array
     * when nothing is picked; the view falls back to its own static cards.
     *
     * @param  int  $limit  Maximum number of showcase posts
     * @return array<int, array<string, mixed>> Post rows with blog name and slug
     */
    public function findHomeShowcase(int $limit = 6): array
    {
        $sql = "SELECT p.*, b.blog_name, b.blog_slug
                FROM posts p
                JOIN blogs b ON p.blog_id = b.id
                WHERE p.featured_on_home = 1
                  AND p.status = 'published'
                  AND p.visibility = 'public'
                ORDER BY p.published_at DESC, p.id DESC
                LIMIT :limit";

        return $this->database->query($sql, [':limit' => $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Toggle the admin-only front page flag on a post.
     *
     * Deliberately separate from the per-blog is_featured flag: blog owners
     * control their own headline, only administrators control the site front
     * page.
     */
    public function setFeaturedOnHome(int $postId, bool $on): bool
    {
        $rows = $this->database->execute(
            'UPDATE posts SET featured_on_home = ? WHERE id = ?',
            [$on ? 1 : 0, $postId]
        );

        return $rows >= 0;
    }

    /**
     * Published posts for a blog, optionally within one category, newest first.
     *
     * Powers the folio landing's "index" grid and its AJAX category swap. Pass
     * $excludeId to drop the headline post so it never shows up twice.
     *
     * @return array<int,array<string,mixed>>
     */
    public function findPublishedByBlogAndCategory(int $blogId, ?int $categoryId, int $limit = 6, ?int $excludeId = null): array
    {
        // null id/exclude become 'all'/'none' so the key stays readable and distinct.
        $cacheKey = 'blog-catposts:'.$blogId.':'.($categoryId ?? 'all').':'.$limit.':'.($excludeId ?? 'none');

        $loadCategoryPosts = function () use ($blogId, $categoryId, $limit, $excludeId): array {
            $sql = "SELECT * FROM posts WHERE blog_id = :blog_id AND status = 'published'";
            $params = [':blog_id' => $blogId];

            if ($categoryId !== null) {
                $sql .= ' AND category_id = :category_id';
                $params[':category_id'] = $categoryId;
            }

            if ($excludeId !== null) {
                $sql .= ' AND id <> :exclude_id';
                $params[':exclude_id'] = $excludeId;
            }

            $sql .= ' ORDER BY published_at DESC, id DESC LIMIT :limit';
            $params[':limit'] = $limit;

            return $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        };

        // Blog-scoped category grid; busted on any post write for the blog.
        return fragment()->rememberData($cacheKey, $loadCategoryPosts, 3600, false);
    }

    /**
     * Get index feed with search or recent posts.
     *
     * Delegates to search or recent listing based on query parameter.
     *
     * @param  array<string, mixed>  $options  Options array with page, perPage, categoryName, query keys
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalPosts: int}
     */
    public function getIndexFeed(array $options = []): array
    {
        $page = $options['page'] ?? 1;
        $perPage = $options['perPage'] ?? 8;
        $categoryName = $options['categoryName'] ?? null;
        $query = $options['query'] ?? '';

        if ($query !== '') {
            return $this->searchPublishedPosts($query, $page, $perPage, $categoryName);
        }

        return $this->getRecentPublishedWithPagination($page, $perPage, $categoryName);
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
        // Fetch before the change so we know which blog/URL to invalidate.
        $post = $this->findResource($id);

        $sql = 'UPDATE posts SET status = :status WHERE id = :id';
        $affected = $this->database->execute($sql, [
            ':status' => $status,
            ':id' => $id,
        ]);

        if ($affected > 0 && $post) {
            $blog = $post->blog();

            // Publishing/archiving changes what listings and the post page show.
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/{$post->slug()}*");
            cache()->deletePattern('*:GET:/blogs*');

            // Neighbour/related lists filter on status='published', so a status
            // flip shifts them for the whole blog.
            $this->forgetBlogPostFragments((int) $blog->id(), (int) $post->id());
        }

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

        return new PostResource($found, $blogResource);
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

        return $affected > 0;
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
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function findByAuthorWithFiltersPagination(
        int $authorId,
        int $page = 1,
        int $perPage = 10,
        ?int $blogId = null,
        string $status = '',
        string $searchQuery = '',
        string $sort = 'newest',
        ?int $categoryId = null,
        ?int $tagId = null,
        string $workflowState = '',
        ?int $blogOwnerId = null
    ): array {
        // Validate and sanitize pagination parameters to prevent abuse
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100); // Cap between 1-100 to prevent memory issues

        // Build the WHERE clause and parameters once to follow DRY principle
        [$whereClause, $params] = $this->buildFilterClauses($authorId, $blogId, $status, $searchQuery, $categoryId, $tagId, $workflowState, $blogOwnerId);

        // Get total count first for pagination metadata
        $totalRecords = $this->getTotalCount($whereClause, $params);

        // Calculate offset for LIMIT clause
        $offset = ($page - 1) * $perPage;

        // Whitelist sort options
        $orderBy = match ($sort) {
            'oldest' => 'p.published_at ASC',
            'title_asc' => 'p.title ASC',
            'title_desc' => 'p.title DESC',
            default => 'p.published_at DESC',
        };

        // v1 enforces single reviewer per post, so a plain LEFT JOIN is safe (no row multiplication).
        $sql = "SELECT p.*, b.blog_name, c.name AS category_name,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                       pr.reviewer_id AS reviewer_id,
                       pr.assigned_at AS reviewer_assigned_at,
                       ru.username AS reviewer_username
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
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
     * @return array<int, array<string, mixed>> Array of post records
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
     * Count comments by moderation status across a blog's posts.
     */
    /**
     * Count all posts in a blog by status, across all authors.
     *
     * @param  int  $blogId  Blog ID
     * @param  string  $status  Post status ('draft', 'published', etc.)
     * @return int Number of posts
     */
    public function countByBlogIdAndStatus(int $blogId, string $status): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->getTable()} WHERE blog_id = :blog_id AND status = :status";
        $stmt = $this->database->query($sql, [':blog_id' => $blogId, ':status' => $status]);

        return (int) ($stmt->fetch()['count'] ?? 0);
    }

    public function countCommentsByBlogIdAndStatus(int $blogId, string $status): int
    {
        $sql = "SELECT COUNT(*) as count FROM comments c
                INNER JOIN {$this->getTable()} p ON c.post_id = p.id
                WHERE p.blog_id = :blog_id AND c.status = :status";
        $stmt = $this->database->query($sql, [':blog_id' => $blogId, ':status' => $status]);
        $result = $stmt->fetch();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Count posts in a blog authored by a specific user with a given status.
     *
     * Used by the Shared landing page to show "my drafts on this blog" etc.
     */
    public function countByBlogAndAuthorAndStatus(int $blogId, int $authorId, string $status): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->getTable()}
                WHERE blog_id = ? AND author_id = ? AND status = ?";
        $stmt = $this->database->query($sql, [$blogId, $authorId, $status]);

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Count posts in a blog authored by a specific user with a given workflow_state.
     *
     * Used for the author/contributor "needs revision" badge on the Shared page.
     */
    public function countByBlogAndAuthorAndWorkflow(int $blogId, int $authorId, string $workflowState): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->getTable()}
                WHERE blog_id = ? AND author_id = ? AND workflow_state = ?";
        $stmt = $this->database->query($sql, [$blogId, $authorId, $workflowState]);

        return (int) ($stmt->fetch()['c'] ?? 0);
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

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        if ($workflowState !== '' && in_array($workflowState, self::WORKFLOW_STATES, true)) {
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
                       au.username AS author_username,
                       (SELECT COUNT(*) FROM comments WHERE post_id = p.id) AS comment_count,
                       pr.reviewer_id AS reviewer_id,
                       pr.assigned_at AS reviewer_assigned_at,
                       ru.username AS reviewer_username
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
        int $authorId,
        ?int $blogId = null,
        string $searchQuery = '',
        ?int $categoryId = null,
        ?int $tagId = null,
        ?int $blogOwnerId = null
    ): array {
        [$whereClause, $params] = $this->buildFilterClauses(
            $authorId, $blogId, '', $searchQuery, $categoryId, $tagId, '', $blogOwnerId
        );

        return $this->groupedStatusCounts($whereClause, $params);
    }

    /**
     * Per-status totals across every author in one blog, for the per-blog
     * posts index badges.
     *
     * @return array<string, int> Keys: all, published, draft, pending, needs_changes, archived
     */
    public function countsByStatusForBlog(int $blogId, string $searchQuery = ''): array
    {
        $where = 'WHERE p.blog_id = :blog_id';
        $params = [':blog_id' => $blogId];

        if ($searchQuery !== '') {
            $where .= ' AND (p.title LIKE :search_title OR p.content LIKE :search_content)';
            $term = '%'.$searchQuery.'%';
            $params[':search_title'] = $term;
            $params[':search_content'] = $term;
        }

        return $this->groupedStatusCounts($where, $params);
    }

    /**
     * Run one GROUP BY status query and shape it into the badge-count array.
     *
     * needs_changes is a workflow_state living on posts of any status, so it
     * is summed across the groups rather than counted as its own status.
     *
     * @param  string  $whereClause  Prepared WHERE clause (aliased as p)
     * @param  array<string, mixed>  $params  Bindings for the WHERE clause
     * @return array<string, int>
     */
    private function groupedStatusCounts(string $whereClause, array $params): array
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

        $sql = "SELECT p.status, COUNT(*) AS cnt,
                       SUM(CASE WHEN p.workflow_state = 'needs_changes' THEN 1 ELSE 0 END) AS needs_changes
                FROM {$this->getTable()} p
                {$whereClause}
                GROUP BY p.status";

        foreach ($this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
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
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function findAllForAdmin(
        int $page = 1,
        int $perPage = 20,
        string $status = '',
        string $searchQuery = ''
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = 'WHERE 1=1';
        $params = [];

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        if ($searchQuery !== '') {
            $where .= ' AND (p.title LIKE :search_title OR p.content LIKE :search_content)';
            $term = '%'.$searchQuery.'%';
            $params[':search_title'] = $term;
            $params[':search_content'] = $term;
        }

        $countStmt = $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} p {$where}",
            $params
        );
        $totalRecords = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.id, p.title, p.slug, p.status, p.updated_at, p.blog_id,
                       p.featured_on_home,
                       b.blog_name, b.blog_slug,
                       au.username AS author_username
                FROM {$this->getTable()} p
                LEFT JOIN blogs b ON p.blog_id = b.id
                LEFT JOIN users au ON au.id = p.author_id
                {$where}
                ORDER BY p.updated_at DESC
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
     * Review-pipeline posts for a blog, unassigned submissions first.
     *
     * @return array<int, array<string, mixed>> Post rows with author and reviewer info
     */
    public function findReviewQueueForBlog(int $blogId): array
    {
        $sql = "SELECT p.id, p.title, p.slug, p.workflow_state, p.updated_at, p.author_id,
                       au.username AS author_username,
                       pr.reviewer_id, ru.username AS reviewer_username,
                       pr.assigned_at
                FROM {$this->getTable()} p
                INNER JOIN users au ON au.id = p.author_id
                LEFT JOIN post_reviewers pr ON pr.post_id = p.id
                LEFT JOIN users ru ON ru.id = pr.reviewer_id
                WHERE p.blog_id = ?
                  AND p.workflow_state IN ('in_review', 'needs_changes')
                ORDER BY (pr.reviewer_id IS NULL) DESC, p.updated_at DESC";

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
     * @return array{string, array<string, mixed>} Tuple of [WHERE clause, parameters]
     */
    private function buildFilterClauses(
        int $authorId,
        ?int $blogId,
        string $status,
        string $searchQuery,
        ?int $categoryId = null,
        ?int $tagId = null,
        string $workflowState = '',
        ?int $blogOwnerId = null
    ): array {
        $whereClause = 'WHERE p.author_id = :author_id';
        $params = [':author_id' => $authorId];

        // Apply blog filter if provided
        if ($blogId !== null) {
            $whereClause .= ' AND p.blog_id = :blog_id';
            $params[':blog_id'] = $blogId;
        }

        // Constrain to blogs owned by the given user. Used by the personal
        // "All Posts" view so posts drafted on shared blogs never leak into
        // the owned surface.
        if ($blogOwnerId !== null) {
            $whereClause .= ' AND p.blog_id IN (SELECT id FROM blogs WHERE owner_id = :blog_owner_id)';
            $params[':blog_owner_id'] = $blogOwnerId;
        }

        // Validate status against whitelist to prevent invalid database queries
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $whereClause .= ' AND p.status = :status';
            $params[':status'] = $status;
        }

        // Workflow state filter is independent of post status (a 'needs_changes'
        // workflow_state lives on a draft-status post). Validated against the
        // WORKFLOW_STATES whitelist before reaching SQL.
        if ($workflowState !== '' && in_array($workflowState, self::WORKFLOW_STATES, true)) {
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

    /**
     * Find posts for a blog that are currently in the review pipeline.
     *
     * Used by WorkflowService::disableWorkflow to reset in-flight posts
     * back to draft when the owner turns off the workflow toggle.
     *
     * @param  int  $blogId  Blog identifier
     * @return array<int, array{id:int,author_id:int,workflow_state:string}>
     */
    public function findInFlightByBlogId(int $blogId): array
    {
        $sql = "SELECT id, author_id, workflow_state
                FROM posts
                WHERE blog_id = ?
                  AND workflow_state IN ('in_review', 'needs_changes')";

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
