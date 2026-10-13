<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\RoleModel;
use App\Models\UserModel;
use App\Services\BlogRoleLabels;
use Framework\Core\Response;

/**
 * The accept/decline page for a signed-in invitee. It lives under /dashboard so
 * the control panel menu, blog switcher and notification bell are the ones the
 * navigation middleware builds for that area.
 */
final class InviteController extends AppController
{
    public function __construct(
        private BlogInvitationModel $invitationModel,
        private BlogModel $blogModel,
        private UserModel $userModel,
        private RoleModel $roles,
        private BlogRoleLabels $roleLabels,
    ) {}

    /**
     * Show who invited the user, to which blog and as what (GET /dashboard/invite/{token}).
     *
     * @param  string  $token  Raw token from the email or notification link
     */
    public function show(string $token): Response
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $token));
        $blog = $invite ? $this->blogModel->getBlog((int) $invite['blog_id']) : false;

        // The public page owns the expired state, so a dead token lands there.
        if (!$invite || $blog === false) {
            return $this->redirect(lurl('/invite/'.$token));
        }

        $inviter = $invite['invited_by'] === null ? null : $this->userModel->findById((int) $invite['invited_by']);
        $role = $this->roles->findBySlug((string) $invite['role']);
        $accountEmail = (string) auth()->user()['email'];

        return $this->view('dashboard.invite.accept', [
            'noBreadcrumb' => true,
            'token' => $token,
            'blogName' => $blog->name(),
            'inviterName' => $inviter === null ? null : ($inviter['display_name_cached'] ?: $inviter['handle']),
            'roleLabel' => $this->roleLabels->label((string) $invite['role']),
            'roleDescription' => (string) ($role['description'] ?? ''),
            'invitedEmail' => $invite['email'],
            'accountEmail' => $accountEmail,
            'wrongAccount' => strcasecmp($accountEmail, (string) $invite['email']) !== 0,
        ]);
    }
}
