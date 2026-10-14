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
 * The wording is not built here. A row says which sentence it is (a locale key
 * under notifications.types) and what fills it: the actor, the target, and any
 * smaller values. The row partial translates it and sets actor and target apart,
 * so every language gets the same emphasis. NotificationFeed fills in what the
 * payload only names by id, such as a blog or a person's picture.
 *
 * Tones are named rather than coloured, so each surface can dress them.
 */
final class NotificationPresenter
{
    public const TONE_POSITIVE = 'positive';

    public const TONE_ATTENTION = 'attention';

    public const TONE_CRITICAL = 'critical';

    public const TONE_NEUTRAL = 'neutral';

    /** Types that read as one sentence whatever their payload holds. */
    private const KNOWN_TYPES = [
        'blog.invite', 'blog.invite_declined',
        'post.submitted', 'post.submitted_unassigned', 'post.approved', 'post.needs_changes', 'post.published',
        'post.reviewer_assigned', 'post.reviewer_stale', 'post.workflow_disabled',
        'collaborator.role_changed', 'collaborator.removed',
        'comment.reply', 'comment.on_your_post', 'comment.awaiting_moderation', 'comment.on_blog', 'comment.created',
        'moderation.warning', 'moderation.reporter_warning', 'moderation.reporting_paused',
        'analytics.milestone', 'analytics.spike',
        'admin.report_threshold', 'admin.mail_queue_failures', 'admin.scheduler_stalled', 'admin.email_template_failed',
    ];

