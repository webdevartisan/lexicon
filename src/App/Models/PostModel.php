<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\PostResource;
use App\Services\PublicCacheInvalidator;

/**
 * PostModel handles post CRUD operations and relationships.
 *
 * Manages posts table with cache invalidation, dashboard filtering and workflow
 * state transitions. Includes cascade deletion helpers for blog cleanup. What the
 * public reads lives in PublishedPostModel.
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
     * 'moderated' = hidden by a moderation decision. It has no entry in
     * STATUS_TRANSITIONS, so only the moderation flow can take a post out of it.
     */
    public const STATUSES = ['draft', 'pending', 'scheduled', 'published', 'archived', 'moderated'];

    /**
     * Valid workflow state values for the editorial pipeline.
     */
    public const WORKFLOW_STATES = ['draft', 'in_review', 'needs_changes', 'approved'];

    /**
     * Valid visibility values, mirroring the `posts.visibility` enum.
     */
    public const VISIBILITIES = ['public', 'private', 'unlisted'];

    /** Blog addresses the blog's own pages answer, so no post can be given one. */
    private const ROUTE_SLUGS = ['archive', 'index-feed', 'stats'];

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
            // Discover lists recent posts and each blog's post count.
            app(PublicCacheInvalidator::class)->purgeDiscover();

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

            // Discover lists recent posts.
            app(PublicCacheInvalidator::class)->purgeDiscover();

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

            // Discover lists recent posts.
            app(PublicCacheInvalidator::class)->purgeDiscover();

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
     * The address a post will actually get in its blog: the one asked for when it is
     * free, otherwise the first free numbered variant (hello, hello-2, hello-3...).
     *
     * @param  int  $blogId  Blog the post belongs to
     * @param  string  $slug  Requested, already valid slug
     * @param  int|null  $exceptId  The post itself, so its own address never counts as taken
     * @param  int  $maxLength  Longest slug the column and validator accept
     * @return string A slug no other post in the blog uses
     */
    public function availableSlug(int $blogId, string $slug, ?int $exceptId = null, int $maxLength = 100): string
    {
        $sql = "SELECT slug FROM {$this->getTable()}
                WHERE blog_id = :blog_id AND id <> :except_id AND (slug = :slug OR slug LIKE :pattern)";
        $taken = array_flip(array_column($this->database->query($sql, [
            ':blog_id' => $blogId,
            ':except_id' => $exceptId ?? 0,
            ':slug' => $slug,
            ':pattern' => $slug.'-%',
        ])->fetchAll(), 'slug')) + array_flip(self::ROUTE_SLUGS);

        if (!isset($taken[$slug])) {
            return $slug;
        }

        for ($n = 2; ; $n++) {
            $suffix = '-'.$n;
            $candidate = rtrim(substr($slug, 0, $maxLength - strlen($suffix)), '-').$suffix;
            if (!isset($taken[$candidate])) {
                return $candidate;
            }
        }
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
     * Change post status to draft.
     *
     * @param  int  $id  Post ID
     * @return bool True on success
     */
    public function unpublishPost(int $id): bool
    {
        $sql = "UPDATE posts SET status = 'draft' WHERE id = :id AND status <> 'moderated'";
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

        $sql = "UPDATE posts SET status = :status WHERE id = :id AND status <> 'moderated'";
        $affected = $this->database->execute($sql, [
            ':status' => $status,
            ':id' => $id,
        ]);

        if ($affected > 0 && $post) {
            $this->forgetStatusSurfaces($post);
        }

        return $affected > 0;
    }

    /**
     * Update how widely a post is visible, independent of its status.
     *
     * Purges the same public surfaces as a status change: private and
     * unlisted posts are also excluded from those listings, so a flip
     * between them is just as visible to a cached reader.
     *
     * @param  int  $id  Post ID
     * @param  string  $visibility  One of self::VISIBILITIES
     * @return bool True on success
     */
    public function updateVisibility(int $id, string $visibility): bool
    {
        $post = $this->findResource($id);

        $affected = $this->database->execute(
            'UPDATE posts SET visibility = ? WHERE id = ?',
            [$visibility, $id]
        );

        if ($affected > 0 && $post) {
            $this->forgetStatusSurfaces($post);
        }

        return $affected > 0;
    }

    /**
     * Hide a post from readers because of a moderation decision.
     *
     * The old status is kept beside it, so reversing the decision restores the
     * post exactly rather than guessing what it was.
     *
     * @return bool False when it was already hidden or no longer exists
     */
    public function hideForModeration(int $id): bool
    {
        $post = $this->findResource($id);

        $affected = $this->database->execute(
            "UPDATE posts
                SET status_before_moderation = status, status = 'moderated'
              WHERE id = ? AND status <> 'moderated'",
            [$id]
        );

        if ($affected > 0 && $post) {
            $this->forgetStatusSurfaces($post);
        }

        return $affected > 0;
    }

    /**
     * Put a post a moderation decision hid back to the status it had.
     *
     * @return bool False when it is not hidden by moderation, or has no status
     *              recorded to go back to (that needs a person, not a guess)
     */
    public function restoreFromModeration(int $id): bool
    {
        $post = $this->findResource($id);

        $affected = $this->database->execute(
            "UPDATE posts
                SET status = status_before_moderation, status_before_moderation = NULL
              WHERE id = ? AND status = 'moderated' AND status_before_moderation IS NOT NULL",
            [$id]
        );

        if ($affected > 0 && $post) {
            $this->forgetStatusSurfaces($post);
        }

        return $affected > 0;
    }

    /**
     * Clear what a status change makes stale: the post page, the listings, and
     * the neighbour and related lists across its blog.
     */
    private function forgetStatusSurfaces(PostResource $post): void
    {
        $blog = $post->blog();

        cache()->deletePattern("*:GET:/blog/{$blog->slug()}/{$post->slug()}*");
        app(PublicCacheInvalidator::class)->purgeDiscover();

        // Neighbour/related lists filter on status='published', so a status
        // flip shifts them for the whole blog.
        $this->forgetBlogPostFragments((int) $blog->id(), (int) $post->id());
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
        string $workflowState = ''
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

        // Whitelist sort options
        $orderBy = match ($sort) {
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
     * Handles of everyone who wrote a post in the blog, in any status.
     *
     * @return list<string>
     */
    public function authorHandlesInBlog(int $blogId): array
    {
        $sql = "SELECT DISTINCT u.handle FROM {$this->getTable()} p
                INNER JOIN users u ON u.id = p.author_id
                WHERE p.blog_id = ? AND u.handle IS NOT NULL AND u.handle <> ''";

        return array_map('strval', $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_COLUMN));
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
     * Ids of every post one person wrote in one blog, whatever its status.
     *
     * @return list<int>
     */
    public function idsByBlogAndAuthor(int $blogId, int $authorId): array
    {
        $sql = "SELECT id FROM {$this->getTable()} WHERE blog_id = ? AND author_id = ?";

        return array_map('intval', $this->database->query($sql, [$blogId, $authorId])->fetchAll(\PDO::FETCH_COLUMN));
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
     * needs_changes is a workflow_state living on posts of any status, so it
     * is summed across the groups rather than counted as its own status.
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

        if ($visibility !== '' && in_array($visibility, self::VISIBILITIES, true)) {
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
     * Review-pipeline posts for a blog, unassigned submissions first.
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
