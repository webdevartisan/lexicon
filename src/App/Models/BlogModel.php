<?php

declare(strict_types=1);

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
    public const ROLES = ['editor', 'author', 'contributor', 'reviewer'];

    /**
     * Collaborator roles offered on the team page: the shipped list plus any
     * admin-created custom roles with blog scope.
     *
     * @param  bool  $workflowEnabled  When false the reviewer role is dropped (nothing for it to do)
     * @return string[] Role slugs
     */
    public function availableCollaboratorRoles(bool $workflowEnabled = true): array
    {
        $roles = $this->database->query(
            "SELECT role_slug FROM roles
             WHERE scope = 'blog' AND role_slug <> 'blog_owner'
             ORDER BY level DESC"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $roles = array_map('strval', $roles);

        // Reviewer has nothing to do when the editorial pipeline is off.
        if (!$workflowEnabled) {
            $roles = array_values(array_diff($roles, ['reviewer']));
        }

        return $roles;
    }

    /**
     * Behavior level for a stored collaborator role.
     *
     * Custom admin-created roles keep their own slug in blog_users, but act as
     * one of the shipped roles, derived from the permissions granted to them.
     * Resolving here keeps every policy and view check working against the
     * known role names.
     */
    public function baseRoleFor(string $role): string
    {
        if ($role === '' || $role === 'owner' || in_array($role, self::ROLES, true)) {
            return $role;
        }

        static $resolved = [];
        if (isset($resolved[$role])) {
            return $resolved[$role];
        }

        $slugs = $this->database->query(
            'SELECT p.permission_slug
             FROM roles r
             JOIN role_permissions rp ON rp.role_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.role_slug = ?',
            [$role]
        )->fetchAll(\PDO::FETCH_COLUMN);

        $base = 'contributor';
        if (array_intersect($slugs, ['edit_blog_posts', 'publish_blog_posts', 'delete_blog_posts'])) {
            $base = 'editor';
        } elseif (in_array('create_posts', $slugs, true)) {
            $base = 'author';
        } elseif (array_intersect($slugs, ['review_posts', 'approve_posts', 'reject_posts'])) {
            $base = 'reviewer';
        }

        return $resolved[$role] = $base;
    }

    /**
     * The blog role slug a user holds on a blog, straight from blog_users.
     *
     * Returns the stored slug (a shipped role or a custom admin-created one),
     * or null when the user is not an active collaborator. Ownership is
     * structural and lives in blogs.owner_id, so it is resolved separately.
     *
     * @return string|null Stored role slug, or null if not a member
     */
    public function storedRoleFor(int $blogId, int $userId): ?string
    {
        $role = $this->database->query(
            'SELECT role FROM blog_users WHERE blog_id = ? AND user_id = ? AND is_active = 1 LIMIT 1',
            [$blogId, $userId]
        )->fetchColumn();

        return $role === false ? null : (string) $role;
    }

    /**
     * Permission slugs granted by a role slug, read from role_permissions.
     *
     * This is the single source of truth for what a blog role can do. Custom
     * admin-created roles resolve through their own permissions here, so they
     * work natively without being mapped onto a shipped role.
     *
     * @return string[] Permission slugs (empty when the role has none or is unknown)
     */
    public function permissionsForRole(string $roleSlug): array
    {
        static $cache = [];
        if (array_key_exists($roleSlug, $cache)) {
            return $cache[$roleSlug];
        }

        $rows = $this->database->query(
            'SELECT p.permission_slug
             FROM roles r
             JOIN role_permissions rp ON rp.role_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.role_slug = ?',
            [$roleSlug]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return $cache[$roleSlug] = $rows ?: [];
    }

    /**
     * Effective blog-level permission slugs for a user on one blog.
     *
     * Structural owners implicitly hold the full owner bundle (the blog_owner
     * role's permissions); everyone else gets exactly their collaborator role's
     * permissions. Non-members get nothing. This is what policies consult
     * instead of comparing hardcoded role names.
     *
     * @return string[] Permission slugs the user holds on this blog
     */
    public function blogPermissionsFor(int $blogId, int $userId, int $ownerId): array
    {
        if ($ownerId === $userId) {
            return $this->permissionsForRole('blog_owner');
        }

        $role = $this->storedRoleFor($blogId, $userId);

        return $role === null ? [] : $this->permissionsForRole($role);
    }

    /**
     * Every blog-scoped permission slug a user holds across all their blogs.
     *
     * Unions the owner bundle (for blogs they own) with each collaborator role's
     * permissions (for blogs they are an active member of), de-duplicated. One
     * query, so callers can answer "can this user ever do X anywhere?" with a
     * cheap array check instead of resolving permissions blog by blog.
     *
     * @return string[] Distinct permission slugs (empty for a reader-only user)
     */
    public function aggregateBlogPermissions(int $userId): array
    {
        $sql = "
            SELECT p.permission_slug
            FROM blogs b
            JOIN roles r ON r.role_slug = 'blog_owner'
            JOIN role_permissions rp ON rp.role_id = r.id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE b.owner_id = ?

            UNION

            SELECT p.permission_slug
            FROM blog_users bu
            JOIN roles r ON r.role_slug = bu.role
            JOIN role_permissions rp ON rp.role_id = r.id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE bu.user_id = ? AND bu.is_active = 1
        ";

        return $this->database->query($sql, [$userId, $userId])->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Whether the user owns at least one blog.
     *
     * Ownership is structural (blogs.owner_id), so this is distinct from holding
     * a permission. Drives the owner-only comment firehose toggle.
     */
    public function userOwnsAnyBlog(int $userId): bool
    {
        $sql = 'SELECT EXISTS(SELECT 1 FROM blogs WHERE owner_id = ?)';

        return (bool) $this->database->query($sql, [$userId])->fetchColumn();
    }

    /**
     * Update a blog and invalidate related caches.
     *
     * Invalidates all cached blog URLs and post listings when blog data changes.
     * If slug changes, invalidates both old and new URLs.
     *
     * @param  int|string  $id  Blog ID
     * @param  array<string, mixed>  $data  Updated blog data
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
            fragment()->forget('blog-by-slug:'.$blog->slug(), false);

            // If slug changed, invalidate new URL too
            if (isset($data['slug']) && $data['slug'] !== $blog->slug()) {
                cache()->deletePattern("*:GET:/blog/{$data['slug']}/*");
                fragment()->forget('blog-by-slug:'.$data['slug'], false);
            }

            // Invalidate blog listings
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Get all posts belonging to a blog.
     *
     * @param  int  $blogId  Blog ID
     * @return array<int, array<string, mixed>> Array of post records
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
     * @return array<int, array<string, mixed>> Array of blog records with owner_name, post_count, author_count
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
     * Every blog as `id => name`, for filter dropdowns.
     *
     * Deliberately two columns and no counts: the admin filters only need a label,
     * and getAllBlogsWithOwnerAndCounts() runs two correlated subqueries per row.
     *
     * @return array<int, string>
     */
    public function getAllForSelect(): array
    {
        $rows = $this->database
            ->query('SELECT id, blog_name FROM blogs ORDER BY blog_name ASC')
            ->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) $row['blog_name'];
        }

        return $options;
    }

    /**
     * Paginated admin listing with optional name/slug/owner search.
     *
     * @param  string  $theme  Theme directory name to match; the literal
     *                         'none' matches blogs with no theme set. A theme
     *                         that really is called "none" would be ambiguous
     *                         here, so the caller resolves that first and only
     *                         passes the sentinel once it has ruled the key out.
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>} Same shape as UserModel::findAllForAdmin()
     */
    public function findAllForAdmin(
        int $page = 1,
        int $perPage = 20,
        string $q = '',
        string $status = '',
        string $featured = '',
        string $orderBy = 'b.published_at DESC',
        string $theme = ''
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $conditions = [];
        $params = [];

        if ($q !== '') {
            $conditions[] = '(b.blog_name LIKE :q_name OR b.blog_slug LIKE :q_slug OR u.username LIKE :q_owner)';
            $term = '%'.$q.'%';
            $params[':q_name'] = $term;
            $params[':q_slug'] = $term;
            $params[':q_owner'] = $term;
        }

        if (in_array($status, self::STATUSES, true)) {
            $conditions[] = 'b.status = :status';
            $params[':status'] = $status;
        }

        if ($featured === 'yes' || $featured === 'no') {
            $conditions[] = 'b.is_featured = :featured';
            $params[':featured'] = $featured === 'yes' ? 1 : 0;
        }

        if ($theme === 'none') {
            $conditions[] = "(bs.theme IS NULL OR bs.theme = '')";
        } elseif ($theme !== '') {
            $conditions[] = 'bs.theme = :theme';
            $params[':theme'] = $theme;
        }

        $where = $conditions === [] ? '' : 'WHERE '.implode(' AND ', $conditions);

        // LEFT, not INNER: a blog with no settings row yet still belongs in the
        // listing, and is exactly what the 'none' theme filter is looking for.
        $joins = 'INNER JOIN users u ON b.owner_id = u.id
                  LEFT JOIN blog_settings bs ON bs.blog_id = b.id';

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM blogs b {$joins} {$where}",
            $params
        )->fetchColumn();

        // $orderBy comes from a TableSort whitelist, never from raw input.
        $sql = "SELECT b.*, u.username as owner_name, bs.theme as theme,
                    (SELECT COUNT(*) FROM posts WHERE blog_id = b.id) as post_count,
                    (SELECT COUNT(*) FROM blog_users WHERE blog_id = b.id AND is_active = 1) as author_count
                FROM blogs b
                {$joins}
                {$where}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;

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
     * Get all blogs owned by a user with aggregate counts.
     *
     * @param  int  $ownerId  User ID
     * @return array<int, array<string, mixed>> Array of blog records with owner_name, post_count, author_count
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
     * @param  int  $blogId  Blog ID
     * @return array<string, mixed>|null Blog record with owner_name, post_count, author_count, or null if not found
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
     * @param  int  $ownerId  User ID
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
     * @param  int  $ownerId  User ID
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
     * @param  array<string, mixed>  $data  Blog data (blog_name, blog_slug, description, owner_id)
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

        // Clear any cached 404 for this slug from before the blog existed.
        fragment()->forget('blog-by-slug:'.$data['blog_slug'], false);

        return (int) $this->database->lastInsertId();
    }

    /**
     * Get a blog by ID with owner username.
     *
     * @param  int  $id  Blog ID
     * @return array<string, mixed>|null Blog record with owner_name, or null if not found
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
     * @param  int  $ownerId  User ID
     * @return array<int, array<string, mixed>> Array of blog records
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
     * @param  int  $blogId  Blog ID
     * @return array<int, array<string, mixed>> Array of blog_users records with username and email
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
     * The structural owner of a blog (blogs.owner_id), with identity fields.
     *
     * Ownership lives on the blog itself, not in blog_users, so the admin
     * roster reads it separately to show the full team.
     *
     * @return array<string, mixed>|null Owner user row, or null if missing
     */
    public function getBlogOwner(int $blogId): ?array
    {
        $sql = 'SELECT u.id, u.username, u.email
                FROM blogs b
                INNER JOIN users u ON u.id = b.owner_id
                WHERE b.id = ?';

        return $this->database->query($sql, [$blogId])->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get all active users not yet assigned to a blog.
     *
     * Returns users available for assignment, excluding those already active on the blog.
     * Global user roles do not affect per-blog role eligibility.
     *
     * @param  int  $blogId  Blog ID
     * @return array<int, array<string, mixed>> Array of user records with id, username, email
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
     * @param  int  $blogId  Blog ID
     * @param  int  $userId  User ID to assign
     * @param  string  $role  Collaborative role (any assignable blog role)
     * @param  int  $assignedBy  User ID performing the assignment
     * @return bool True on success
     *
     * @throws \InvalidArgumentException If role is not an assignable blog role
     */
    public function addUserToBlog(int $blogId, int $userId, string $role, int $assignedBy): bool
    {
        // Validate against the assignable blog roles (shipped plus custom),
        // never the hardcoded const, so custom admin-created roles can be assigned.
        if (!in_array($role, $this->availableCollaboratorRoles(), true)) {
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

        return $rowCount > 0;
    }

    /**
     * Revoke a user's access to a blog.
     *
     * Performs soft delete by setting is_active=0 and recording revoked_at timestamp.
     * Preserves record for audit trail and potential restoration.
     *
     * @param  int  $blogId  Blog ID
     * @param  int  $userId  User ID to revoke
     * @return bool True on success
     */
    public function revokeUserFromBlog(int $blogId, int $userId): bool
    {
        $sql = 'UPDATE blog_users
                SET is_active = 0, revoked_at = NOW()
                WHERE blog_id = ? AND user_id = ? AND is_active = 1';

        $rowCount = $this->database->execute($sql, [$blogId, $userId]);

        return $rowCount > 0;
    }

    /**
     * Change blog status to draft.
     *
     * @param  int  $id  Blog ID
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
     * @param  int  $id  Blog ID
     * @return bool True on success
     */
    public function publishBlog(int $id): bool
    {
        $sql = "UPDATE blogs SET status = 'published' WHERE id = ?";

        return $this->database->execute($sql, [$id]) > 0;
    }

    /**
     * Published blogs with the locale data the sitemap needs.
     *
     * Separate from getDirectoryWithPagination because that one feeds the explore
     * listing and carries presentation fields this does not want, and because a
     * sitemap wants every blog rather than a page of them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findPublicForSitemap(int $limit = 1000): array
    {
        $sql = "SELECT b.blog_slug,
                       COALESCE(s.default_locale, 'en') AS default_locale,
                       COALESCE(s.translations_enabled, 0) AS translations_enabled,
                       GROUP_CONCAT(DISTINCT t.locale) AS translated_locales,
                       MAX(p.published_at) AS last_post_at
                FROM blogs b
                LEFT JOIN blog_settings s ON s.blog_id = b.id
                LEFT JOIN posts p ON p.blog_id = b.id
                     AND p.status = 'published' AND p.visibility = 'public'
                LEFT JOIN post_translations t ON t.post_id = p.id
                WHERE b.status = 'published'
                GROUP BY b.id, b.blog_slug, s.default_locale, s.translations_enabled
                ORDER BY b.id
                LIMIT :limit";

        return $this->database->query($sql, [':limit' => $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get a blog by its slug.
     *
     * @param  string  $slug  Blog slug
     * @return array<string, mixed>|null Blog record, or null if not found
     */
    public function getBlogBySlug(string $slug): ?array
    {
        $cacheKey = 'blog-by-slug:'.$slug;

        $loadBlog = function () use ($slug): ?array {
            $sql = 'SELECT * FROM blogs WHERE blog_slug = ?';

            return $this->database->query($sql, [$slug])->fetch(\PDO::FETCH_ASSOC) ?: null;
        };

        // Hit on every blog page; busted from createBlog/update/delete. Creation
        // also clears the key so a pre-creation 404 lookup can't linger.
        return fragment()->rememberData($cacheKey, $loadBlog, 3600, false);
    }

    /**
     * Find multiple blogs by their IDs.
     *
     * Returns minimal blog data (id, blog_slug, blog_name) for efficient
     * lookups. Used to enrich posts with blog links without N+1 queries.
     *
     * @param  array<int>  $ids  Blog IDs to fetch
     * @return array<int, array<string, mixed>> Array of blog records with id, blog_slug, and blog_name
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "SELECT id, blog_slug, blog_name FROM blogs WHERE id IN ($placeholders)";
        $stmt = $this->database->query($sql, $ids);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Admin-picked blogs for the explore page's featured section.
     *
     * Curation replaces the old "most posts wins" ordering so nobody can spam
     * their way onto a platform owned surface. Only published blogs with the
     * admin flag appear; an empty result simply hides the section.
     *
     * @param  int  $limit  Maximum number of blogs to return
     * @return array<int, array<string, mixed>> Array of blog records with ownername and postcount
     */
    public function getFeaturedCreators(int $limit = 4): array
    {
        $sql = "
            SELECT b.*, u.username AS ownername,
                (SELECT COUNT(*) FROM posts p WHERE p.blog_id = b.id AND p.status = 'published') AS postcount,
                (SELECT COUNT(*) FROM blog_users bu WHERE bu.blog_id = b.id AND bu.is_active = 1) AS authorcount
            FROM blogs b
            INNER JOIN users u ON b.owner_id = u.id
            WHERE b.is_featured = 1 AND b.status = 'published'
            ORDER BY b.published_at DESC
            LIMIT ?
        ";
        $stmt = $this->database->query($sql, [$limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Platform-wide count of published blogs, for the front page stats strip.
     */
    public function countPublished(): int
    {
        $sql = "SELECT COUNT(*) FROM blogs WHERE status = 'published'";

        return (int) $this->database->query($sql)->fetchColumn();
    }

    /**
     * Toggle the admin-only explore page flag on a blog.
     */
    public function setExploreFeatured(int $blogId, bool $on): bool
    {
        $rows = $this->database->execute(
            'UPDATE blogs SET is_featured = ? WHERE id = ?',
            [$on ? 1 : 0, $blogId]
        );

        return $rows >= 0;
    }

    /**
     * Public blog directory: published blogs that have at least one published post.
     *
     * Powers the Blogs tab on the explore page. Empty shells are excluded so
     * the directory only lists blogs a visitor can actually read.
     *
     * @param  int  $page  Page number
     * @param  int  $perPage  Blogs per page
     * @param  string  $search  Optional name/description search term
     * @return array{data: array<int, array<string, mixed>>, totalPages: int, currentPage: int, perPage: int, totalBlogs: int}
     */
    public function getDirectoryWithPagination(int $page = 1, int $perPage = 12, string $search = ''): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $where = "b.status = 'published'
                  AND EXISTS (SELECT 1 FROM posts p WHERE p.blog_id = b.id AND p.status = 'published')";
        $params = [];

        if ($search !== '') {
            $where .= ' AND (b.blog_name LIKE :q_name OR b.description LIKE :q_desc)';
            $term = '%'.$search.'%';
            $params[':q_name'] = $term;
            $params[':q_desc'] = $term;
        }

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM blogs b WHERE {$where}",
            $params
        )->fetchColumn();

        $sql = "SELECT b.*, u.username AS owner_name,
                    (SELECT COUNT(*) FROM posts p WHERE p.blog_id = b.id AND p.status = 'published') AS post_count,
                    (SELECT COUNT(*) FROM blog_users bu WHERE bu.blog_id = b.id AND bu.is_active = 1) AS author_count,
                    (SELECT MAX(p.published_at) FROM posts p WHERE p.blog_id = b.id AND p.status = 'published') AS last_post_at
                FROM blogs b
                INNER JOIN users u ON b.owner_id = u.id
                WHERE {$where}
                ORDER BY last_post_at DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        return [
            'data' => $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC),
            'totalPages' => (int) ceil($total / $perPage),
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalBlogs' => $total,
        ];
    }

    /**
     * Get a blog wrapped in BlogResource.
     *
     * @param  string|int  $id  Blog ID
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
     * @param  string|int  $owner_id  User ID
     * @return BlogResource[] Array of BlogResource instances, or empty array if none found
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
     * Get all blogs accessible to a user — both owned and collaborated.
     *
     * Returns the same shape as getBlogsByOwnerWithCounts() plus a `user_role`
     * column: 'owner' for owned blogs, or the collaborative role from blog_users.
     * Used by the dashboard to surface blogs for non-owner collaborators.
     *
     * @return array<int, array<string, mixed>> Blog rows with counts and user_role
     */
    public function getAccessibleBlogs(int $userId): array
    {
        $sql = "
            SELECT b.*, u.username AS owner_name,
                (SELECT COUNT(*) FROM posts WHERE blog_id = b.id) AS post_count,
                (SELECT COUNT(*) FROM blog_users WHERE blog_id = b.id AND is_active = 1) AS author_count,
                'owner' AS user_role
            FROM blogs b
            INNER JOIN users u ON b.owner_id = u.id
            WHERE b.owner_id = ?

            UNION

            SELECT b.*, u.username AS owner_name,
                (SELECT COUNT(*) FROM posts WHERE blog_id = b.id) AS post_count,
                (SELECT COUNT(*) FROM blog_users WHERE blog_id = b.id AND is_active = 1) AS author_count,
                bu.role AS user_role
            FROM blogs b
            INNER JOIN users u ON b.owner_id = u.id
            INNER JOIN blog_users bu ON bu.blog_id = b.id AND bu.user_id = ? AND bu.is_active = 1
            WHERE b.owner_id != ?

            ORDER BY updated_at DESC
        ";

        return $this->database->query($sql, [$userId, $userId, $userId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Delete a blog and invalidate related caches.
     *
     * Performs hard delete from database. Cascading deletes should be handled
     * at application level for audit trail (posts, collaborators, etc.).
     *
     * @param  int|string  $id  Blog ID
     * @return bool True on success
     */
    public function delete(int|string $id): bool
    {
        $blog = $this->getBlog($id);

        $result = parent::delete($id);

        if ($result && $blog) {
            // Invalidate all posts in this blog
            cache()->deletePattern("*:GET:/blog/{$blog->slug()}/*");
            fragment()->forget('blog-by-slug:'.$blog->slug(), false);

            // Invalidate blog listings
            cache()->deletePattern('*:GET:/blogs*');
        }

        return $result;
    }

    /**
     * Get active users on a blog who hold any of the given roles.
     *
     * Returns rows of user_id + username + role, including the owner when
     * 'owner' is in the role list (owner data lives on the blog row, not
     * blog_users). Used by workflow notifications to find recipients.
     *
     * @param  array<int,string>  $roles  e.g. ['owner','editor','reviewer']
     * @return array<int,array{user_id:int,username:string,email:string,role:string}>
     */
    public function getActiveUsersWithRoles(int $blogId, array $roles): array
    {
        $roles = array_values(array_unique(array_filter($roles, 'is_string')));
        if (empty($roles)) {
            return [];
        }

        $rows = [];

        $includeOwner = in_array('owner', $roles, true);
        $collabRoles = array_values(array_diff($roles, ['owner']));

        if ($includeOwner) {
            $sql = 'SELECT u.id AS user_id, u.username, u.email, ? AS role
                    FROM blogs b
                    INNER JOIN users u ON u.id = b.owner_id
                    WHERE b.id = ?';
            $rows = array_merge($rows, $this->database->query($sql, ['owner', $blogId])->fetchAll(\PDO::FETCH_ASSOC));
        }

        if (!empty($collabRoles)) {
            $placeholders = implode(',', array_fill(0, count($collabRoles), '?'));
            $sql = "SELECT u.id AS user_id, u.username, u.email, bu.role AS role
                    FROM blog_users bu
                    INNER JOIN users u ON u.id = bu.user_id
                    WHERE bu.blog_id = ?
                      AND bu.is_active = 1
                      AND bu.role IN ({$placeholders})";
            $params = array_merge([$blogId], $collabRoles);
            $rows = array_merge($rows, $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC));
        }

        // De-dup: an owner who also has a blog_users row shouldn't get the notification twice.
        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int) $r['user_id'];
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = $r;
            }
        }

        return array_values($byUser);
    }

    /**
     * Count active editors on a blog.
     *
     * Drives the "you're about to revoke the only editor" guard on the team page.
     */
    public function countActiveByRole(int $blogId, string $role): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM blog_users
                WHERE blog_id = ? AND role = ? AND is_active = 1';
        $stmt = $this->database->query($sql, [$blogId, $role]);

        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /**
     * Check if a user is an active collaborator on any blog they don't own.
     *
     * Drives the global "Shared" nav item — visible only when the user has
     * inbound access on someone else's blog.
     *
     * @param  int  $userId  User ID
     * @return bool True if the user has at least one active blog_users row
     */
    public function userIsCollaborator(int $userId): bool
    {
        $sql = 'SELECT 1 FROM blog_users
                WHERE user_id = ? AND is_active = 1
                LIMIT 1';

        return (bool) $this->database->query($sql, [$userId])->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * Whether the user is in reader mode: owns no blog and collaborates on none.
     *
     * This single definition drives the reader/creator dashboard split, the
     * reader navigation, and the blog-front header label.
     */
    public function userIsReaderOnly(int $userId): bool
    {
        $sql = 'SELECT NOT EXISTS(SELECT 1 FROM blogs WHERE owner_id = ?)
                   AND NOT EXISTS(SELECT 1 FROM blog_users WHERE user_id = ? AND is_active = 1)';

        return (bool) $this->database->query($sql, [$userId, $userId])->fetchColumn();
    }

    /**
     * Get blogs shared with a user (excludes blogs they own).
     *
     * Returns the per-blog role the user holds via blog_users plus a few
     * lightweight fields needed by the Shared landing page.
     *
     * @param  int  $userId  User ID
     * @return array<int,array<string,mixed>>
     */
    public function getSharedBlogsForUser(int $userId): array
    {
        $sql = 'SELECT b.id, b.blog_name, b.blog_slug, b.status, b.owner_id,
                       u.username AS owner_name,
                       bu.role AS user_role
                FROM blogs b
                INNER JOIN users u ON u.id = b.owner_id
                INNER JOIN blog_users bu ON bu.blog_id = b.id
                WHERE bu.user_id = ?
                  AND bu.is_active = 1
                  AND b.owner_id != ?
                ORDER BY b.updated_at DESC';

        return $this->database->query($sql, [$userId, $userId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user has a reviewer-capable role on any active blog.
     *
     * Used to decide whether to show the review queue panel on the dashboard.
     *
     * @param  int  $userId  User ID
     * @return bool True if the user has reviewer, editor, or owner role on any blog
     */
    public function userHasReviewerRole(int $userId): bool
    {
        $sql = 'SELECT 1 FROM blog_users
                WHERE user_id = ? AND is_active = 1
                  AND role IN (\'reviewer\', \'editor\', \'owner\')
                LIMIT 1';

        return (bool) $this->database->query($sql, [$userId])->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * Check whether a user can access a specific blog (owner or active collaborator).
     *
     * Used to gate preview of unpublished posts for authenticated blog members.
     *
     * @param  int  $userId  User to check
     * @param  int  $blogId  Blog to check against
     * @return bool True if the user owns the blog or is an active collaborator
     */
    public function userCanAccessBlog(int $userId, int $blogId): bool
    {
        $sql = 'SELECT 1 FROM blogs WHERE id = ? AND owner_id = ?
                UNION
                SELECT 1 FROM blog_users WHERE blog_id = ? AND user_id = ? AND is_active = 1
                LIMIT 1';

        return (bool) $this->database->query($sql, [$blogId, $userId, $blogId, $userId])->fetch(\PDO::FETCH_COLUMN);
    }

    /**
     * Count active collaborators for a blog.
     *
     * Use this to show deletion impact before removing a blog.
     *
     * @param  int  $blogId  Blog ID
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
     * @param  int  $blogId  Blog ID
     * @return int Number of rows deleted
     */
    public function deleteCollaboratorsByBlogId(int $blogId): int
    {
        $sql = 'DELETE FROM blog_users WHERE blog_id = ?';

        return $this->database->execute($sql, [$blogId]);
    }
}
