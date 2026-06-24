<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
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
    ) {}

    /**
     * Show the team management page (owner only).
     *
     * @param  string  $blogId  Blog ID
     */
    public function team(string $blogId): Response
    {
        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        return $this->view('blog.team', [
            'blog' => $blog->toArray(),
            'members' => $blog->users(),
            'pendingInvites' => $this->invitationModel->getPendingForBlog($blog->id()),
            'roles' => BlogModel::ROLES,
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

        // addUserToBlog handles role change via ON DUPLICATE KEY UPDATE.
        $this->blogModel->addUserToBlog($blog->id(), (int) $userId, $role, (int) auth()->user()['id']);

        $target = $this->userModel->findById((int) $userId);
        $actor  = auth()->user();
        if ($target && !empty($target['email'])) {
            $this->notifications->dispatch((int) $userId, 'collaborator.role_changed', [
                'blog_name'           => $blog->name(),
                'new_role'            => $role,
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

        $this->blogModel->revokeUserFromBlog($blog->id(), (int) $userId);

        $target = $this->userModel->findById((int) $userId);
        $actor  = auth()->user();
        if ($target && !empty($target['email'])) {
            $this->notifications->dispatch((int) $userId, 'collaborator.removed', [
                'blog_name'           => $blog->name(),
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

        $this->blogModel->revokeUserFromBlog($blog->id(), (int) $user['id']);

        audit()->log((int) $user['id'], 'blog.self_removed', 'blog_user', $blog->id(),
            [], $this->request->ip());

        $this->flash('success', 'You have left the blog.');

        return $this->redirect(lurl('/dashboard/blog'));
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
