<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\PostResource;
use App\Services\PostCacheInvalidator;

/**
 * PostModel handles post CRUD operations and relationships.
 *
 * Manages posts table writes, status and workflow
 * transitions, and cascade deletion helpers for blog cleanup. Public reads live
 * in PublishedPostModel, dashboard lists and counts in PostListingModel.
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

    private function postCache(): PostCacheInvalidator
    {
        return app(PostCacheInvalidator::class);
    }

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
            $this->postCache()->postCreated((int) ($data['blog_id'] ?? 0));
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

        if (isset($data['status']) && $data['status'] !== 'pending') {
            $data['workflow_state'] = 'draft';
        }

        $result = parent::update($id, $data);

        if ($result) {
            $this->postCache()->postChanged($post, isset($data['slug']) ? (string) $data['slug'] : null);
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
        // Fetched first: once the row is gone its URL can no longer be looked up.
        $post = $this->findResource($id);

        $result = parent::delete($id);

        if ($result && $post) {
            $this->postCache()->postChanged($post);
        }

        return $result;
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
            $this->postCache()->forgetBlogPosts($blogId);

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
            $this->postCache()->forgetBlogPosts($blogId);

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
     * Update post status.
     *
     * A review state only means something while the post is pending, so any
     * other status clears it. That keeps the review queue from listing posts
     * that were published, scheduled or pulled back by some other route.
     *
     * @param  int  $id  Post ID
     * @param  string  $status  New status value
     * @return bool True on success
     */
    public function updateStatus(int $id, string $status): bool
    {
        // Fetch before the change so we know which blog/URL to invalidate.
        $post = $this->findResource($id);

        $sql = "UPDATE posts
                SET status = :status,
                    workflow_state = IF(:still_pending = 1, workflow_state, 'draft')
                WHERE id = :id AND status <> 'moderated'";
        $affected = $this->database->execute($sql, [
            ':status' => $status,
            ':still_pending' => $status === 'pending' ? 1 : 0,
            ':id' => $id,
        ]);

        if ($affected > 0 && $post) {
            $this->postCache()->postChanged($post);
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
            $this->postCache()->postChanged($post);
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
            $this->postCache()->postChanged($post);
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
            $this->postCache()->postChanged($post);
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
     * The move only happens while the post is still in one of $from, checked in
     * the same statement, so two people deciding at once cannot both succeed.
     *
     * @param  int  $postId  Post ID
     * @param  string  $newState  New workflow state (must be in WORKFLOW_STATES constant)
     * @param  int  $byUserId  User ID performing the transition
     * @param  string[]  $from  States the post may be in; empty allows any
     * @return bool True when the post moved, false when it was not in $from
     *
     * @throws \InvalidArgumentException If workflow state is invalid
     */
    public function transitionWorkflow(int $postId, string $newState, int $byUserId, array $from = []): bool
    {
        if (!in_array($newState, self::WORKFLOW_STATES, true)) {
            throw new \InvalidArgumentException("Invalid workflow state: {$newState}");
        }

        $params = [$newState, $byUserId, $postId];
        $guard = '';
        if ($from !== []) {
            $guard = ' AND workflow_state IN ('.implode(',', array_fill(0, count($from), '?')).')';
            $params = [...$params, ...$from];
        }

        $sql = "UPDATE posts
                SET workflow_state = ?,
                    last_workflow_by = ?,
                    last_workflow_at = NOW()
                WHERE id = ?{$guard}";

        return $this->database->execute($sql, $params) > 0;
    }

    /**
     * Put a draft, or a post sent back for changes, into review.
     *
     * Status and review state change together so the two can never disagree.
     *
     * @param  int  $postId  Post ID
     * @param  int  $byUserId  User submitting it
     * @return bool True when the post entered review
     */
    public function enterReview(int $postId, int $byUserId): bool
    {
        $sql = "UPDATE posts
                SET status = 'pending',
                    workflow_state = 'in_review',
                    last_workflow_by = ?,
                    last_workflow_at = NOW()
                WHERE id = ?
                  AND status IN ('draft', 'pending')
                  AND workflow_state IN ('draft', 'needs_changes')";

        return $this->database->execute($sql, [$byUserId, $postId]) > 0;
    }

    /**
     * Take a post out of review and back to draft.
     *
     * @param  int  $postId  Post ID
     * @param  int  $byUserId  User withdrawing or resetting it
     * @return bool True when the post was pending and is now a draft
     */
    public function leaveReview(int $postId, int $byUserId): bool
    {
        $sql = "UPDATE posts
                SET status = 'draft',
                    workflow_state = 'draft',
                    last_workflow_by = ?,
                    last_workflow_at = NOW()
                WHERE id = ? AND status = 'pending'";

        return $this->database->execute($sql, [$byUserId, $postId]) > 0;
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
     * Find posts for a blog that are currently in the review pipeline.
     *
     * Used by WorkflowService::disableWorkflow to return every pending post
     * to draft when the owner turns off the workflow toggle.
     *
     * @param  int  $blogId  Blog identifier
     * @return array<int, array{id:int,author_id:int,workflow_state:string}>
     */
    public function findInFlightByBlogId(int $blogId): array
    {
        $sql = "SELECT id, author_id, workflow_state
                FROM posts
                WHERE blog_id = ?
                  AND status = 'pending'";

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
