<?php

namespace App\Models;

use App\Resources\BlogResource;

/**
 * BlogModel handles blog CRUD operations and collaborator management.
 *
 * Manages the blogs table and blog_users pivot table for multi-user blogs.
 * Includes cache invalidation on updates and resource transformation.
 */
class BlogModel extends AppModel
{
    protected ?string $table = 'blogs';

    /**
     * Valid blog status values.
     */
    public const STATUSES = ['draft', 'published', 'archived'];

    /**
     * Valid collaborative roles for blog_users.
     * 
     * These are per-blog roles independent of global user roles.
     */
    public const ROLES = ['editor', 'author', 'contributor', 'reviewer', 'viewer'];

    /**
     * Update a blog and invalidate related caches.
     *
     * Invalidates all cached blog URLs and post listings when blog data changes.
     * If slug changes, invalidates both old and new URLs.
     *
     * @param int|string $id Blog ID
     * @param array $data Updated blog data
     * @return bool True on success
     */
    public function update(int|string $id, array $data): bool
    {
        $blog = $this->getBlog($id);

        if (!$blog) {
            return false;
        }

        $result = parent::update($id, $data);

        if ($result) {
            // Invalidate old blog URL and all its posts
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/*");

            // If slug changed, invalidate new URL too
            if (isset($data['slug']) && $data['slug'] !== $blog->slug()) {
                cache()->deletePattern("*:GET:/blog/{$data['slug']}/*");
            }

            // Invalidate blog listings
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Get all posts belonging to a blog.
     *
     * @param int $blogId Blog ID
     * @return array Array of post records
     */
    public function getBlogPosts(int $blogId): array
    {
        // Query posts table directly without mutating $this->table
        $sql = 'SELECT * FROM posts WHERE blog_id = ?';
        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all blogs with owner info and aggregate counts.
     *
     * Returns blogs ordered by publish date with post count and active collaborator count.
     *
     * @return array Array of blog records with owner_name, post_count, author_count
     */
    public function getAllBlogsWithOwnerAndCounts(): array
    {
        $sql = 'SELECT b.*, u.username as owner_name,
                    (SELECT COUNT(*) FROM posts WHERE blog_id = b.id) as post_count,
                    (SELECT COUNT(*) FROM blog_users WHERE blog_id = b.id AND is_active = 1) as author_count
                FROM blogs b
                INNER JOIN users u ON b.owner_id = u.id
                ORDER BY b.published_at DESC';

        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all blogs owned by a user with aggregate counts.
     *
     * @param int $ownerId User ID
     * @return array Array of blog records with owner_name, post_count, author_count
     */
    public function getBlogsByOwnerWithCounts(int $ownerId): array
    {
        $sql = 'SELECT b.*, u.username as owner_name,
                    (SELECT COUNT(*) FROM posts WHERE blog_id = b.id) as post_count,
                    (SELECT COUNT(*) FROM blog_users WHERE blog_id = b.id AND is_active = 1) as author_count
                FROM blogs b
                INNER JOIN users u ON b.owner_id = u.id
                WHERE b.owner_id = ?
                ORDER BY b.published_at DESC';
        $stmt = $this->database->query($sql, [$ownerId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a single blog by ID with owner info and counts.
     *
     * @param int $blogId Blog ID
     * @return array|null Blog record with owner_name, post_count, author_count, or null if not found
     */
    public function getBlogByIdWithCounts(int $blogId): ?array
    {
        $sql = 'SELECT b.*, u.username as owner_name,
                    (SELECT COUNT(*) FROM posts WHERE blog_id = b.id) as post_count,
                    (SELECT COUNT(*) FROM blog_users WHERE blog_id = b.id AND is_active = 1) as author_count
                FROM blogs b
                INNER JOIN users u ON b.owner_id = u.id
                WHERE b.id = ?';
        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get blog ID for a given owner.
     *
     * Returns the most recently published blog for the owner.
     *
     * @param int $ownerId User ID
     * @return int Blog ID
     */
    public function getBlogIdByOwnerId(int $ownerId): int
    {
        $sql = 'SELECT id FROM blogs
                WHERE owner_id = ?
                ORDER BY published_at DESC
                LIMIT 1';

        $stmt = $this->database->query($sql, [$ownerId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($result['id'] ?? 0);
    }

    /**
     * Get blog name for a given owner.
     *
     * Returns the most recently published blog name for the owner.
     *
     * @param int $ownerId User ID
     * @return string Blog name
     */
    public function getBlogNameByOwnerId(int $ownerId): string
    {
        $sql = 'SELECT blog_name FROM blogs
                WHERE owner_id = ?
                ORDER BY published_at DESC
                LIMIT 1';

        $stmt = $this->database->query($sql, [$ownerId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (string) ($result['blog_name'] ?? '');
    }

    /**
     * Create a new blog.
     *
     * @param array $data Blog data (blog_name, blog_slug, description, owner_id)
     * @return int Newly created blog ID
     */
    public function createBlog(array $data): int
    {
        $sql = 'INSERT INTO blogs (blog_name, blog_slug, description, owner_id) VALUES (?, ?, ?, ?)';
        $this->database->execute($sql, [
            $data['blog_name'],
            $data['blog_slug'],
            $data['description'],
            $data['owner_id'],
        ]);

        return (int) $this->database->lastInsertId();
    }

    /**
     * Get a blog by ID with owner username.
     *
     * @param int $id Blog ID
     * @return array|null Blog record with owner_name, or null if not found
     */
    public function getBlogById(int $id): ?array
    {
        $sql = 'SELECT b.*, u.username as owner_name
                FROM blogs b
                INNER JOIN users u ON b.owner_id = u.id
                WHERE b.id = ?';
        $stmt = $this->database->query($sql, [$id]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all blogs owned by a user.
     *
     * @param int $ownerId User ID
     * @return array Array of blog records
     */
    public function getBlogsByOwnerId(int $ownerId): array
    {
        $sql = 'SELECT b.* FROM blogs b
                WHERE b.owner_id = ?
                ORDER BY b.published_at DESC, b.created_at DESC';

        $stmt = $this->database->query($sql, [$ownerId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all active collaborators for a blog.
     *
     * Returns users assigned to the blog with their role and contact info.
     *
     * @param int $blogId Blog ID
     * @return array Array of blog_users records with username and email
     */
    public function getBlogUsers(int $blogId): array
    {
        $sql = 'SELECT bu.*, u.username, u.email
                FROM blog_users bu
                INNER JOIN users u ON bu.user_id = u.id
                WHERE bu.blog_id = ? AND bu.is_active = 1';

        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all active users not yet assigned to a blog.
     *
     * Returns users available for assignment, excluding those already active on the blog.
     * Global user roles do not affect per-blog role eligibility.
     *
     * @param int $blogId Blog ID
     * @return array Array of user records with id, username, email
     */
    public function getAvailableUsers(int $blogId): array
    {
        $sql = 'SELECT u.id, u.username, u.email
                FROM users u
                WHERE u.is_active = 1
                AND u.id NOT IN (
                    SELECT user_id 
                    FROM blog_users 
                    WHERE blog_id = ? AND is_active = 1
                )
                ORDER BY u.username ASC';

        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Assign a user to a blog with a collaborative role.
     *
     * Uses UNIQUE(blog_id, user_id) constraint to prevent duplicates.
     * Re-adding a previously revoked user reactivates them with the new role.
     *
     * @param int $blogId Blog ID
     * @param int $userId User ID to assign
     * @param string $role Collaborative role (must be one of ROLES constants)
     * @param int $assignedBy User ID performing the assignment
     * @return bool True on success
     * @throws \InvalidArgumentException If role is not in ROLES constant
     */
    public function addUserToBlog(int $blogId, int $userId, string $role, int $assignedBy): bool
    {
        // Validate role against allowed collaborative roles only
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $sql = 'INSERT INTO blog_users (blog_id, user_id, role, assigned_by, assigned_at, is_active)
                VALUES (?, ?, ?, ?, NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    role = VALUES(role),
                    assigned_by = VALUES(assigned_by),
                    assigned_at = NOW(),
                    is_active = 1,
                    revoked_at = NULL';

        $rowCount = $this->database->execute($sql, [$blogId, $userId, $role, $assignedBy]);

        // Log assignment for audit trail
        if ($rowCount > 0) {
            audit()->log(
                $assignedBy,
                'assign_blog_user',
                'blog_user',
                $blogId,
                ["Assigned user {$userId} as {$role} to blog {$blogId}"],
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        }

        return $rowCount > 0;
    }

    /**
     * Revoke a user's access to a blog.
     *
     * Performs soft delete by setting is_active=0 and recording revoked_at timestamp.
     * Preserves record for audit trail and potential restoration.
     *
     * @param int $blogId Blog ID
     * @param int $userId User ID to revoke
     * @return bool True on success
     */
    public function revokeUserFromBlog(int $blogId, int $userId): bool
    {
        $sql = 'UPDATE blog_users
                SET is_active = 0, revoked_at = NOW()
                WHERE blog_id = ? AND user_id = ? AND is_active = 1';

        $rowCount = $this->database->execute($sql, [$blogId, $userId]);

        // Log revocation for compliance and debugging
        if ($rowCount > 0) {
            audit()->log(
                auth()->user()['id'] ?? 0,
                'revoke_blog_user',
                'blog_user',
                $blogId,
                ["Revoked user {$userId} from blog {$blogId}"],
                $_SERVER['REMOTE_ADDR'] ?? null
            );
        }

        return $rowCount > 0;
    }

    /**
     * Change blog status to draft.
     *
     * @param int $id Blog ID
     * @return bool True on success
     */
    public function unpublishBlog(int $id): bool
    {
        $sql = "UPDATE blogs SET status = 'draft' WHERE id = ?";

        return $this->database->execute($sql, [$id]) > 0;
    }

    /**
     * Change blog status to published.
     *
     * @param int $id Blog ID
     * @return bool True on success
     */
    public function publishBlog(int $id): bool
    {
        $sql = "UPDATE blogs SET status = 'published' WHERE id = ?";

        return $this->database->execute($sql, [$id]) > 0;
    }

    /**
     * Get a blog by its slug.
     *
     * @param string $slug Blog slug
     * @return array|null Blog record, or null if not found
     */
    public function getBlogBySlug(string $slug): ?array
    {
        $sql = 'SELECT * FROM blogs WHERE blog_slug = ?';
        $stmt = $this->database->query($sql, [$slug]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find multiple blogs by their IDs.
     *
     * Returns minimal blog data (id, blog_slug) for efficient slug lookups.
     * Used to enrich posts with blog slugs without N+1 queries.
     *
     * @param array<int> $ids Blog IDs to fetch
     * @return array Array of blog records with id and blog_slug
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $sql = "SELECT id, blog_slug FROM blogs WHERE id IN ($placeholders)";
        $stmt = $this->database->query($sql, $ids);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get featured blog creators ordered by post count.
     *
     * Returns blogs with the most published posts for homepage or discovery features.
     *
     * @param int $limit Maximum number of creators to return
     * @return array Array of blog records with ownername and postcount
     */
    public function getFeaturedCreators(int $limit = 4): array
    {
        $sql = "
            SELECT b.*, u.username AS ownername,
                (SELECT COUNT(*) FROM posts p WHERE p.blog_id = b.id AND p.status = 'published') AS postcount
            FROM blogs b
            INNER JOIN users u ON b.owner_id = u.id
            ORDER BY postcount DESC, b.published_at DESC
            LIMIT ?
        ";
        $stmt = $this->database->query($sql, [$limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a blog wrapped in BlogResource.
     *
     * @param string|int $id Blog ID
     * @return BlogResource|false BlogResource instance, or false if not found
     */
    public function getBlog(string|int $id): BlogResource|false
    {
        if (!$found = parent::find($id)) {
            return false;
        }

        return new BlogResource($found, $this);
    }

    /**
     * Get all blogs for a user as BlogResource array.
     *
     * @param string|int $owner_id User ID
     * @return array Array of BlogResource instances, or empty array if none found
     */
    public function resource(string|int $owner_id): array
    {
        $blogs = parent::findBy('owner_id', $owner_id);

        if (empty($blogs)) {
            return [];
        }

        return array_map(function ($blog) {
            return new BlogResource($blog, $this);
        }, $blogs);
    }

    /**
     * Delete a blog and invalidate related caches.
     *
     * Performs hard delete from database. Cascading deletes should be handled
     * at application level for audit trail (posts, collaborators, etc.).
     *
     * @param int|string $id Blog ID
     * @return bool True on success
     */
    public function delete(int|string $id): bool
    {
        $blog = $this->getBlog($id);

        $result = parent::delete($id);

        if ($result && $blog) {
            // Invalidate all posts in this blog
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/*");

            // Invalidate blog listings
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Count active collaborators for a blog.
     *
     * Use this to show deletion impact before removing a blog.
     *
     * @param int $blogId Blog ID
     * @return int Number of active collaborators
     */
    public function countCollaborators(int $blogId): int
    {
        $sql = 'SELECT COUNT(*) as count FROM blog_users 
                WHERE blog_id = ? AND is_active = 1';
        $stmt = $this->database->query($sql, [$blogId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Delete all collaborators for a blog.
     *
     * Removes all user-blog relationships when deleting a blog.
     * Uses hard delete since the parent blog is being deleted.
     *
     * @param int $blogId Blog ID
     * @return int Number of rows deleted
     */
    public function deleteCollaboratorsByBlogId(int $blogId): int
    {
        $sql = 'DELETE FROM blog_users WHERE blog_id = ?';
        
        return $this->database->execute($sql, [$blogId]);
    }
}
