<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\CollaboratorRemovedMail;
use App\Mail\CollaboratorRoleChangedMail;
use App\Mail\InviteDeclinedMail;
use App\Mail\Mailable;
use App\Mail\NewCommentMail;
use App\Mail\PostApprovedMail;
use App\Mail\PostNeedsChangesMail;
use App\Mail\PostPublishedMail;
use App\Mail\PostSubmittedMail;
use App\Mail\ReviewerAssignedMail;
use App\Mail\ReviewerStaleMail;
use App\Mail\WorkflowDisabledMail;
use App\Models\NotificationModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;

/**
 * Single entry point for every RBAC / workflow notification.
 *
 * Always writes the in-app row. Conditionally sends a Mailable based on the
 * recipient's user_preferences gate. blog.invite is in-app only — the
 * BlogInviteMail is built and sent by InvitationService (it owns the raw token).
 */
class NotificationService
{
    /**
     * Map notification type → user_preferences gate column.
     *
     * Types absent from this map are written in-app but never email.
     */
    public const TYPE_TO_PREFERENCE = [
        'blog.invite_declined' => 'notify_invites',
        'post.submitted' => 'notify_post_status',
        'post.submitted_unassigned' => 'notify_post_status',
        'post.approved' => 'notify_post_status',
        'post.needs_changes' => 'notify_post_status',
        'post.published' => 'notify_post_status',
        'post.reviewer_assigned' => 'notify_post_status',
        'post.reviewer_stale' => 'notify_post_status',
        'post.workflow_disabled' => 'notify_post_status',
        'collaborator.role_changed' => 'notify_role_changes',
        'collaborator.removed' => 'notify_role_changes',
        'comment.created' => 'notify_comments',
    ];

    public function __construct(
        private NotificationModel $notifications,
        private UserModel $users,
        private UserPreferencesModel $preferences,
        private MailService $mail,
    ) {}

    /**
     * Dispatch a notification: write the in-app row, then optionally email.
     *
     * @param  int  $userId  Recipient user ID
     * @param  string  $type  One of the documented event types
     * @param  array<string, mixed>  $data  Type-specific payload (see plan for required keys)
     */
    public function dispatch(int $userId, string $type, array $data): void
    {
        $this->notifications->create($userId, $type, $data);

        if (!isset(self::TYPE_TO_PREFERENCE[$type])) {
            return;
        }

        $user = $this->users->findById($userId);
        if (!$user || empty($user['email'])) {
            return;
        }

        $prefKey = self::TYPE_TO_PREFERENCE[$type];
        if (!$this->preferences->notificationPreference($userId, $prefKey)) {
            return;
        }

        if (env('MAIL_ENABLED', false) === false) {
            return;
        }

        $mailable = $this->buildMailable($type, (string) $user['email'], $data);
        if ($mailable !== null) {
            $this->mail->send($mailable);
        }
    }

    /**
     * Build the Mailable for an emailable type.
     *
     * Returns null when the type has no Mailable mapping.
     *
     * @param  array<string, mixed>  $data  Type-specific payload
     */
    private function buildMailable(string $type, string $to, array $data): ?Mailable
    {
        return match ($type) {
            'post.submitted' => new PostSubmittedMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['author_username'] ?? ''),
                false
            ),
            'post.submitted_unassigned' => new PostSubmittedMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['author_username'] ?? ''),
                true
            ),
            'post.approved' => new PostApprovedMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['reviewer_username'] ?? '')
            ),
            'post.needs_changes' => new PostNeedsChangesMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['reviewer_username'] ?? ''),
                (string) ($data['feedback'] ?? '')
            ),
            'post.published' => new PostPublishedMail(
                $to,
                (string) ($data['post_title'] ?? ''),
                (string) ($data['blog_slug'] ?? ''),
                (string) ($data['post_slug'] ?? '')
            ),
            'post.reviewer_assigned' => new ReviewerAssignedMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['assigned_by_username'] ?? '')
            ),
            'post.reviewer_stale' => new ReviewerStaleMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['former_reviewer_username'] ?? '')
            ),
            'post.workflow_disabled' => new WorkflowDisabledMail(
                $to,
                (int) ($data['post_id'] ?? 0),
                (string) ($data['post_title'] ?? ''),
                (string) ($data['blog_name'] ?? '')
            ),
            'collaborator.role_changed' => new CollaboratorRoleChangedMail(
                $to,
                (string) ($data['blog_name'] ?? ''),
                (string) ($data['new_role'] ?? ''),
                (string) ($data['changed_by_username'] ?? '')
            ),
            'collaborator.removed' => new CollaboratorRemovedMail(
                $to,
                (string) ($data['blog_name'] ?? ''),
                (string) ($data['removed_by_username'] ?? '')
            ),
            'comment.created' => new NewCommentMail(
                $to,
                (string) ($data['post_title'] ?? ''),
                (string) ($data['blog_slug'] ?? ''),
                (string) ($data['post_slug'] ?? ''),
                (string) ($data['commenter_name'] ?? 'A reader'),
                (string) ($data['comment_excerpt'] ?? ''),
                (bool) ($data['awaiting_moderation'] ?? false),
                (int) ($data['comment_id'] ?? 0)
            ),
            'blog.invite_declined' => new InviteDeclinedMail(
                $to,
                (string) ($data['blog_name'] ?? ''),
                (string) ($data['declined_email'] ?? '')
            ),
            default => null,
        };
    }
}
