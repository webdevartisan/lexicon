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
     * Find a single user by email address.
     *
     * Exclude soft-deleted users from authentication attempts.
     *
     * @param string $email Email address to search
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
     * @param string|int $id User ID
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
     * @param int $id User ID
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
     * Restore a soft-deleted user account.
     *
     * Clear the deleted_at timestamp to reactivate the account.
     *
     * @param int $userId User ID to restore
     * @return bool True if user was restored
     */
    public function restoreDeleted(int $userId): bool
    {
        $sql = 'UPDATE '.$this->getTable().' 
                SET deleted_at = NULL 
                WHERE id = ? AND deleted_at IS NOT NULL';

        $rowCount = $this->database->execute($sql, [$userId]);
        return $rowCount > 0;
    }

    /**
     * Insert multiple role assignments for a user.
     *
     * Returns true on success, false on failure.
     * Errors are logged instead of being sent to the browser.
     *
     * @param int $userId User ID
     * @param array $roles Array of role IDs to assign
     * @return bool True on success
     */
    public function insertUserRoles(int $userId, array $roles): bool
    {
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
     * @param int $userId User ID
     * @param array $newRoles Array of new role IDs
     * @return bool True on success
     */
    public function updateUserRoles(int $userId, array $newRoles): bool
    {
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
     * Get the slugs of roles assigned to a user.
     *
     * Example return: ['administrator', 'author'].
     *
     * @param int $userId User ID
     * @return string[] Array of role slugs
     */
    public function getUserRoles(int $userId): array
    {
        $sql = 'SELECT r.role_slug
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?';

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
     * @param int $userId User ID
     * @return string[] Array of permission slugs
     */
    public function getUserPermissions(int $userId): array
    {
        $sql = 'SELECT DISTINCT p.permission_slug
                FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ?';

        $stmt = $this->database->query($sql, [$userId]);

        /** @var string[]|false $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $rows ?: [];
    }

    /**
     * Convenience wrapper: find users by username.
     *
     * @param string $userName Username to search
     * @return array<int,array<string,mixed>>
     */
    public function findByUsername(string $userName): array
    {
        return $this->findBy('username', $userName);
    }

    /**
     * Update a user by id.
     *
     * Returns true on success, false on failure.
     * Column names are validated before being interpolated into SQL.
     *
     * @param int $id User ID
     * @param array $data Associative array of column => value pairs
     * @return bool True on success
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
     * @param int $userId User ID
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
     * @param int $userId User ID
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
     * @param int $userId User ID
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
                AND u.deleted_at IS NULL";

        $stmt = $this->database->query($sql);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Update user password hash.
     *
     * @param int $userId User ID
     * @param string $hash Password hash from password_hash()
     * @return bool True if password was updated
     */
    public function updatePasswordHashById(int $userId, string $hash): bool
    {
        $sql = 'UPDATE users SET password = ? WHERE id = ?';
        
        $rowCount = $this->database->execute($sql, [$hash, $userId]);
        return $rowCount > 0;
    }

    /**
     * Check if a user can be deleted (simple content check).
     *
     * Prevent deletion if user has published content.
     * This is a simple data query, not complex business logic.
     * For full business rule validation, use UserDeletionService::canDeleteUser().
     *
     * @param int $userId User ID
     * @return bool True if user has no posts
     */
    public function canDelete(int $userId): bool
    {
        return $this->countPosts($userId) === 0;
    }

    /**
     * Verify a user's password against stored hash.
     *
     * Used for password confirmation on sensitive operations
     * like account/blog deletion. Uses constant-time comparison
     * via password_verify() to prevent timing attacks.
     *
     * @param int $userId User ID
     * @param string $password Plain text password to verify
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
     * Check if username is unique among existing users.
     *
     * @param string $username Username to check
     * @param int|null $ignoreUserId User ID to exclude from check (for profile updates)
     * @return bool True if unique
     */
    public function isUsernameUnique(string $username, ?int $ignoreUserId = null): bool
    {
        $sql = '
            SELECT 1
            FROM users
            WHERE username = :username
            '.($ignoreUserId !== null ? 'AND id <> :ignore_id' : '').'
            LIMIT 1
        ';

        $params = [':username' => $username];
        if ($ignoreUserId !== null) {
            $params[':ignore_id'] = $ignoreUserId;
        }

        $stmt = $this->database->query($sql, $params);

        return $stmt->fetchColumn() === false;
    }
}
