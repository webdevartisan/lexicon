<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;

/**
 * The human-readable facts about an invitation: which blog, who invited,
 * and what the role is called and allows. Shared by the signed-in confirmation
 * page and the new-user wizard so both say the same thing.
 */
final class InviteSummary
{
    public function __construct(
        private BlogModel $blogModel,
        private UserModel $userModel,
        private RoleModel $roles,
        private BlogRoleLabels $roleLabels,
    ) {}

    /**
     * @param  array{blog_id: int|string, role: string, invited_by: int|string|null, email: string}  $invite  A valid invitation row
     * @return array{blogName: string, inviterName: string|null, roleLabel: string, roleDescription: string, invitedEmail: string}|null
     *                                                                                                                                  Null when the invited blog no longer exists
     */
    public function describe(array $invite): ?array
    {
        $blog = $this->blogModel->getBlog((int) $invite['blog_id']);
        if ($blog === false) {
            return null;
        }

        $inviter = $invite['invited_by'] === null ? null : $this->userModel->findById((int) $invite['invited_by']);
        $role = $this->roles->findBySlug((string) $invite['role']);

        return [
            'blogName' => $blog->name(),
            'inviterName' => $inviter === null ? null : ($inviter['display_name_cached'] ?: $inviter['handle']),
            'roleLabel' => $this->roleLabels->label((string) $invite['role']),
            'roleDescription' => (string) ($role['description'] ?? ''),
            'invitedEmail' => (string) $invite['email'],
        ];
    }
}
