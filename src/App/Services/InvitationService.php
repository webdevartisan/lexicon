<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BlogInviteMail;
use App\Models\BlogInvitationModel;
use App\Models\BlogModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;

/**
 * Orchestrates the full blog invitation lifecycle.
 *
 * Handles all side-effects (email, notification, audit) so that
 * BlogInvitationModel stays persistence-only.
 */
class InvitationService
{
    private const EXPIRY_DAYS = 7;

    public function __construct(
        private readonly BlogInvitationModel $invitationModel,
        private readonly BlogModel $blogModel,
        private readonly UserModel $userModel,
        private readonly NotificationService $notifications,
        private readonly MailService $mailService,
        private readonly UserPreferencesModel $preferences,
    ) {}

    /**
     * Issue a blog invitation.
     *
     * Existing users also receive an in-app notification; an email is always sent.
     *
     * @param  int  $blogId  Target blog
     * @param  string  $email  Invitee email
     * @param  string  $role  Must be in BlogModel::ROLES
     * @param  int  $invitedBy  Owner issuing the invite
     * @param  string  $ip  Client IP for audit
     * @return bool Whether the invitation email was delivered. The invite row
     *              exists either way, but the accept link only travels by
     *              email, so a false here means the invitee cannot act on it.
     *
     * @throws \InvalidArgumentException If role is not in BlogModel::ROLES
     */
    public function invite(int $blogId, string $email, string $role, int $invitedBy, string $ip): bool
    {
        if (!in_array($role, BlogModel::ROLES, true)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+'.self::EXPIRY_DAYS.' days'));

        $this->invitationModel->create($blogId, $email, $role, $tokenHash, $invitedBy, $expiresAt);

        audit()->log($invitedBy, 'blog.invite_sent', 'blog_invitation', $blogId,
            ['email' => $email, 'role' => $role], $ip);

        // In-app notification for users who already have an account.
        $existingUser = $this->userModel->findByEmail($email);
        if ($existingUser) {
            $this->notifications->dispatch((int) $existingUser['id'], 'blog.invite', [
                'blog_id' => $blogId,
                'role' => $role,
                'invited_by' => $invitedBy,
                'token' => $rawToken,
            ]);
        }

        // Email always sent (raw token in link, never stored).
        $blog = $this->blogModel->getBlog($blogId);
        $blogName = $blog ? $blog->name() : 'a blog';

        return $this->mailService->send(new BlogInviteMail($email, $rawToken, $blogName, $role));
    }

    /**
     * Accept an invitation using the raw token from the email link.
     *
     * Adds the user to blog_users and consumes the invite.
     *
     * @param  string  $rawToken  Raw token from the link
     * @param  int  $acceptingUserId  Authenticated user accepting
     *
     * @throws \RuntimeException If the token is invalid, expired, or already used
     */
    public function accept(string $rawToken, int $acceptingUserId): void
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $rawToken));

        if (!$invite) {
            throw new \RuntimeException('Invitation is invalid, expired, or already used.');
        }

        $this->invitationModel->markAccepted((int) $invite['id']);
        $this->blogModel->addUserToBlog(
            (int) $invite['blog_id'],
            $acceptingUserId,
            $invite['role'],
            (int) $invite['invited_by']
        );

        audit()->log(
            $acceptingUserId,
            'blog.invite_accepted',
            'blog_user',
            (int) $invite['blog_id'],
            ["Accepted invitation as {$invite['role']}"],
            $_SERVER['REMOTE_ADDR'] ?? null
        );

        // First-blog convenience: if the new collaborator has no default blog
        // set, land them inside this one on their next dashboard visit. They
        // can still switch via the topbar. Owners keep their own default.
        if ($this->preferences->getDefaultBlogId($acceptingUserId) === null) {
            $this->preferences->setDefaultBlogId($acceptingUserId, (int) $invite['blog_id']);
        }

        audit()->log($acceptingUserId, 'blog.invite_accepted', 'blog_invitation', (int) $invite['blog_id'],
            ['role' => $invite['role']], $_SERVER['REMOTE_ADDR'] ?? null);
    }

    /**
     * Decline an invitation and notify the blog owner.
     *
     * @param  string  $rawToken  Raw token from the link
     * @param  int  $decliningUserId  User declining (0 if not logged in)
     *
     * @throws \RuntimeException If the token is invalid, expired, or already used
     */
    public function decline(string $rawToken, int $decliningUserId): void
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $rawToken));

        if (!$invite) {
            throw new \RuntimeException('Invitation is invalid, expired, or already used.');
        }

        $this->invitationModel->markDeclined((int) $invite['id']);

        $blog = $this->blogModel->getBlog((int) $invite['blog_id']);
        if ($blog) {
            $this->notifications->dispatch($blog->ownerId(), 'blog.invite_declined', [
                'blog_id' => (int) $invite['blog_id'],
                'blog_name' => $blog->name(),
                'declined_email' => $invite['email'],
            ]);
        }

        audit()->log($decliningUserId, 'blog.invite_declined', 'blog_invitation', (int) $invite['blog_id'],
            ['email' => $invite['email']], $_SERVER['REMOTE_ADDR'] ?? null);
    }

    /**
     * Cancel a pending invite (owner action).
     *
     * @param  int  $blogId  Target blog
     * @param  string  $email  Invitee email
     * @param  int  $cancelledBy  Owner cancelling
     */
    public function cancel(int $blogId, string $email, int $cancelledBy): void
    {
        $this->invitationModel->cancelPendingForEmail($blogId, $email);

        audit()->log($cancelledBy, 'blog.invite_cancelled', 'blog_invitation', $blogId,
            ['email' => $email], $_SERVER['REMOTE_ADDR'] ?? null);
    }
}
