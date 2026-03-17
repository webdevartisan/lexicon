<?php

declare(strict_types=1);

namespace App;

use App\Models\UserModel;
use App\Models\UserProfileModel;
use Framework\Interfaces\AuthInterface;
use Framework\Session;

/**
 * Authentication and authorization service.
 *
 * Responsible for login/logout, current user lookup, and simple role/permission
 * checks on top of Session and UserModel.
 */
class Auth implements AuthInterface
{
    private ?array $cachedUser = null;

    private ?array $cachedRoles = null;

    private ?array $cachedPermissions = null;

    private ?array $cachedAvatar = null;

    public function __construct(
        private Session $session,
        private UserModel $users,
        private UserProfileModel $profiles
    ) {}

    /**
     * Attempt to log in with email and password.
     *
     * Returns true on success, false on failure.
     */
    public function login(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if (!$user || !isset($user['password']) || !\password_verify($password, $user['password'])) {
            return false;
        }

        // Optional: transparently upgrade old hashes if algorithm/cost changes.
        if (\password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->users->updateById((int) $user['id'], [
                'password' => \password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        // Regenerate session ID on login to prevent session fixation.
        $this->session->regenerate(true);

        $this->session->set('user_id', (int) $user['id']);

        $this->users->updateById((int) $user['id'], [
            'last_login' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Log out the current user and harden session state.
     */
    public function logout(): void
    {
        $this->session->remove('user_id');

        // Clear cached data
        $this->cachedUser = null;
        $this->cachedRoles = null;
        $this->cachedPermissions = null;
        $this->cachedAvatar = null;

        // Optional: regenerate on logout too, to break any leaked ID.
        $this->session->regenerate(true);
    }

    /**
     * Get the current authenticated user record, or null if guest.
     *
     * @return array<string,mixed>|null
     */
    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = $this->session->get('user_id');

        if ($id === null) {
            return null;
        }

        $userId = \is_int($id) ? $id : (int) $id;
        if ($userId <= 0) {
            return null;
        }

        $record = $this->users->find($userId);

        if (!$record) {
            return null;
        }

        // Load roles/permissions once and attach to the record
        $this->cachedRoles = $this->users->getUserRoles($userId);
        $this->cachedPermissions = $this->users->getUserPermissions($userId);
        $this->cachedAvatar = $this->profiles->getProfileAvatar($userId);

        $record = array_merge($record, $this->cachedAvatar);

        $record['roles'] = $this->cachedRoles;
        $record['permissions'] = $this->cachedPermissions;

        $this->cachedUser = $record;

        return $this->cachedUser;
    }

    /**
     * True if a user is currently authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check whether the current user has the given role slug.
     *
     * Example role slugs: "administrator", "author".
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        $roles = $user['roles'] ?? [];

        return \in_array($role, $roles, true);
    }

    /**
     * Check whether the current user has the given permission slug.
     *
     * Example permission slugs: "edit_post", "delete_comment".
     */
    public function hasPermission(string $permission): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        $permissions = $user['permissions'] ?? [];

        return \in_array($permission, $permissions, true);
    }

    public function hasBlogs(): int
    {
        $user = $this->user();

        if (!$user) {
            return 0;
        }

        return (int) $this->users->countBlogs($user['id']);
    }

    /**
     * Get all role slugs for the current user.
     *
     * @return string[]
     */
    public function getCurrentUserRoles(): array
    {
        $user = $this->user();

        if (!$user) {
            return [];
        }

        return $user['roles'] ?? [];
    }
}
