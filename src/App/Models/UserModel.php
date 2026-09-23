<?php

declare(strict_types=1);

namespace App\Models;

use App\Resources\UserResource;
use App\Traits\SoftDeletes;
use Exception;

/**
 * UserModel
 *
 * Expect all input to be pre-validated by the Validator service.
 * This model handles:
 * - User CRUD operations
 * - Role and permission queries
 * - Domain-specific data access
 */
class UserModel extends AppModel
{
    use SoftDeletes;

    protected ?string $table = 'users';

    /**
     * Daily signup counts for the last N days, for the admin overview chart.
     *
     * Days with no signups are filled with zero so the chart has a bar
     * for every day in the window.
     *
     * @param  int  $days  Window size in days
     * @return array<string, int> Y-m-d date => signups
     */
    public function signupsByDay(int $days = 30): array
    {
        $days = max(1, min(90, $days));

        $sql = "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
                FROM {$this->getTable()}
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)";

        $rows = [];
        foreach ($this->database->query($sql, [$days - 1])->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[(string) $row['day']] = (int) $row['cnt'];
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $series[$day] = $rows[$day] ?? 0;
        }

        return $series;
    }

    /**
     * Find a single user by email address.
     *
     * Exclude soft-deleted users from authentication attempts.
     *
     * @param  string  $email  Email address to search
     * @return array<string,mixed>|null User data or null if not found
     */
    public function findByEmail(string $email): ?array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' 
                WHERE email = ? AND deleted_at IS NULL 
                LIMIT 1';

        $stmt = $this->database->query($sql, [$email]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Find user wrapped in a Resource for policy checks.
     *
     * Return UserResource instead of raw array to enable Gate authorization.
     *
     * @param  string|int  $id  User ID
     * @return UserResource|false User resource or false if not found
     */
    public function findResource(string|int $id): UserResource|false
    {
        if (!$found = $this->findById($id)) {
            return false;
        }

        // Load roles for policy authorization
        $found['roles'] = $this->getUserRoles((int) $id);

        return new UserResource($found);
    }

    /**
     * Find user by ID, excluding soft-deleted users.
     *
     * Override parent to add soft delete filtering.
     *
     * @param  int  $id  User ID
     * @return array<string,mixed>|null User data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' 
                WHERE id = ? AND deleted_at IS NULL 
                LIMIT 1';

        $stmt = $this->database->query($sql, [$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Find all users, excluding soft-deleted.
     *
     * Override parent to add soft delete filtering.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = 'SELECT * FROM '.$this->getTable().' WHERE deleted_at IS NULL';
        $stmt = $this->database->query($sql);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert multiple role assignments for a user.
     *
     * Returns true on success, false on failure.
     * Errors are logged instead of being sent to the browser.
     *
     * @param  int  $userId  User ID
     * @param  int[]  $roles  Array of role IDs to assign
     * @return bool True on success
     */
    /**
     * Keep only the role ids that are system-scoped.
     *
     * The account-wide user_roles table is for control-panel roles; blog roles
     * belong in blog_users. Enforcing that here means no controller or crafted
     * request can bind a blog role globally.
     *
     * @param  int[]|string[]  $roleIds  Candidate role ids
     * @return int[] The subset that is system-scoped
     */
    private function keepSystemRoleIds(array $roleIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->database->query(
            "SELECT id FROM roles WHERE scope = 'system' AND id IN ($placeholders)",
            $ids
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $rows ?: []);
    }

    /**
     * Insert system roles for a user.
     *
     * @param  int[]  $roles
     */
    public function insertUserRoles(int $userId, array $roles): bool
    {
        // Accounts hold system roles only; drop any blog-scoped ids so a crafted
        // request can never bind a blog role globally.
        $roles = $this->keepSystemRoleIds($roles);

        // If there are no roles, treat this as a no-op success
        if (empty($roles)) {
            return true;
        }

        $sql = 'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)';

        try {
            // execute the same query for each role with different parameters
            foreach ($roles as $roleId) {
                $this->database->execute($sql, [$userId, $roleId]);
            }

            return true;
        } catch (\PDOException $e) {
            error_log('insertUserRoles PDOException: '.$e->getMessage());

            return false;
        } catch (\Exception $e) {
            error_log('insertUserRoles Exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Update user roles by replacing existing assignments.
     *
     * Caller is responsible for wrapping in transaction if atomicity is required.
     *
     * @param  int  $userId  User ID
     * @param  int[]  $newRoles  Array of new role IDs
     * @return bool True on success
     */
    public function updateUserRoles(int $userId, array $newRoles): bool
    {
        // Accounts hold system roles only. Filtering here keeps the diff below
        // from ever removing or adding a blog-scoped assignment.
        $newRoles = $this->keepSystemRoleIds($newRoles);

        try {
            // Step 1: Get current role assignments to compute changes
            $stmt = $this->database->query('SELECT role_id FROM user_roles WHERE user_id = ?', [$userId]);
            $currentRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Step 2: Calculate roles to add and remove
            $toAdd = array_diff($newRoles, $currentRoles);
            $toRemove = array_diff($currentRoles, $newRoles);

            // Step 3: Delete removed roles (batch)
            if (!empty($toRemove)) {
                $placeholders = implode(',', array_fill(0, count($toRemove), '?'));
                $sql = "DELETE FROM user_roles WHERE user_id = ? AND role_id IN ($placeholders)";
                $this->database->execute($sql, array_merge([$userId], $toRemove));
            }

            // Step 4: Insert new roles (batch)
            if (!empty($toAdd)) {
                $values = [];
                $params = [];
                foreach ($toAdd as $roleId) {
                    $values[] = '(?, ?)';
                    $params[] = $userId;
                    $params[] = $roleId;
                }

                $sql = 'INSERT INTO user_roles (user_id, role_id) VALUES '.implode(',', $values);
                $this->database->execute($sql, $params);
            }

            return true;
        } catch (\Throwable $e) {
            error_log('updateUserRoles failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Paginated user listing for the admin panel, roles aggregated in one
     * query instead of one lookup per row.
     *
     * @param  int  $page  Current page (1-based)
     * @param  int  $perPage  Rows per page (capped at 100)
     * @param  string  $q  Optional handle/email/name search term
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function findAllForAdmin(
        int $page = 1,
        int $perPage = 20,
        string $q = '',
        string $active = '',
        string $role = '',
        string $orderBy = 'u.created_at DESC',
        ?int $excludeUserId = null
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $where = 'WHERE u.deleted_at IS NULL';
        $params = [];

        if ($excludeUserId !== null) {
            $where .= ' AND u.id <> :exclude_id';
            $params[':exclude_id'] = $excludeUserId;
        }

        if ($q !== '') {
            $where .= " AND (u.handle LIKE :q_handle OR u.email LIKE :q_email
                        OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE :q_name)";
            $term = '%'.$q.'%';
            $params[':q_handle'] = $term;
            $params[':q_email'] = $term;
            $params[':q_name'] = $term;
        }

        if ($active === 'yes' || $active === 'no') {
            $where .= ' AND u.is_active = :is_active';
            $params[':is_active'] = $active === 'yes' ? 1 : 0;
        }

        // Role lives on a join table, so filtering needs EXISTS rather than a
        // WHERE on the GROUP_CONCAT (which is only computed after grouping).
        if ($role !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM user_roles ur2
                                    INNER JOIN roles r2 ON r2.id = ur2.role_id
                                    WHERE ur2.user_id = u.id AND r2.role_slug = :role_slug)';
            $params[':role_slug'] = $role;
        }

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} u {$where}",
            $params
        )->fetchColumn();

        $offset = ($page - 1) * $perPage;

        // $orderBy comes from a TableSort whitelist, never from raw input.
        // The roles column is the account's site role only (scope=system);
        // blog involvement is summarized separately as owned/member counts so
        // the list shows all three access axes at a glance.
        $sql = "SELECT u.id, u.handle, u.email, u.first_name, u.last_name,
                       u.is_active, u.created_at, u.last_login, u.posts_count,
                       u.suspended_at, u.suspended_until,
                       COALESCE(GROUP_CONCAT(r.role_slug ORDER BY r.role_slug SEPARATOR ','), '') AS roles,
                       (SELECT COUNT(*) FROM blogs b2 WHERE b2.owner_id = u.id) AS owned_blogs,
                       (SELECT COUNT(*) FROM blog_users bu2 WHERE bu2.user_id = u.id AND bu2.is_active = 1) AS member_blogs,
                       u.reports_paused_until,
                       (SELECT COUNT(*) FROM moderation_cases mc WHERE mc.subject_author_id = u.id AND mc.resolution IS NULL) AS open_cases,
                       (SELECT COUNT(*) FROM content_reports cr WHERE cr.reporter_id = u.id AND cr.outcome = 'unfounded') AS unfounded_reports
                FROM {$this->getTable()} u
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r ON r.id = ur.role_id AND r.scope = 'system'
                {$where}
                GROUP BY u.id
                ORDER BY {$orderBy}
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
     * Get the slugs of roles assigned to a user.
     *
     * Example return: ['administrator', 'author'].
     *
     * @param  int  $userId  User ID
     * @return string[] Array of role slugs
     */
    public function getUserRoles(int $userId): array
    {
        // Only system-scoped roles live on an account globally. Blog roles are
        // held per blog in blog_users, so they must never surface here or they
        // would grant their permissions site-wide with no blog binding.
        $sql = "SELECT r.role_slug
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ? AND r.scope = 'system'";

        $stmt = $this->database->query($sql, [$userId]);

        /** @var string[]|false $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $rows ?: [];
    }

    /**
     * Get distinct permission slugs for a user (via roles).
     *
     * Example return: ['edit_post', 'delete_comment'].
     *
     * @param  int  $userId  User ID
     * @return string[] Array of permission slugs
     */
    public function getUserPermissions(int $userId): array
    {
        // Global permissions come only from system-scoped roles. Blog-role
        // permissions are contextual (resolved per blog in BlogModel), never
        // part of the account-wide set.
        $sql = "SELECT DISTINCT p.permission_slug
                FROM user_roles ur
                JOIN roles r ON r.id = ur.role_id
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ? AND r.scope = 'system'";

        $stmt = $this->database->query($sql, [$userId]);

        /** @var string[]|false $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $rows ?: [];
    }

    /**
     * Convenience wrapper: find users by handle.
     *
     * @param  string  $handle  Handle to search
     * @return array<int,array<string,mixed>>
     */
    public function findByHandle(string $handle): array
    {
        return $this->findBy('handle', $handle);
    }

    /**
     * Update a user by id.
     *
     * Returns true on success, false on failure.
     * Column names are validated before being interpolated into SQL.
     *
     * @param  int  $id  User ID
     * @param  array<string, mixed>  $data  Associative array of column => value pairs
     * @return bool True on success
     *
     * @throws Exception If invalid column name provided
     */
    public function updateById(int $id, array $data): bool
    {
        // If there is nothing to update, treat as success
        if (empty($data)) {
            return true;
        }

        $sets = [];
        $params = [];

        foreach ($data as $k => $v) {
            // Validate column names to prevent SQL injection via dynamic keys
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $k)) {
                throw new Exception("Invalid column name '{$k}' in updateById.");
            }

            $sets[] = "{$k} = ?";
            $params[] = $v;
        }

        // append id as the last parameter for WHERE clause
        $params[] = $id;

        $sql = 'UPDATE '.$this->getTable().' SET '.implode(', ', $sets).' WHERE id = ?';

        $rowCount = $this->database->execute($sql, $params);

        return $rowCount > 0;
    }

    /**
     * Count how many posts a user has authored.
     *
     * @param  int  $userId  User ID
     * @return int Post count
     */
    public function countPosts(int $userId): int
    {
        $sql = 'SELECT COUNT(*) FROM posts WHERE author_id = ?';
        $stmt = $this->database->query($sql, [$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count how many blogs a user owns.
     *
     * @param  int  $userId  User ID
     * @return int Blog count
     */
    public function countBlogs(int $userId): int
    {
        $sql = 'SELECT COUNT(*) FROM blogs WHERE owner_id = ?';
        $stmt = $this->database->query($sql, [$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count how many comments a user's posts have received.
     *
     * @param  int  $userId  User ID
     * @return int Comment count
     */
    public function countCommentsReceived(int $userId): int
    {
        $sql = 'SELECT COUNT(c.id)
                FROM comments c
                JOIN posts p ON p.id = c.post_id
                WHERE p.author_id = ?';

        $stmt = $this->database->query($sql, [$userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count distinct users with at least one published post.
     *
     * Powers the front page stats strip, so "writers" means people whose
     * writing a visitor can actually read, not raw signups.
     */
    public function countPublicWriters(): int
    {
        $sql = "SELECT COUNT(DISTINCT p.author_id)
                FROM posts p
                JOIN users u ON u.id = p.author_id
                WHERE p.status = 'published'
                AND EXISTS (SELECT 1 FROM blogs pb WHERE pb.id = p.blog_id AND pb.status = 'published')
                AND u.is_active = 1
                AND u.deleted_at IS NULL";

        return (int) $this->database->query($sql)->fetchColumn();
    }

    /**
     * Count total active administrator users.
     *
     * Used to prevent deletion of the last admin account.
     *
     * @return int Number of active administrators
     */
    public function countAdministrators(): int
    {
        $sql = "SELECT COUNT(DISTINCT u.id)
                FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                JOIN roles r ON ur.role_id = r.id
                WHERE r.role_slug = 'administrator'
                AND u.is_active = 1
                AND u.deleted_at IS NULL";

        $stmt = $this->database->query($sql);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Active users who can act on a given SystemPolicy permission slug.
     *
     * An administrator role always qualifies, matching SystemPolicy's own
     * "administrators pass everything" rule, so this never has to be kept in
     * sync with the AREA_PERMISSIONS map by hand.
     *
     * @param  string  $permission  A SystemPolicy::AREA_PERMISSIONS slug
     * @return array<int, array{id: int, handle: string}>
     */
    public function findAllWithPermission(string $permission): array
    {
        $sql = "SELECT DISTINCT u.id, u.handle
                FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                JOIN roles r ON r.id = ur.role_id
                LEFT JOIN role_permissions rp ON rp.role_id = r.id
                LEFT JOIN permissions p ON p.id = rp.permission_id
                WHERE r.scope = 'system'
                  AND u.is_active = 1
                  AND u.deleted_at IS NULL
                  AND (r.role_slug = 'administrator' OR p.permission_slug = ?)";

        return $this->database->query($sql, [$permission])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Give the account exactly one system role, replacing whatever it held.
     *
     * One transaction, and it throws: a half-applied role change is how an
     * account ends up with no role, or with two, without anyone noticing.
     *
     * @throws \InvalidArgumentException When $roleId is not a system-scope role
     */
    public function setSystemRole(int $userId, int $roleId, int $assignedBy): void
    {
        $isSystemRole = $this->database
            ->query("SELECT 1 FROM roles WHERE id = ? AND scope = 'system'", [$roleId])
            ->fetchColumn();

        if ($isSystemRole === false) {
            throw new \InvalidArgumentException("Role {$roleId} is not a site role.");
        }

        $apply = function () use ($userId, $roleId, $assignedBy): void {
            $this->database->execute(
                "DELETE ur FROM user_roles ur
                   JOIN roles r ON r.id = ur.role_id
                  WHERE ur.user_id = ? AND r.scope = 'system'",
                [$userId]
            );

            $this->database->execute(
                'INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)',
                [$userId, $roleId, $assignedBy]
            );
        };

        // Joins a caller's transaction rather than opening a second one, which
        // Database refuses, so this stays safe to call from inside one.
        $this->database->inTransaction() ? $apply() : $this->transaction($apply);
    }

    /**
     * End every session this account has open, on each one's next request.
     *
     * Sessions live in PHP's own store with no index by user, so they cannot be
     * found and deleted. Each remembers the epoch it signed in under instead,
     * and Auth drops any whose epoch no longer matches.
     */
    public function bumpSessionEpoch(int $userId): bool
    {
        return $this->database->execute(
            'UPDATE users SET session_epoch = session_epoch + 1 WHERE id = ?',
            [$userId]
        ) === 1;
    }

    /**
     * Pause this account's reporting until a UTC time, or end a pause with null.
     */
    public function setReportsPausedUntil(int $userId, ?string $until): void
    {
        $this->database->execute('UPDATE users SET reports_paused_until = ? WHERE id = ?', [$until, $userId]);
    }

    /**
     * Update user password hash.
     *
     * @param  int  $userId  User ID
     * @param  string  $hash  Password hash from password_hash()
     * @return bool True if password was updated
     */
    public function updatePasswordHashById(int $userId, string $hash): bool
    {
        $sql = 'UPDATE users SET password = ? WHERE id = ?';

        $rowCount = $this->database->execute($sql, [$hash, $userId]);

        return $rowCount > 0;
    }

    /**
     * Verify a user's password against stored hash.
     *
     * Used for password confirmation on sensitive operations
     * like account/blog deletion. Uses constant-time comparison
     * via password_verify() to prevent timing attacks.
     *
     * @param  int  $userId  User ID
     * @param  string  $password  Plain text password to verify
     * @return bool True if password matches stored hash
     */
    public function verifyPassword(int $userId, string $password): bool
    {
        $user = $this->findById($userId);

        if (!$user || empty($user['password'])) {
            return false;
        }

        return password_verify($password, $user['password']);
    }

    /**
     * Check if a handle is unique among existing users.
     *
     * @param  string  $handle  Handle to check
     * @param  int|null  $ignoreUserId  User ID to exclude from check (for profile updates)
     * @return bool True if unique
     */
    public function isHandleUnique(string $handle, ?int $ignoreUserId = null): bool
    {
        $sql = '
            SELECT 1
            FROM users
            WHERE handle = :handle
            '.($ignoreUserId !== null ? 'AND id <> :ignore_id' : '').'
            LIMIT 1
        ';

        $params = [':handle' => $handle];
        if ($ignoreUserId !== null) {
            $params[':ignore_id'] = $ignoreUserId;
        }

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchColumn() === false;
    }
}
