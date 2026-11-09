<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostListingModel;
use App\Models\UserModel;
use App\Resources\BlogResource;
use App\Services\BlogOwnershipService;
use App\Services\BlogRoleLabels;
use App\Services\InvitationService;
use App\Services\NotificationService;
use App\Services\PasswordConfirmRateLimiter;
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
        private readonly PostListingModel $postListing,
        private readonly BlogSettingsModel $blogSettingsModel,
        private readonly BlogOwnershipService $ownership,
        private readonly PasswordConfirmRateLimiter $passwordThrottle,
        private readonly BlogRoleLabels $roleLabels,
    ) {}

    /**
     * Show the team management page (owner only).
     *
     * Renders the roster together with pending and expired invitations. When the
     * review workflow is on it also reports how many posts in review have no
     * reviewer, so the page can point the owner at the review queue.
     *
     * @param  string  $blogId  Blog ID
     */
    public function team(string $blogId): Response
    {
        $blog = $this->getBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        $blogIdInt = (int) $blog->id();
        $user = auth()->user();

        // Only blogs that opted into the review pipeline can have posts waiting for a reviewer.
        $settings = $this->blogSettingsModel->findByBlogId($blogIdInt);
        $workflowEnabled = !empty($settings['workflow_enabled']);
        $unassignedInReview = $workflowEnabled ? $this->postListing->countInReviewUnassigned($blogIdInt) : 0;

        // With the workflow off the reviewer role has nothing to do, so don't offer it on the invite form.
        // Custom blog-scope roles created in the control panel are offered too.
        $availableRoles = $this->blogModel->availableCollaboratorRoles($workflowEnabled);
        $members = $blog->users();

        return $this->view('blog.team', [
            'blog' => $blog->toArray(),
            'members' => $members,
            'roleLabels' => $this->roleLabelsFor($availableRoles, $members),
            'pendingInvites' => $this->invitationModel->getPendingForBlog($blogIdInt),
            'expiredInvites' => $this->invitationModel->getExpiredForBlog($blogIdInt),
            'roles' => $availableRoles,
            'workflowEnabled' => $workflowEnabled,
            'unassignedInReview' => $unassignedInReview,
            'canTransfer' => Gate::allows('transferOwnership', $blog, $user),
        ]);
    }

    /**
     * Display label for every role the team page can show.
     *
     * @param  array<int, string>  $availableRoles  Roles offered for new collaborators
     * @param  array<int, array<string, mixed>>  $members  Current collaborators
     * @return array<string, string> Role slug => label
     */
    private function roleLabelsFor(array $availableRoles, array $members): array
    {
        $labels = [];
        foreach (['owner', ...$availableRoles, ...array_column($members, 'role')] as $role) {
            $labels[$role] ??= $this->roleLabels->label((string) $role);
        }

        return $labels;
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
            'role' => 'required|in:'.implode(',', $this->blogModel->availableCollaboratorRoles()),
        ]);
        $data = $validator->validated();

        $emailed = $this->invitationService->invite(
            $blog->id(),
            $data['email'],
            $data['role'],
            (int) auth()->user()['id'],
            $this->request->ip()
        );

        if ($emailed) {
            $this->flash('success', 'Invitation sent to '.$data['email'].'.');
        } else {
            // The invite exists but its accept link only travels by email, so
            // saying "sent" here would strand the invitee.
            $this->flash(
                'error',
                'Invitation created, but the email to '.$data['email'].' could not be delivered. '
                .'Cancel it and try again once mail is working.'
            );
        }

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
        $this->invitationService->cancel($blog->id(), $email, (int) auth()->user()['id'], $this->request->ip());

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
            'role' => 'required|in:'.implode(',', $this->blogModel->availableCollaboratorRoles()),
        ]);
        $role = $validator->validated()['role'];

        // addUserToBlog handles role change via ON DUPLICATE KEY UPDATE.
        $this->blogModel->addUserToBlog($blog->id(), (int) $userId, $role, (int) auth()->user()['id']);

        audit()->log(
            (int) auth()->user()['id'],
            'blog.role_changed',
            'blog_user',
            (int) $blog->id(),
            ["Set user {$userId} to {$role} on blog {$blogId}"],
            $this->request->ip()
        );

        $target = $this->userModel->findById((int) $userId);
        $actor = auth()->user();
        if ($target && !empty($target['email'])) {
            $this->notifications->dispatch((int) $userId, 'collaborator.role_changed', [
                'blog_id' => $blog->id(),
                'blog_name' => $blog->name(),
                'new_role' => $role,
                'actor_handle' => (string) $actor['handle'],
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

        audit()->log(
            (int) auth()->user()['id'],
            'blog.user_revoked',
            'blog_user',
            (int) $blog->id(),
            ["Revoked user {$userId} from blog {$blogId}"],
            $this->request->ip()
        );

        $target = $this->userModel->findById((int) $userId);
        $actor = auth()->user();
        if ($target && !empty($target['email'])) {
            $this->notifications->dispatch((int) $userId, 'collaborator.removed', [
                'blog_id' => $blog->id(),
                'blog_name' => $blog->name(),
                'actor_handle' => (string) $actor['handle'],
            ]);
        }

        $this->flash('success', 'Collaborator access revoked.');

        return $this->redirect(lurl("/dashboard/blog/{$blogId}/team"));
    }

    /**
     * Hand the blog to an active collaborator. Password confirmed, like deleting the blog.
     *
     * @param  string  $blogId  Blog ID
     */
    public function transferOwnership(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->getBlog($blogId);
        $user = auth()->user();
        Gate::authorize('transferOwnership', $blog, $user);

        $userId = (int) $user['id'];
        $teamUrl = lurl("/dashboard/blog/{$blog->id()}/team");

        // Throttled like every other password confirmation here: without a limit
        // this form is an unlimited oracle for guessing the password of whichever
        // account a stolen session belongs to.
        if ($this->passwordThrottle->tooManyAttempts($userId)) {
            $this->flash('error', 'Too many incorrect passwords. Try again later.');

            return $this->redirect($teamUrl);
        }

        if (!$this->userModel->verifyPassword($userId, (string) $this->request->postParam('password'))) {
            $this->passwordThrottle->hit($userId);
            $this->flash('error', 'Incorrect password. Ownership was not transferred.');

            return $this->redirect($teamUrl);
        }

        $this->passwordThrottle->clear($userId);

        try {
            $this->ownership->transfer(
                $blog->id(),
                $userId,
                (int) $this->request->postParam('new_owner_id'),
                $userId,
                $this->request->ip()
            );
        } catch (\InvalidArgumentException $e) {
            $this->flash('error', $e->getMessage());

            return $this->redirect($teamUrl);
        }

        $this->flash('success', 'Ownership transferred. You stay on the blog as an editor.');

        return $this->redirect(lurl('/dashboard/blog'));
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

        $this->flash('success', 'You have left the blog. You will need a new invitation to rejoin.');

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
