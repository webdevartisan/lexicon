<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\AccountErasureService;
use App\Services\DisplayNameService;
use App\Services\PublicCacheInvalidator;
use App\ValueObjects\TableSort;
use Framework\Core\Response;
use Framework\Database;
use Framework\Exceptions\PageNotFoundException;

class UserController extends AppController
{
    // Enforced for every action by AppController::beforeAction()
    protected ?string $areaAbility = 'manageUsers';

    public function __construct(
        private UserModel $model,
        private RoleModel $roleModel,
        private BlogModel $blogModel,
        private DisplayNameService $displayNames,
        private PublicCacheInvalidator $cacheInvalidator,
        private AccountErasureService $erasure,
        protected Database $database,
    ) {}

    public function index(): Response
    {
        $q = trim((string) ($this->request->get['q'] ?? ''));
        $active = trim((string) ($this->request->get['active'] ?? ''));
        $role = trim((string) ($this->request->get['role'] ?? ''));
        $page = max(1, (int) ($this->request->get['page'] ?? 1));

        $sort = TableSort::fromRequest($this->request, [
            'id' => 'u.id',
            'handle' => 'u.handle',
            'email' => 'u.email',
            'posts' => 'u.posts_count',
            'active' => 'u.is_active',
            'last_login' => 'u.last_login',
            'created' => 'u.created_at',
        ], defaultKey: 'created', defaultDirection: 'desc', tiebreaker: 'u.id DESC');

        $result = $this->model->findAllForAdmin($page, 20, $q, $active, $role, $sort->orderBy(), $this->erasure->deletedUserId());

        return $this->view('user.index', [
            'users' => $result['data'],
            'pagination' => $result['pagination'],
            'q' => $q,
            'active' => $active,
            'role' => $role,
            // Only system roles live on an account, so only they make sense as a
            // filter. Blog roles are per-blog and never appear in user_roles.
            'roleOptions' => $this->roleModel->findByScope('system'),
            'sort' => $sort,
        ]);
    }

