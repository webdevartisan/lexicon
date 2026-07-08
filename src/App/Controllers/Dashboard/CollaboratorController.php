<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\UserModel;
use App\Resources\BlogResource;
use App\Services\InvitationService;
use App\Services\NotificationService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Dashboard blog team management.
 *
 * Owner-only actions: invite, cancel invite, change role, revoke. Any active
 * collaborator may remove themselves. Authorization is enforced via BlogPolicy.
 */
final class CollaboratorController extends AppController
{
    public function __construct(
        private BlogModel $blogModel,
        private BlogInvitationModel $invitationModel,
        private InvitationService $invitationService,
        private readonly UserModel $userModel,
        private readonly NotificationService $notifications,
        private readonly PostModel $postModel,
        private readonly PostReviewerModel $postReviewerModel,
        private readonly BlogSettingsModel $blogSettingsModel,
    ) {}

    /**
     * Show the team management page (owner only).
     *
     * Renders three concerns: the roster, the invite form (plus pending/expired
     * invites), and a per-blog workflow health snapshot so the owner sees in
     * one place what's queued, returned, or approved-not-published.
     *
     * @param  string  $blogId  Blog ID
     */
    public function team(string $blogId): Response
    {
        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        $blogIdInt = (int) $blog->id();
        $user = auth()->user();
        $isAdmin = auth()->hasRole('administrator');

        // Workflow health only makes sense for blogs that opted into the review pipeline.
        // Skip the count queries entirely otherwise. The stats would all be zero and just clutter the page.
        $settings = $this->blogSettingsModel->findByBlogId($blogIdInt);
        $workflowEnabled = !empty($settings['workflow_enabled']);

        $workflowHealth = null;
        if ($workflowEnabled) {
            $inReviewTotal = $this->postModel->countByBlogAndWorkflow($blogIdInt, 'in_review');
            $inReviewUnassigned = $this->postModel->countInReviewUnassigned($blogIdInt);
            $needsChangesTotal = $this->postModel->countByBlogAndWorkflow($blogIdInt, 'needs_changes');
            $approvedTotal = $this->postModel->countByBlogAndWorkflow($blogIdInt, 'approved');

            $inReviewRecent = $this->postReviewerModel->findInReviewForSupervisor(
                userId: (int) $user['id'],
                isAdmin: $isAdmin,
                blogId: $blogIdInt,
            );

            $workflowHealth = [
                'in_review_total' => $inReviewTotal,
                'in_review_unassigned' => $inReviewUnassigned,
                'in_review_assigned' => max(0, $inReviewTotal - $inReviewUnassigned),
                'needs_changes' => $needsChangesTotal,
                'approved' => $approvedTotal,
                'recent' => $inReviewRecent,
            ];
        }

        // With the workflow off the reviewer role has nothing to do, so don't offer it on the invite form.
        $availableRoles = $workflowEnabled
            ? BlogModel::ROLES
            : array_values(array_diff(BlogModel::ROLES, ['reviewer']));

        return $this->view('blog.team', [
            'blog' => $blog->toArray(),
            'members' => $blog->users(),
            'pendingInvites' => $this->invitationModel->getPendingForBlog($blogIdInt),
            'expiredInvites' => $this->invitationModel->getExpiredForBlog($blogIdInt),
            'roles' => $availableRoles,
            'workflowEnabled' => $workflowEnabled,
            'workflowHealth' => $workflowHealth,
        ]);
    }

    /**
     * Send an invitation (owner only).
     *
     * @param  string  $blogId  Blog ID
     */
    public function invite(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        Gate::authorize('invite', $blog, auth()->user());

        $validator = $this->validateOrFail([
            'email' => 'required|email',
            'role' => 'required|in:'.implode(',', BlogModel::ROLES),
        ]);
        $data = $validator->validated();

        $this->invitationService->invite(
            $blog->id(),
            $data['email'],
            $data['role'],
            (int) auth()->user()['id'],
            (string) ($this->request->ip() ?? '')
        );

        $this->flash('success', 'Invitation sent to '.$data['email'].'.');

        return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
    }

    /**
     * Cancel a pending invitation (owner only).
     *
     * @param  string  $blogId  Blog ID
     */
    public function cancelInvite(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        $email = (string) $this->request->postParam('email', '');
        $this->invitationService->cancel($blog->id(), $email, (int) auth()->user()['id']);

        $this->flash('success', 'Invitation cancelled.');

        return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
    }

