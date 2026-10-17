<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BlogInviteMail;
use App\Mail\BlogInviteNewUserMail;
use App\Mail\Mailable;
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
        private readonly MailQueueService $mailQueue,
        private readonly UserPreferencesModel $preferences,
        private readonly RecipientLocale $locales,
    ) {}

    /**
     * Issue a blog invitation.
     *
     * Existing users also receive an in-app notification; an email is always sent.
     *
     * @param  int  $blogId  Target blog
     * @param  string  $email  Invitee email
     * @param  string  $role  Must be an assignable blog role
     * @param  int  $invitedBy  Owner issuing the invite
     * @param  string|null  $ip  Client IP for audit
     * @return bool Whether the invitation email was delivered. The invite row
     *              exists either way, but the accept link only travels by
     *              email, so a false here means the invitee cannot act on it.
     *
     * @throws \InvalidArgumentException If role is not an assignable blog role
     */
    public function invite(int $blogId, string $email, string $role, int $invitedBy, ?string $ip): bool
    {
        // Same assignable-role source as role-change and validation, so an
        // owner can invite into any shipped or custom blog role.
        if (!in_array($role, $this->blogModel->availableCollaboratorRoles(), true)) {
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

        // Email always queued. The invitations table keeps only the token hash;
        // the raw token travels in the link and in the invitee's own
        // notification. Queueing means an invite survives the transport being
        // down instead of vanishing.
        $blog = $this->blogModel->getBlog($blogId);
        $blogName = $blog ? $blog->name() : 'a blog';

        // An invitee with an account reads it in their own language; anyone
        // else in the language of the blog they are invited to.
        $mailClass = $existingUser ? BlogInviteMail::class : BlogInviteNewUserMail::class;
        $mail = Mailable::inLocale(
            $this->locales->forAddress($email, $this->locales->forBlog($blogId)),
            fn (): BlogInviteMail => new $mailClass($email, $rawToken, $blogName, $role)
        );

        return $this->mailQueue->enqueue($mail, 'blog', $blogId) > 0;
    }

    /**
     * Accept an invitation using the raw token from the email link.
     *
     * Adds the user to blog_users and consumes the invite. Only the account
     * the invitation was sent to can accept it.
     *
     * @param  string  $rawToken  Raw token from the link
     * @param  int  $acceptingUserId  Authenticated user accepting
     * @param  string|null  $ip  Client IP for audit
     *
     * @throws \RuntimeException If the token is invalid, expired, already used, or sent to another address
     */
    public function accept(string $rawToken, int $acceptingUserId, ?string $ip): void
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $rawToken));

        if (!$invite) {
            throw new \RuntimeException('Invitation is invalid, expired, or already used.');
        }

        $user = $this->userModel->find($acceptingUserId);
        if (!$user || strcasecmp((string) $user['email'], (string) $invite['email']) !== 0) {
            throw new \RuntimeException('Invitation was sent to a different account.');
        }

        $this->blogModel->transaction(function () use ($invite, $acceptingUserId): void {
            // The conditional update is the claim: a second click finds the
            // row already answered and adds nobody.
            if (!$this->invitationModel->markAccepted((int) $invite['id'])) {
                throw new \RuntimeException('Invitation is invalid, expired, or already used.');
            }

            $this->blogModel->addUserToBlog(
                (int) $invite['blog_id'],
                $acceptingUserId,
                $invite['role'],
                $invite['invited_by'] === null ? null : (int) $invite['invited_by']
            );
        });

        // First-blog convenience: if the new collaborator has no default blog
        // set, land them inside this one on their next dashboard visit. They
        // can still switch via the topbar. Owners keep their own default.
        if ($this->preferences->getDefaultBlogId($acceptingUserId) === null) {
            $this->preferences->setDefaultBlogId($acceptingUserId, (int) $invite['blog_id']);
        }

        audit()->log($acceptingUserId, 'blog.invite_accepted', 'blog_invitation', (int) $invite['blog_id'],
            ['role' => $invite['role']], $ip);
    }

    /**
     * Decline an invitation and notify the blog owner.
     *
     * @param  string  $rawToken  Raw token from the link
     * @param  int  $decliningUserId  User declining (0 if not logged in)
     * @param  string|null  $ip  Client IP for audit
     *
     * @throws \RuntimeException If the token is invalid, expired, or already used
     */
    public function decline(string $rawToken, int $decliningUserId, ?string $ip): void
    {
        $invite = $this->invitationModel->findValidByToken(hash('sha256', $rawToken));

        if (!$invite) {
            throw new \RuntimeException('Invitation is invalid, expired, or already used.');
        }

        if (!$this->invitationModel->markDeclined((int) $invite['id'])) {
            throw new \RuntimeException('Invitation is invalid, expired, or already used.');
        }

        $blog = $this->blogModel->getBlog((int) $invite['blog_id']);
        if ($blog) {
            $this->notifications->dispatch($blog->ownerId(), 'blog.invite_declined', [
                'blog_id' => (int) $invite['blog_id'],
                'blog_name' => $blog->name(),
                'declined_email' => $invite['email'],
            ]);
        }

        audit()->log($decliningUserId, 'blog.invite_declined', 'blog_invitation', (int) $invite['blog_id'],
            ['email' => $invite['email']], $ip);
    }

    /**
     * Cancel a pending invite (owner action).
     *
     * @param  int  $blogId  Target blog
     * @param  string  $email  Invitee email
     * @param  int  $cancelledBy  Owner cancelling
     * @param  string|null  $ip  Client IP for audit
     */
    public function cancel(int $blogId, string $email, int $cancelledBy, ?string $ip): void
    {
        $this->invitationModel->cancelPendingForEmail($blogId, $email);

        audit()->log($cancelledBy, 'blog.invite_cancelled', 'blog_invitation', $blogId,
            ['email' => $email], $ip);
    }
}
