<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\RoleModel;
use App\Models\UserModel;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

class UserController extends AppController
{
    public function __construct(
        private UserModel $model,
        private RoleModel $roleModel,
        protected Database $database,
    ) {}

    public function index(): Response
    {
        $this->requireRole(['administrator']); // only admins can manage users
        $users = $this->model->findAll();
        foreach ($users as $key => $user) {
            $users[$key]['roles'] = implode(',', $this->model->getUserRoles($user['id']));

        }

        return $this->view('user.index', [
            'users' => $users,
        ]);
    }

    public function new(): Response
    {
        $this->requireRole(['administrator']);

        // $roleModel = new RoleModel($this->database);
        $roles = $this->roleModel->findAll();

        return $this->view('Admin/Users/new.lex.php', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $this->requireRole(['administrator']);

        $data = [
            'username' => $this->request->post['username'],
            'email' => $this->request->post['email'],
            'password' => $this->request->post['password'],
            'first_name' => $this->request->post['first_name'] ?? null,
            'last_name' => $this->request->post['last_name'] ?? null,
            'is_active' => $this->request->post['is_active'] ?? 1,
        ];

        $roles = $this->request->post['roles'];

        if ($this->model->insert($data)) {

            $userId = $this->model->getInsertID();

            $test = $this->model->insertUserRoles((int) $userId, $roles);

            return $this->redirect('/admin/users');
        }

        return $this->view('Admin/Users/new.lex.php', [
            'errors' => $this->model->getErrors(),
            'user' => $data,
        ]);
    }

    public function edit(string $id): Response
    {
        $this->requireRole(['administrator']);
        $user = $this->getUser($id);

        $user['roles'] = $this->model->getUserRoles($user['id']);

        // $roleModel = new RoleModel($this->database);
        $roles = $this->roleModel->findAll();

        return $this->view('Admin/Users/edit.lex.php', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(string $id): Response
    {
        $this->requireRole(['administrator']);
        $user = $this->getUser($id);

        // Collect updated user data
        $data = [
            'username' => $this->request->post['username'] ?? $user['username'],
            'email' => $this->request->post['email'] ?? $user['email'],
            'first_name' => $this->request->post['first_name'] ?? $user['first_name'],
            'last_name' => $this->request->post['last_name'] ?? $user['last_name'],
        ];

        // Only update password if provided
        if (!empty($this->request->post['password'])) {
            $data['password'] = $this->request->post['password'];
        }

        // Update user record
        $userUpdated = $this->model->update($id, $data);

        // Update roles in one call (model handles diff + transaction)
        $newRoles = $this->request->post['roles'] ?? [];
        $rolesUpdated = $this->model->updateUserRoles((int) $id, $newRoles);

        if ($userUpdated && $rolesUpdated) {
            return $this->redirect('/admin/users');
        }

        return $this->view('Admin/Users/edit.lex.php', [
            'errors' => $this->model->getErrors(),
            'user' => $user,
            'roles' => $newRoles,
        ]);
    }

    public function delete(string $id): Response
    {
        $this->requireRole(['administrator']);
        $user = $this->getUser($id);

        return $this->view('Admin/Users/delete.lex.php', [
            'user' => $user,
        ]);
    }

    public function destroy(string $id): Response
    {
        $this->requireRole(['administrator']);
        $this->model->delete($id);

        return $this->redirect('/admin/users');
    }

    private function getUser(string $id): array
    {
        $user = $this->model->find($id);

        if (!$user) {
            throw new PageNotFoundException("User with ID '$id' not found.");
        }

        return $user;
    }
}