    /**
     * Change a collaborator's role (owner only).
     *
     * @param  string  $blogId  Blog ID
     * @param  string  $userId  Collaborator user ID
     */
    public function changeRole(string $blogId, string $userId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        $validator = $this->validateOrFail([
            'role' => 'required|in:'.implode(',', BlogModel::ROLES),
        ]);
        $role = $validator->validated()['role'];

        // Guard against demoting the only editor — leaves the blog without an
        // operational lead and silently strands in-flight workflow items.
        if ($role !== 'editor' && $this->isLastWithRole((int) $blog->id(), (int) $userId, 'editor')) {
            $this->flash('error', 'This is the only editor on the blog. Promote someone else to editor first.');

            return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
        }

        // addUserToBlog handles role change via ON DUPLICATE KEY UPDATE.
        $this->blogModel->addUserToBlog($blog->id(), (int) $userId, $role, (int) auth()->user()['id']);

        $target = $this->userModel->findById((int) $userId);
        $actor = auth()->user();
        if ($target && !empty($target['email'])) {
            $this->notifications->dispatch((int) $userId, 'collaborator.role_changed', [
                'blog_name' => $blog->name(),
                'new_role' => $role,
                'changed_by_username' => (string) ($actor['username'] ?? ''),
            ]);
        }

        $this->flash('success', 'Role updated.');

        return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
    }

    /**
     * Revoke a collaborator's access (owner only, soft revoke).
     *
     * @param  string  $blogId  Blog ID
     * @param  string  $userId  Collaborator user ID
     */
    public function revoke(string $blogId, string $userId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        // Same guard as changeRole — never strand the blog without an editor.
        if ($this->isLastWithRole((int) $blog->id(), (int) $userId, 'editor')) {
            $this->flash('error', 'This is the only editor on the blog. Promote someone else first, then revoke.');

            return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
        }

        $this->blogModel->revokeUserFromBlog($blog->id(), (int) $userId);

        $target = $this->userModel->findById((int) $userId);
        $actor = auth()->user();
        if ($target && !empty($target['email'])) {
            $this->notifications->dispatch((int) $userId, 'collaborator.removed', [
                'blog_name' => $blog->name(),
                'removed_by_username' => (string) ($actor['username'] ?? ''),
            ]);
        }

        $this->flash('success', 'Collaborator access revoked.');

        return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
    }

    /**
     * Leave a blog (any non-owner collaborator).
     *
     * @param  string  $blogId  Blog ID
     */
    public function leave(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        $user = auth()->user();

        // Owners cannot leave; they must transfer ownership or delete the blog.
        if ($blog->ownerId() === (int) $user['id']) {
            $this->flash('error', 'Owners cannot leave their own blog. Transfer ownership or delete the blog instead.');

            return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
        }

        // Refuse to leave if you're the only editor — otherwise the owner is
        // stranded without anyone to move workflow items through.
        if ($this->isLastWithRole((int) $blog->id(), (int) $user['id'], 'editor')) {
            $this->flash('error', 'You are the only editor on this blog. Ask the owner to promote someone before you leave.');

            return $this->redirect(lurl('/dashboard/shared'));
        }

        $this->blogModel->revokeUserFromBlog($blog->id(), (int) $user['id']);

        audit()->log((int) $user['id'], 'blog.self_removed', 'blog_user', $blog->id(),
            [], $this->request->ip());

        $this->flash('success', 'You have left the blog. You will need a new invitation to rejoin.');

        return $this->redirect(lurl('/dashboard/shared'));
    }

    /**
     * Would removing/demoting this user leave the blog with zero people in $role?
     *
     * Centralized so leave, revoke, and changeRole share the same check.
     */
    private function isLastWithRole(int $blogId, int $userIdLosing, string $role): bool
    {
        $members = $this->blogModel->getActiveUsersWithRoles($blogId, [$role]);
        if (count($members) > 1) {
            return false;
        }
        foreach ($members as $m) {
            if ($m['user_id'] === $userIdLosing) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve blog or throw 404.
     *
     * @throws PageNotFoundException
     */
    private function getBlog(string|int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$id}' not found.");
        }

        return $blog;
    }
}