    /**
     * @param  array<string, mixed>  $row  A notifications table row
     * @return array{
     *     id: int, type: string, scope: string,
     *     sentence: array{key: string, actor: string|null, target: string|null, params: array<string, string>},
     *     actor: array{name: string|null, handle: string|null, id: int|null}|null,
     *     blogId: int, blogName: string|null, role: string|null,
     *     quote: string, inviteToken: string|null, icon: string, tone: string, href: string, isUnread: bool, createdAt: string|null
     * }
     */
    public static function for(array $row): array
    {
        $type = (string) ($row['type'] ?? '');
        $payload = json_decode((string) ($row['data'] ?? '{}'), true) ?: [];

        return [
            'id' => (int) ($row['id'] ?? 0),
            'type' => $type,
            'scope' => (string) ($row['scope'] ?? 'personal'),
            'sentence' => self::sentence($type, $payload),
            'actor' => self::actor($type, $payload),
            'blogId' => (int) ($payload['blog_id'] ?? 0),
            'blogName' => isset($payload['blog_name']) && $payload['blog_name'] !== '' ? (string) $payload['blog_name'] : null,
            'role' => isset($payload['new_role']) ? (string) $payload['new_role'] : (isset($payload['role']) ? (string) $payload['role'] : null),
            // A moderator's note is the whole point of its notification; a
            // comment's excerpt is a preview of something that lives elsewhere.
            'quote' => trim((string) ($payload['message'] ?? $payload['comment_excerpt'] ?? '')),
            'inviteToken' => $type === 'blog.invite' ? self::text($payload, 'token') : null,
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
     * Which sentence this row is, and what fills it.
     *
     * The target is the name of the thing the notification is about, a post or
     * a blog. A blog the payload only knows by id gets its name from
     * NotificationFeed, which is why the target can be null here.
     *
     * @param  array<string, mixed>  $payload
     * @return array{key: string, actor: string|null, target: string|null, params: array<string, string>}
     */
    private static function sentence(string $type, array $payload): array
    {
        if (!in_array($type, self::KNOWN_TYPES, true)) {
            return ['key' => 'notifications.types.unknown', 'actor' => null, 'target' => $type, 'params' => []];
        }

        $post = self::text($payload, 'post_title');
        $blog = self::text($payload, 'blog_name');

        [$target, $params] = match ($type) {
            'blog.invite', 'blog.invite_declined', 'collaborator.role_changed', 'collaborator.removed', 'analytics.spike' => [$blog, []],
            'moderation.warning' => [self::text($payload, 'subject_label'), []],
            'moderation.reporter_warning' => [null, []],
            'moderation.reporting_paused' => [self::text($payload, 'until_label'), []],
            'analytics.milestone' => [$post, ['count' => number_format((int) ($payload['threshold'] ?? 0))]],
            'admin.report_threshold' => ['#'.(int) ($payload['case_id'] ?? 0), ['count' => (string) (int) ($payload['report_count'] ?? 0)]],
            'admin.mail_queue_failures' => [null, [
                'count' => (string) (int) ($payload['failed_count'] ?? 0),
                'minutes' => (string) (int) ($payload['window_minutes'] ?? 60),
            ]],
            'admin.scheduler_stalled' => [null, ['minutes' => (string) (int) round(($payload['heartbeat_age_seconds'] ?? 0) / 60)]],
            'admin.email_template_failed' => [self::text($payload, 'email'), []],
            default => [$post, []],
        };

        // A held comment reads differently from one already on the page.
        $key = str_replace('.', '_', $type === 'comment.created' ? 'comment.on_blog' : $type);
        if (in_array($type, ['comment.on_blog', 'comment.created'], true) && !empty($payload['awaiting_moderation'])) {
            $key .= '_pending';
        }

        return [
            'key' => 'notifications.types.'.$key,
            'actor' => self::actor($type, $payload)['name'] ?? null,
            'target' => $target,
            'params' => $params,
        ];
    }

    /**
     * Who did the thing, as far as the payload says.
     *
     * Null for notices the platform itself sends. A person known only by
     * handle or id gets their name and picture from NotificationFeed.
     *
     * @param  array<string, mixed>  $payload
     * @return array{name: string|null, handle: string|null, id: int|null}|null
     */
    private static function actor(string $type, array $payload): ?array
    {
        $handleKey = match ($type) {
            'post.submitted', 'post.submitted_unassigned' => 'author_handle',
            'post.approved', 'post.needs_changes' => 'reviewer_handle',
            'post.reviewer_assigned', 'collaborator.role_changed', 'collaborator.removed' => 'actor_handle',
            'comment.reply', 'comment.on_your_post', 'comment.awaiting_moderation', 'comment.on_blog', 'comment.created' => 'commenter_handle',
            default => null,
        };

        $handle = $handleKey !== null ? self::text($payload, $handleKey) : null;
        $id = $type === 'blog.invite' && !empty($payload['invited_by']) ? (int) $payload['invited_by'] : null;
        $name = match ($type) {
            'comment.reply', 'comment.on_your_post', 'comment.awaiting_moderation',
            'comment.on_blog', 'comment.created' => self::text($payload, 'commenter_name'),
            'blog.invite_declined' => self::text($payload, 'declined_email'),
            default => null,
        };

        if ($handle === null && $id === null && $name === null) {
            return null;
        }

        return ['name' => $name ?? ($handle !== null ? '@'.$handle : null), 'handle' => $handle, 'id' => $id];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function text(array $payload, string $key): ?string
    {
        $value = trim((string) ($payload[$key] ?? ''));

        return $value === '' ? null : $value;
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
            'analytics.milestone' => 'trophy',
            'analytics.spike' => 'trending-up',
            'admin.report_threshold' => 'flag',
            'admin.mail_queue_failures' => 'mail-warning',
            'admin.scheduler_stalled' => 'clock-alert',
            'admin.email_template_failed' => 'mail-x',
            default => 'bell',
        };
    }

    private static function tone(string $type): string
    {
        return match (true) {
            in_array($type, ['post.approved', 'post.published', 'analytics.milestone', 'analytics.spike'], true) => self::TONE_POSITIVE,
            in_array($type, ['post.needs_changes', 'post.workflow_disabled', 'comment.awaiting_moderation'], true) => self::TONE_ATTENTION,
            in_array($type, [
                'collaborator.removed', 'moderation.warning', 'moderation.reporter_warning', 'moderation.reporting_paused',
                'admin.report_threshold', 'admin.mail_queue_failures', 'admin.scheduler_stalled', 'admin.email_template_failed',
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
            // Rows written before the payload carried blog_id have nowhere to point.
            'collaborator.role_changed' => !empty($payload['blog_id']) ? '/dashboard/blog/'.(int) $payload['blog_id'] : '',
            'analytics.milestone' => '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0).'/insights/posts/'.(int) ($payload['post_id'] ?? 0).'?range=12m',
            'analytics.spike' => '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0).'/insights?range=today',
            'admin.report_threshold' => '/admin/reports/'.(int) ($payload['case_id'] ?? 0),
            'admin.mail_queue_failures' => '/admin/mail-queue?status=failed',
            'admin.scheduler_stalled' => '/admin/scheduled-tasks',
            'admin.email_template_failed' => '/admin/email-templates',
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
