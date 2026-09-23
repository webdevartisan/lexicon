<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Turns one notification row into the few facts any surface needs to draw it.
 *
 * All of this used to live inside the list item template, which meant the
 * masthead panel, the reader's own page and the two control panel inboxes each
 * had to repeat it, and the click-through target was posted back by the
 * browser as a hidden field. Here it is worked out once, on the server, from
 * the row's own payload.
 *
 * Tones are named rather than coloured. The front and the control panel dress
 * them differently, and a class name from one would be meaningless in the other.
 */
final class NotificationPresenter
{
    public const TONE_POSITIVE = 'positive';

    public const TONE_ATTENTION = 'attention';

    public const TONE_CRITICAL = 'critical';

    public const TONE_NEUTRAL = 'neutral';

    /**
     * @param  array<string, mixed>  $row  A notifications table row
     * @return array{
     *     id: int, type: string, title: string, message: string, icon: string,
     *     tone: string, href: string, isUnread: bool, createdAt: string|null
     * }
     */
    public static function for(array $row): array
    {
        $type = (string) ($row['type'] ?? '');
        $payload = json_decode((string) ($row['data'] ?? '{}'), true) ?: [];

        return [
            'id' => (int) ($row['id'] ?? 0),
            'type' => $type,
            'title' => self::title($type, $payload),
            'message' => trim((string) ($payload['message'] ?? '')),
            'icon' => self::icon($type),
            'tone' => self::tone($type),
            'href' => self::href($type, $payload),
            'isUnread' => empty($row['read_at']),
            'createdAt' => isset($row['created_at']) ? (string) $row['created_at'] : null,
        ];
    }

