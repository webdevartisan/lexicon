<?php

declare(strict_types=1);

namespace App\Services\TwoFactor;

use App\Models\UserModel;
use App\Policies\SystemPolicy;

/**
 * Decides whose sign-in must have a second step.
 *
 * Anyone who can open the control panel can change what every blog shows, so
 * those accounts are held to it when the setting is on. Everyone else may turn
 * it on for themselves.
 */
final class TwoFactorPolicy
{
    public function __construct(
        private UserModel $users,
        private TwoFactorService $twoFactor,
        private SystemPolicy $system,
        private bool $requiredForAdmins,
    ) {}

    /**
     * @param  array<string, mixed>  $user
     */
    public function isRequiredFor(array $user): bool
    {
        if (!$this->requiredForAdmins) {
            return false;
        }

        // The same rule that opens /admin, so the requirement and the door never disagree
        return $this->system->accessDashboard([
            'id' => (int) $user['id'],
            'roles' => $this->users->getUserRoles((int) $user['id']),
            'permissions' => $this->users->getUserPermissions((int) $user['id']),
        ]);
    }

    /**
     * Whether this account is blocked from the control panel until it sets two-factor up.
     *
     * @param  array<string, mixed>  $user
     */
    public function mustSetUp(array $user): bool
    {
        return $this->isRequiredFor($user) && !$this->twoFactor->isEnabled($user);
    }
}
