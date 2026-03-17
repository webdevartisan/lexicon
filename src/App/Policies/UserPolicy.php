<?php

declare(strict_types=1);

namespace App\Policies;

use App\Resources\UserResource;
use Framework\Interfaces\PolicyInterface;

/**
 * Authorization policy for user account management.
 *
 * define who can perform actions on user accounts.
 * Administrators have full control, users can manage their own accounts.
 */
class UserPolicy implements PolicyInterface
{
    /**
     * View user profile.
     *
     * allow users to view their own profile and admins to view all profiles.
     */
    public function view(array $user, object $targetUser): bool
    {
        assert($targetUser instanceof UserResource);

        // Administrators can view any profile
        if ($this->isAdministrator($user)) {
            return true;
        }

        // Users can view their own profile
        return (int) $user['id'] === $targetUser->id();
    }

    /**
     * Update user profile.
     *
     * allow users to update their own profile and admins to update any profile.
     */
    public function update(array $user, object $targetUser): bool
    {
        assert($targetUser instanceof UserResource);

        // Administrators can update any profile
        if ($this->isAdministrator($user)) {
            return true;
        }

        // Users can update their own profile
        return (int) $user['id'] === $targetUser->id();
    }

    /**
     * Delete user account.
     *
     * have strict rules for deletion:
     * - Users can delete their own account
     * - Administrators can delete other accounts
     * - Cannot delete the last administrator
     */
    public function delete(array $user, object $targetUser): bool
    {
        assert($targetUser instanceof UserResource);

        $isAdmin = $this->isAdministrator($user);
        $targetIsAdmin = $targetUser->hasRole('administrator');
        $isSelf = (int) $user['id'] === $targetUser->id();

        // Users can delete their own account (unless it's the last admin)
        if ($isSelf && !$this->isLastAdministrator($targetUser)) {
            return true;
        }

        // Administrators can delete other accounts
        if ($isAdmin && !$isSelf) {
            // But not the last administrator
            return !$this->isLastAdministrator($targetUser);
        }

        return false;
    }

    /**
     * Restore a soft-deleted user.
     *
     * only allow administrators to restore deleted accounts.
     */
    public function restore(array $user, object $targetUser): bool
    {
        assert($targetUser instanceof UserResource);

        // Only administrators can restore deleted accounts
        return $this->isAdministrator($user);
    }

    /**
     * Permanently delete user data (hard delete).
     *
     * restrict this to administrators only for GDPR compliance.
     */
    public function forceDelete(array $user, object $targetUser): bool
    {
        assert($targetUser instanceof UserResource);

        // Only administrators can permanently delete accounts
        return $this->isAdministrator($user);
    }

    /**
     * Check if user is an administrator.
     */
    private function isAdministrator(array $user): bool
    {
        $roles = $user['roles'] ?? [];

        return in_array('administrator', $roles, true);
    }

    /**
     * Check if this is the last administrator account.
     *
     * prevent deletion of the last admin to avoid lockout.
     */
    private function isLastAdministrator(UserResource $user): bool
    {
        if (!$user->hasRole('administrator')) {
            return false;
        }

        // TODO: should inject UserModel here to check admin count
        // For now, prevent deletion of any admin as a safety measure
        // This method should be enhanced to actually count admins
        return true;
    }
}