    /**
     * Present a whole list in one call.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function forAll(array $rows): array
    {
        return array_map(static fn (array $row): array => self::for($row), $rows);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function title(string $type, array $payload): string
    {
        return match ($type) {
            'blog.invite' => 'You were invited to a blog as '.($payload['role'] ?? ''),
            'blog.invite_declined' => ($payload['declined_email'] ?? 'Someone').' declined an invite',
            'post.submitted' => 'A post was submitted for your review',
            'post.submitted_unassigned' => 'A post was submitted for review',
            'post.approved' => 'Your post '.($payload['post_title'] ?? '').' was approved',
            'post.needs_changes' => 'Changes requested on '.($payload['post_title'] ?? ''),
            'post.published' => 'Your post '.($payload['post_title'] ?? '').' is live',
            'post.reviewer_assigned' => 'You were assigned to review a post',
            'post.reviewer_stale' => 'Reviewer reset on a post',
            'post.workflow_disabled' => 'A post of yours was reset to draft',
            'collaborator.role_changed' => 'Your role on '.($payload['blog_name'] ?? '').' changed',
            'collaborator.removed' => 'You were removed from '.($payload['blog_name'] ?? ''),
            'comment.reply' => ($payload['commenter_name'] ?? 'Someone').' replied to your comment on '.($payload['post_title'] ?? 'a post'),
            'comment.on_your_post' => ($payload['commenter_name'] ?? 'A reader').' commented on your post '.($payload['post_title'] ?? ''),
            'comment.awaiting_moderation' => 'A comment needs approval on '.($payload['post_title'] ?? 'a post'),
            'moderation.warning' => 'A moderator warned you about your '.($payload['subject_type'] ?? 'content').' '.($payload['subject_label'] ?? ''),
            'moderation.reporter_warning' => 'A moderator warned you about unfounded reports',
            'moderation.reporting_paused' => 'Your reports are paused until '.($payload['until_label'] ?? 'further notice'),
            // comment.created rows predate the split into four types
            'comment.on_blog', 'comment.created' => ($payload['commenter_name'] ?? 'A reader').' commented on '.($payload['post_title'] ?? 'a post')
                .(!empty($payload['awaiting_moderation']) ? ' (awaiting moderation)' : ''),
            'admin.report_threshold' => 'Case #'.($payload['case_id'] ?? '?').' has '.($payload['report_count'] ?? 0).' reports and needs a look',
            'admin.mail_queue_failures' => ($payload['failed_count'] ?? 0).' emails failed to send in the last '.($payload['window_minutes'] ?? 60).' minutes',
            'admin.scheduler_stalled' => 'The task scheduler has not ticked in '.round(($payload['heartbeat_age_seconds'] ?? 0) / 60).' minute(s)',
            default => $type,
        };
    }

    private static function icon(string $type): string
    {
        return match ($type) {
            'blog.invite', 'blog.invite_declined' => 'mail',
            'post.submitted', 'post.submitted_unassigned' => 'send',
            'post.approved' => 'check-circle',
            'post.needs_changes' => 'pen-line',
            'post.published' => 'megaphone',
            'post.reviewer_assigned' => 'user-check',
            'post.reviewer_stale', 'post.workflow_disabled' => 'rotate-ccw',
            'collaborator.role_changed', 'collaborator.removed' => 'users',
            'comment.reply' => 'reply',
            'comment.awaiting_moderation' => 'shield-alert',
            'moderation.warning', 'moderation.reporter_warning' => 'alert-triangle',
            'moderation.reporting_paused' => 'flag-off',
            'comment.on_your_post', 'comment.on_blog', 'comment.created' => 'message-circle',
            'admin.report_threshold' => 'flag',
            'admin.mail_queue_failures' => 'mail-warning',
            'admin.scheduler_stalled' => 'clock-alert',
            default => 'bell',
        };
    }

    private static function tone(string $type): string
    {
        return match (true) {
            in_array($type, ['post.approved', 'post.published'], true) => self::TONE_POSITIVE,
            in_array($type, ['post.needs_changes', 'post.workflow_disabled', 'comment.awaiting_moderation'], true) => self::TONE_ATTENTION,
            in_array($type, [
                'collaborator.removed', 'moderation.warning', 'moderation.reporter_warning', 'moderation.reporting_paused',
                'admin.report_threshold', 'admin.mail_queue_failures', 'admin.scheduler_stalled',
            ], true) => self::TONE_CRITICAL,
            default => self::TONE_NEUTRAL,
        };
    }

    /**
     * Where opening this notification should land, or '' when the notification
     * is the whole message and there is nowhere further to go.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function href(string $type, array $payload): string
    {
        return match ($type) {
            'post.submitted', 'post.submitted_unassigned', 'post.reviewer_assigned',
            'post.reviewer_stale' => '/dashboard/post/'.(int) ($payload['post_id'] ?? 0).'/review',
            'post.approved', 'post.needs_changes',
            'post.workflow_disabled' => '/dashboard/post/'.(int) ($payload['post_id'] ?? 0).'/edit',
            'post.published' => self::postUrl($payload),
            'blog.invite' => '/invite/'.rawurlencode((string) ($payload['token'] ?? '')),
            // A comment still in the queue is not public yet, so its notification
            // goes to the queue rather than to a page that would not show it.
            'comment.awaiting_moderation' => '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0).'/comments',
            'comment.reply', 'comment.on_your_post', 'comment.on_blog', 'comment.created' => !empty($payload['awaiting_moderation'])
                ? '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0).'/comments'
                : self::commentUrl($payload),
            'collaborator.role_changed' => '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0),
            'admin.report_threshold' => '/admin/reports/'.(int) ($payload['case_id'] ?? 0),
            'admin.mail_queue_failures' => '/admin/mail-queue?status=failed',
            'admin.scheduler_stalled' => '/admin/scheduled-tasks',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function commentUrl(array $payload): string
    {
        $post = self::postUrl($payload);

        if ($post === '' || empty($payload['comment_id'])) {
            return $post;
        }

        return $post.'#comment-'.(int) $payload['comment_id'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function postUrl(array $payload): string
    {
        $blogSlug = (string) ($payload['blog_slug'] ?? '');
        $postSlug = (string) ($payload['post_slug'] ?? '');

        // Half a URL lands on a 404. A row missing either slug is older than
        // those payload keys, and staying put beats sending somebody nowhere.
        if ($blogSlug === '' || $postSlug === '') {
            return '';
        }

        return '/blog/'.rawurlencode($blogSlug).'/'.rawurlencode($postSlug);
    }
}
