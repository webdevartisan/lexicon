<?php

declare(strict_types=1);

namespace App\Models;

/**
 * RoleModel
 *
 * Manages user roles and role-permission relationships.
 * Roles define access levels across the application.
 */
class RoleModel extends AppModel
{
    protected ?string $table = 'roles';

    /**
     * Get total role count.
     *
     * @return int Total roles
     */
    public function getTotal(): int
    {
        $sql = "SELECT COUNT(*) AS total FROM {$this->getTable()}";
        $stmt = $this->database->query($sql);

        return (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Find role by slug.
     *
     * @param  string  $slug  Role slug
     * @return array{id: int, role_name: string, role_slug: string, description?: string}|null
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE role_slug = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$slug]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * All roles with how many users hold each, grouped counts in one query.
     *
     * @return array<int, array<string, mixed>> Roles ordered by scope then level, each with users_count
     */
    public function findAllWithUserCounts(): array
    {
        $sql = "
            SELECT r.*, COUNT(ur.id) AS users_count
            FROM {$this->getTable()} AS r
            LEFT JOIN user_roles AS ur ON ur.role_id = r.id
            GROUP BY r.id
            ORDER BY r.scope = 'system' DESC, r.level DESC
        ";

        return $this->database->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * How many users currently hold a role.
     */
    public function userCount(int $roleId): int
    {
        $stmt = $this->database->query('SELECT COUNT(*) FROM user_roles WHERE role_id = ?', [$roleId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether a role is one of the shipped roles referenced by code.
     *
     * @param  array<string, mixed>  $role  Role row
     */
    public function isSystemRole(array $role): bool
    {
        return !empty($role['is_system']);
    }

    /**
     * Delete a custom role.
     *
     * System roles and roles that still have users are refused; callers
     * must reassign users first so nobody silently loses access.
     *
     * @return bool True when the role row was removed
     */
    public function deleteRole(int $roleId): bool
    {
        $role = $this->find($roleId);
        if (!$role || $this->isSystemRole($role) || $this->userCount($roleId) > 0) {
            return false;
        }

        try {
            $this->database->beginTransaction();
            $this->database->execute('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
            $this->database->execute("DELETE FROM {$this->getTable()} WHERE id = ?", [$roleId]);
            $this->database->commit();

            return true;
        } catch (\Throwable $e) {
            $this->database->rollback();
            error_log('Role deletion failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get all permissions assigned to a role.
     *
     * Returns permission details for a given role by joining role_permissions
     * and permissions tables.
     *
     * @param  int  $roleId  Role identifier
     * @return array<int, array<string, mixed>> List of permissions assigned to this role
     */
    public function getRolePermissions(int $roleId): array
    {
        $sql = '
            SELECT p.id, r.id AS role_id, p.permission_name  
            FROM role_permissions AS rp 
            LEFT JOIN permissions AS p ON p.id = rp.permission_id 
            LEFT JOIN roles AS r ON r.id = rp.role_id 
            WHERE r.id = ?
        ';

        $stmt = $this->database->query($sql, [$roleId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Replace a role's permissions with the given set.
     *
     * Runs inside a transaction so the role is never left with a partial
     * permission set if the insert fails halfway.
     *
     * @param  int  $roleId  Role identifier
     * @param  int[]  $permissionIds  Permission ids to grant (empty revokes all)
     * @return bool True when the sync committed
     */
    public function syncPermissions(int $roleId, array $permissionIds): bool
    {
        $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds))));

        try {
            $this->database->beginTransaction();

            $this->database->execute('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);

            if ($permissionIds !== []) {
                $placeholders = implode(',', array_fill(0, count($permissionIds), '(?, ?)'));
                $params = [];
                foreach ($permissionIds as $pid) {
                    $params[] = $roleId;
                    $params[] = $pid;
                }
                $this->database->execute(
                    "INSERT INTO role_permissions (role_id, permission_id) VALUES {$placeholders}",
                    $params
                );
            }

            $this->database->commit();

            return true;
        } catch (\Throwable $e) {
            $this->database->rollback();
            error_log('Role permission sync failed: '.$e->getMessage());

            return false;
        }
    }
}