    public function new(): Response
    {
        // Accounts carry system roles only; blog roles are assigned per blog
        // on the blog's team page, never globally here.
        $roles = $this->roleModel->findByScope('system');

        return $this->view('user.new', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validateOrFail([
            'handle' => 'required|user_handle|min:2|max:50|unique:users,handle',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|password:'.password_policy_preset(),
            'first_name' => 'max:50',
            'last_name' => 'max:50',
        ]);
        $input = $validator->validated();

        $data = [
            'handle' => $input['handle'],
            'email' => $input['email'],
            // The model layer stores columns verbatim, so hash here like
            // RegisterController does or the account cannot log in
            'password' => password_hash($input['password'], PASSWORD_DEFAULT),
            'first_name' => $input['first_name'] ?? null,
            'last_name' => $input['last_name'] ?? null,
            'is_active' => 1,
        ];

        $roles = array_map('intval', (array) ($this->request->post['roles'] ?? []));

        if ($this->model->insert($data)) {
            $userId = $this->model->getInsertID();

            $this->model->insertUserRoles((int) $userId, $roles);

            audit()->log(
                (int) auth()->user()['id'],
                'user.created',
                'user',
                (int) $userId,
                ['handle' => $data['handle'], 'email' => $data['email'], 'roles' => $roles],
                $this->request->ip()
            );

            $this->flash('success', 'User created.');

            return $this->redirect('/admin/users');
        }

        $this->flash('error', 'Could not create the user. Check the logs.');

        return $this->redirect('/admin/users/new');
    }

    public function edit(string $id): Response
    {
        $user = $this->getUser($id);

        $user['roles'] = $this->model->getUserRoles($user['id']);

        $roles = $this->roleModel->findByScope('system');

        return $this->view('user.edit', [
            'user' => $user,
            'roles' => $roles,
            // The full access picture: which blogs this account owns or
            // collaborates on, and the role held on each.
            'blogs' => $this->blogModel->getAccessibleBlogs((int) $user['id']),
        ]);
    }

    public function update(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = $this->getUser($id);

        $validator = $this->validateOrFail([
            'handle' => 'required|user_handle|min:2|max:50|unique:users,handle,'.(int) $id,
            'email' => 'required|email|unique:users,email,'.(int) $id,
            'password' => 'password:'.password_policy_preset(),
            'first_name' => 'max:50',
            'last_name' => 'max:50',
        ]);
        $input = $validator->validated();

        $data = [
            'handle' => $input['handle'],
            'email' => $input['email'],
            'first_name' => $input['first_name'] ?? $user['first_name'],
            'last_name' => $input['last_name'] ?? $user['last_name'],
        ];

        // Only update password if provided, hashed like every other auth path
        if (!empty($input['password'])) {
            $data['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }

        $isActive = empty($this->request->post['is_active']) ? 0 : 1;

        if ($isActive !== (int) $user['is_active']) {
            if ($isActive === 0 && (int) $id === (int) auth()->user()['id']) {
                $this->flash('error', 'You cannot suspend your own account.');

                return $this->redirect('/admin/users/'.(int) $id.'/edit');
            }

            $data['is_active'] = $isActive;
        }

        $newRoles = array_map('intval', (array) ($this->request->post['roles'] ?? []));

        // Never let the last administrator lose the role, or the whole
        // control panel becomes unreachable
        $adminRole = $this->roleModel->findBySlug('administrator');
        $targetIsAdmin = in_array('administrator', $this->model->getUserRoles((int) $id), true);
        $keepsAdmin = $adminRole && in_array((int) $adminRole['id'], $newRoles, true);

        if ($targetIsAdmin && !$keepsAdmin && $this->model->countAdministrators() <= 1) {
            $this->flash('error', 'This is the last administrator account. Give another user the Administrator role before removing it here.');

            return $this->redirect('/admin/users/'.(int) $id.'/edit');
        }

        // Only write columns that actually changed; resubmitting the form
        // unchanged is a no-op, not an error (update() reports 0 rows as false)
        $changes = changedFields($data, $user);
        $userUpdated = $changes === [] || $this->model->update($id, $changes);

        // Update roles in one call (model handles diff + transaction)
        $rolesUpdated = $this->model->updateUserRoles((int) $id, $newRoles);

        if ($userUpdated && $rolesUpdated) {
            $this->refreshPublicIdentity($user, $changes);

            audit()->log(
                (int) auth()->user()['id'],
                'user.updated',
                'user',
                (int) $id,
                ['fields' => array_keys($changes), 'roles' => $newRoles],
                $this->request->ip()
            );

            $this->flash('success', 'User updated.');

            return $this->redirect('/admin/users');
        }

        $this->flash('error', 'Could not update the user. Check the logs.');

        return $this->redirect('/admin/users/'.(int) $id.'/edit');
    }

    public function delete(string $id): Response
    {
        $user = $this->getUser($id);

        return $this->view('user.delete', [
            'user' => $user,
            'blockers' => $this->erasure->blockers((int) $user['id']),
            'canDelete' => $this->erasure->canErase((int) $user['id']),
        ]);
    }

    public function destroy(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        // Deleting yourself from the admin panel would orphan the session mid-request
        if ((int) $id === (int) auth()->user()['id']) {
            $this->flash('error', 'You cannot delete your own account from here.');

            return $this->redirect('/admin/users');
        }

        $user = $this->getUser($id);

        if (!$this->erasure->canErase((int) $user['id'])) {
            return $this->redirect('/admin/users/'.(int) $user['id'].'/delete');
        }

        $this->erasure->erase((int) $user['id'], (int) auth()->user()['id'], $this->request->ip());

        audit()->log(
            (int) auth()->user()['id'],
            'user.erased',
            'user',
            (int) $id,
            [],
            $this->request->ip()
        );

        $this->flash('success', 'User deleted.');

        return $this->redirect('/admin/users');
    }

    /**
     * Recompute the cached display name and clear public pages after a name or handle edit,
     * the same as the profile form does, or readers keep seeing the old identity until the TTL.
     *
     * @param  array<string, mixed>  $user  The record as it was before the edit
     * @param  array<string, mixed>  $changes  Columns that were written
     */
    private function refreshPublicIdentity(array $user, array $changes): void
    {
        if (array_intersect_key($changes, array_flip(['handle', 'first_name', 'last_name'])) === []) {
            return;
        }

        $newDisplayName = $this->displayNames->refreshCached((int) $user['id']);

        if (isset($changes['handle']) || $newDisplayName !== $user['display_name_cached']) {
            $this->cacheInvalidator->purgeAuthorSurfaces($user['handle']);
        }
    }

    /**
     * @return array<string, mixed> User record
     */
    private function getUser(string $id): array
    {
        $user = $this->model->find($id);

        if (!$user || $this->erasure->isDeletedUserAccount((int) $user['id'])) {
            throw new PageNotFoundException("User with ID '$id' not found.");
        }

        return $user;
    }
}
