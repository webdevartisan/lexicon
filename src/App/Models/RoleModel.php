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
     * Get all permissions assigned to a role.
     *
     * Returns permission details for a given role by joining role_permissions
     * and permissions tables.
     *
     * @param  int  $roleId  Role identifier
     * @return array List of permissions assigned to this role
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
}
