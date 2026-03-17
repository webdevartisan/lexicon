<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

class RoleController extends AppController
{
    public function __construct(
        private RoleModel $model,
        protected Database $database
    ) {}

    public function index(): Response
    {
        $this->requireRole('administrator');

        $roles = $this->model->findAll();

        return $this->view('Admin/Roles/index.lex.php', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show a single role in admin
     */
    public function show(string $id): Response
    {
        $this->requirePermission('manage_all_posts');

        $role = $this->getRole($id);

        $permissions = $this->model->getRolePermissions($id);

        $permissionM = new PermissionModel($this->database);
        $allPermissions = $permissionM->findAll();

        // $this->authorizeOwnership($post);

        return $this->view('Admin/Roles/show.lex.php', [
            'role' => $role,
            'permissions' => $permissions,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Utility: fetch role or 404
     */
    private function getRole(string $id): array
    {
        $role = $this->model->find($id);

        if (!$role) {
            throw new PageNotFoundException("Post with ID '$id' not found.");
        }

        return $role;
    }
}
