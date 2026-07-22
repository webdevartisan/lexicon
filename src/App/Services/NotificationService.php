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
        // Things that happen to your own post.
        'post.approved' => 'notify_post_status',
        'post.needs_changes' => 'notify_post_status',
        'post.published' => 'notify_post_status',
        'post.workflow_disabled' => 'notify_post_status',
        // Review work handed to you as a reviewer.
        'post.submitted' => 'notify_review_requests',
        'post.submitted_unassigned' => 'notify_review_requests',
        'post.reviewer_assigned' => 'notify_review_requests',
        'post.reviewer_stale' => 'notify_review_requests',
        'collaborator.role_changed' => 'notify_role_changes',
        'collaborator.removed' => 'notify_role_changes',
        // Comment types are keyed off CommentAudienceResolver so the four
        // gates stay in lockstep with the four audiences it produces.
        CommentAudienceResolver::TYPE_REPLY => 'notify_comment_replies',
        CommentAudienceResolver::TYPE_AUTHORED => 'notify_comments_authored',
        CommentAudienceResolver::TYPE_MODERATION => 'notify_comments_moderation',
        CommentAudienceResolver::TYPE_BLOG => 'notify_comments_blog',
    ];

    /**
     * Comment notification type → the reason string NewCommentMail renders from.
     */
    private const COMMENT_MAIL_REASON = [
        CommentAudienceResolver::TYPE_REPLY => NewCommentMail::REASON_REPLY,
        CommentAudienceResolver::TYPE_AUTHORED => NewCommentMail::REASON_AUTHORED,
        CommentAudienceResolver::TYPE_MODERATION => NewCommentMail::REASON_MODERATION,
        CommentAudienceResolver::TYPE_BLOG => NewCommentMail::REASON_BLOG,
    ];

    public function __construct(
        private NotificationModel $notifications,
        private UserModel $users,
        private UserPreferencesModel $preferences,
        private MailQueueService $mailQueue,
    ) {}

    /**
     * Dispatch a single-type notification: write the in-app row, then optionally email.
     *
     * @param  int  $userId  Recipient user ID
     * @param  string  $type  One of the documented event types
     * @param  array<string, mixed>  $data  Type-specific payload (see plan for required keys)
     */
    public function dispatch(int $userId, string $type, array $data): void
    {
        $this->dispatchFirstEnabled($userId, [$type], $data);
    }

    /**
     * Dispatch when a recipient qualifies for several types at once.
     *
     * Records one in-app row for the most personal type — the in-app feed is
     * never gated by the email toggles — then emails at most once, under the
     * first type whose gate is on. Walking past a muted type to a later one is
     * deliberate: turning off a specific email must not silence a broader one
     * the recipient still wants.
     *
     * @param  int  $userId  Recipient user ID
     * @param  string[]  $orderedTypes  Types the recipient qualifies for, most personal first
     * @param  array<string, mixed>  $data  Type-specific payload
     */
    public function dispatchFirstEnabled(int $userId, array $orderedTypes, array $data): void
    {
        if ($orderedTypes === []) {
            return;
        }

        $this->notifications->create($userId, $orderedTypes[0], $data);

        // Loaded lazily and once: an unmapped-only type list never touches the DB.
        $user = null;

        foreach ($orderedTypes as $type) {
            $prefKey = self::TYPE_TO_PREFERENCE[$type] ?? null;
            if ($prefKey === null) {
                continue;
            }

            $user ??= $this->users->findById($userId);
            if (!$user || empty($user['email'])) {
                return;
            }

            if (!$this->preferences->notificationPreference($userId, $prefKey)) {
                continue;
            }

            // No MAIL_ENABLED check here any more. The queue worker leaves
            // everything alone while mail is switched off, so notifications wait
            // and go out when it is switched back on instead of being dropped.
            $mailable = $this->buildMailable($type, (string) $user['email'], $data);
            if ($mailable !== null) {
                $this->mailQueue->enqueue($mailable, 'notification', $userId);
            }

            // At most one email per recipient per event.
            return;
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
            CommentAudienceResolver::TYPE_REPLY,
            CommentAudienceResolver::TYPE_AUTHORED,
            CommentAudienceResolver::TYPE_MODERATION,
            CommentAudienceResolver::TYPE_BLOG => new NewCommentMail(
                $to,
                (string) ($data['post_title'] ?? ''),
                (string) ($data['blog_slug'] ?? ''),
                (string) ($data['post_slug'] ?? ''),
                (string) ($data['commenter_name'] ?? 'A reader'),
                (string) ($data['comment_excerpt'] ?? ''),
                (bool) ($data['awaiting_moderation'] ?? false),
                (int) ($data['comment_id'] ?? 0),
                self::COMMENT_MAIL_REASON[$type],
                (int) ($data['blog_id'] ?? 0)
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
