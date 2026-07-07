<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

/**
 * Role management for the control panel.
 *
 * Roles come in two scopes: system roles gate control panel areas, blog
 * roles are assigned to collaborators inside a blog. The six shipped roles
 * are marked is_system and can have their description and permissions
 * edited, but their name, slug, scope and existence are locked because
 * application code references them by slug.
 */
class RoleController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageRoles';

    public function __construct(
        private RoleModel $model,
        protected Database $database
    ) {}

    public function index(): Response
    {
        return $this->view('role.index', [
            'roles' => $this->model->findAllWithUserCounts(),
        ]);
    }

    /**
     * Show a single role in admin
     */
    public function show(string $id): Response
    {
        $role = $this->getRole($id);

        $permissions = $this->model->getRolePermissions((int) $id);

        $permissionM = new PermissionModel($this->database);
        $allPermissions = $permissionM->findAll();

        return $this->view('role.show', [
            'role' => $role,
            'permissions' => $permissions,
            'allPermissions' => $allPermissions,
        ]);
    }

    public function new(): Response
    {
        return $this->view('role.new');
    }

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $input = $this->validateOrFail([
            'role_name' => 'required|min:2|max:50|unique:roles,role_name',
            'description' => 'max:255',
            'scope' => 'required|in:system,blog',
            'level' => 'required|integer',
        ])->validated();

        // min/max rules check string length, so clamp the numeric range here;
        // 100 is reserved for administrator so custom roles can never outrank it
        $level = min(99, max(0, (int) $input['level']));

        // Role slugs use snake_case to match the shipped roles (blog_owner)
        $slug = str_replace('-', '_', slugify($input['role_name']));

        if ($this->model->findBySlug($slug)) {
            $this->flash('error', "A role with the slug '{$slug}' already exists.");

            return $this->redirect('/admin/roles/new');
        }

        $data = [
            'role_name' => $input['role_name'],
            'role_slug' => $slug,
            'description' => $input['description'] ?? '',
            'scope' => $input['scope'],
            'is_system' => 0,
            'level' => $level,
        ];

        $roleId = $this->model->insert($data);

        if ($roleId) {
            audit()->log(
                (int) auth()->user()['id'],
                'role.created',
                'role',
                (int) $roleId,
                ['role_slug' => $slug, 'scope' => $input['scope']],
                $this->request->ip()
            );
            $this->flash('success', 'Role created. Grant it permissions below.');

            return $this->redirect('/admin/roles/'.(int) $roleId.'/show');
        }

        $this->flash('error', 'Could not create the role. Check the logs.');

        return $this->redirect('/admin/roles/new');
    }

    public function edit(string $id): Response
    {
        return $this->view('role.edit', [
            'role' => $this->getRole($id),
        ]);
    }

    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $role = $this->getRole($id);

        if ($this->model->isSystemRole($role)) {
            // Code references system roles by slug and compares levels, so
            // only the human-facing description may change
            $input = $this->validateOrFail([
                'description' => 'max:255',
            ])->validated();

            $data = ['description' => $input['description'] ?? ''];
        } else {
            $input = $this->validateOrFail([
                'role_name' => 'required|min:2|max:50|unique:roles,role_name,'.(int) $id,
                'description' => 'max:255',
                'scope' => 'required|in:system,blog',
                'level' => 'required|integer',
            ])->validated();

            $data = [
                'role_name' => $input['role_name'],
                'description' => $input['description'] ?? '',
                'scope' => $input['scope'],
                // min/max rules check string length, so clamp the range here
                'level' => min(99, max(0, (int) $input['level'])),
            ];
        }

        $changes = changedFields($data, $role);

        if ($changes === [] || $this->model->update((int) $id, $changes)) {
            if ($changes !== []) {
                audit()->log(
                    (int) auth()->user()['id'],
                    'role.updated',
                    'role',
                    (int) $id,
                    ['role_slug' => $role['role_slug'], 'changed' => array_keys($changes)],
                    $this->request->ip()
                );
            }
            $this->flash('success', 'Role updated.');

            return $this->redirect('/admin/roles');
        }

        $this->flash('error', 'Could not update the role. Check the logs.');

        return $this->redirect('/admin/roles/'.(int) $id.'/edit');
    }

    public function delete(string $id): Response
    {
        $role = $this->getRole($id);

        return $this->view('role.delete', [
            'role' => $role,
            'usersCount' => $this->model->userCount((int) $id),
        ]);
    }

    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $role = $this->getRole($id);

        if ($this->model->isSystemRole($role)) {
            $this->flash('error', 'System roles are essential and cannot be deleted.');

            return $this->redirect('/admin/roles');
        }

        if ($this->model->userCount((int) $id) > 0) {
            $this->flash('error', 'Reassign the users holding this role before deleting it.');

            return $this->redirect('/admin/roles');
        }

        if ($this->model->deleteRole((int) $id)) {
            audit()->log(
                (int) auth()->user()['id'],
                'role.deleted',
                'role',
                (int) $id,
                ['role_slug' => $role['role_slug']],
                $this->request->ip()
            );
            $this->flash('success', "Role '{$role['role_name']}' deleted.");
        } else {
            $this->flash('error', 'Could not delete the role. Check the logs.');
        }

        return $this->redirect('/admin/roles');
    }

    /**
     * Replace the role's permission grants with the submitted selection.
     */
    public function updatePermissions(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $role = $this->getRole($id);

        $permissionIds = array_map('intval', (array) ($this->request->post['permissions'] ?? []));

        if ($this->model->syncPermissions((int) $id, $permissionIds)) {
            audit()->log(
                (int) auth()->user()['id'],
                'role.permissions_updated',
                'role',
                (int) $id,
                ['role_slug' => $role['role_slug'] ?? null, 'permission_ids' => $permissionIds],
                $this->request->ip()
            );

            $this->flash('success', 'Permissions updated.');
        } else {
            $this->flash('error', 'Could not update permissions. Check the logs.');
        }

        return $this->redirect('/admin/roles/'.(int) $id.'/show');
    }

    /**
     * Utility: fetch role or 404
     */
    private function getRole(string $id): array
    {
        $role = $this->model->find($id);

        if (!$role) {
            throw new PageNotFoundException("Role with ID '$id' not found.");
        }

        return $role;
    }
}
