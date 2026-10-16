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

    /**
     * Everything that differs between notification types, one row per type.
     *
     * A type with a row reads as one sentence whatever its payload holds; any
     * other type is shown as unknown. Every field is optional:
     *
     * - icon: a name from _notification_icons.lex.php, 'bell' when absent
     * - tone: one of the TONE_* names, neutral when absent
     * - actor / name: the payload keys holding the actor's handle and display name
     * - target: the payload key the sentence names, post_title when absent, null for none
     * - href: a path whose {key} parts are filled with the payload's ids, or one of
     *   'post', 'comment' or 'invite', which are built from slugs and tokens instead
     */
    private const TYPES = [
        'blog.invite' => ['icon' => 'mail', 'target' => 'blog_name', 'href' => 'invite'],
        'blog.invite_declined' => ['icon' => 'mail', 'name' => 'declined_email', 'target' => 'blog_name'],

        'post.submitted' => ['icon' => 'send', 'actor' => 'author_handle', 'href' => '/dashboard/post/{post_id}/review'],
        'post.submitted_unassigned' => [
            'icon' => 'send', 'actor' => 'author_handle', 'href' => '/dashboard/post/{post_id}/review',
        ],
        'post.approved' => [
            'icon' => 'check-circle', 'tone' => self::TONE_POSITIVE, 'actor' => 'reviewer_handle',
            'href' => '/dashboard/post/{post_id}/edit',
        ],
        'post.needs_changes' => [
            'icon' => 'pen-line', 'tone' => self::TONE_ATTENTION, 'actor' => 'reviewer_handle',
            'href' => '/dashboard/post/{post_id}/edit',
        ],
        'post.published' => ['icon' => 'megaphone', 'tone' => self::TONE_POSITIVE, 'href' => 'post'],
        'post.reviewer_assigned' => [
            'icon' => 'user-check', 'actor' => 'actor_handle', 'href' => '/dashboard/post/{post_id}/review',
        ],
        'post.reviewer_stale' => ['icon' => 'rotate-ccw', 'href' => '/dashboard/post/{post_id}/review'],
        'post.workflow_disabled' => [
            'icon' => 'rotate-ccw', 'tone' => self::TONE_ATTENTION, 'href' => '/dashboard/post/{post_id}/edit',
        ],

        'collaborator.role_changed' => [
            'icon' => 'users', 'actor' => 'actor_handle', 'target' => 'blog_name', 'href' => '/dashboard/blog/{blog_id}',
        ],
        'collaborator.removed' => [
            'icon' => 'users', 'tone' => self::TONE_CRITICAL, 'actor' => 'actor_handle', 'target' => 'blog_name',
        ],

        'comment.reply' => [
            'icon' => 'reply', 'actor' => 'commenter_handle', 'name' => 'commenter_name', 'href' => 'comment',
        ],
        'comment.on_your_post' => [
            'icon' => 'message-circle', 'actor' => 'commenter_handle', 'name' => 'commenter_name', 'href' => 'comment',
        ],
        // A comment still in the queue is not public yet, so its notification
        // goes to the queue rather than to a page that would not show it.
        'comment.awaiting_moderation' => [
            'icon' => 'shield-alert', 'tone' => self::TONE_ATTENTION, 'actor' => 'commenter_handle',
            'name' => 'commenter_name', 'href' => '/dashboard/blog/{blog_id}/comments',
        ],
        'comment.on_blog' => [
            'icon' => 'message-circle', 'actor' => 'commenter_handle', 'name' => 'commenter_name', 'href' => 'comment',
        ],
        'comment.created' => [
            'icon' => 'message-circle', 'actor' => 'commenter_handle', 'name' => 'commenter_name', 'href' => 'comment',
        ],

        'moderation.warning' => ['icon' => 'alert-triangle', 'tone' => self::TONE_CRITICAL, 'target' => 'subject_label'],
        'moderation.reporter_warning' => ['icon' => 'alert-triangle', 'tone' => self::TONE_CRITICAL, 'target' => null],
        'moderation.reporting_paused' => ['icon' => 'flag-off', 'tone' => self::TONE_CRITICAL, 'target' => 'until_label'],

        'analytics.milestone' => [
            'icon' => 'trophy', 'tone' => self::TONE_POSITIVE,
            'href' => '/dashboard/blog/{blog_id}/insights/posts/{post_id}?range=12m',
        ],
        'analytics.spike' => [
            'icon' => 'trending-up', 'tone' => self::TONE_POSITIVE, 'target' => 'blog_name',
            'href' => '/dashboard/blog/{blog_id}/insights?range=today',
        ],

        'admin.report_threshold' => ['icon' => 'flag', 'tone' => self::TONE_CRITICAL, 'href' => '/admin/reports/{case_id}'],
        'admin.mail_queue_failures' => [
            'icon' => 'mail-warning', 'tone' => self::TONE_CRITICAL, 'target' => null,
            'href' => '/admin/mail-queue?status=failed',
        ],
        'admin.scheduler_stalled' => [
            'icon' => 'clock-alert', 'tone' => self::TONE_CRITICAL, 'target' => null, 'href' => '/admin/scheduled-tasks',
        ],
        'admin.email_template_failed' => [
            'icon' => 'mail-x', 'tone' => self::TONE_CRITICAL, 'target' => 'email', 'href' => '/admin/email-templates',
        ],
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
        if (!isset(self::TYPES[$type])) {
            return ['key' => 'notifications.types.unknown', 'actor' => null, 'target' => $type, 'params' => []];
        }

        $target = $type === 'admin.report_threshold'
            ? '#'.(int) ($payload['case_id'] ?? 0)
            : self::target($type, $payload);

        // A held comment reads differently from one already on the page.
        $key = str_replace('.', '_', $type === 'comment.created' ? 'comment.on_blog' : $type);
        if (in_array($type, ['comment.on_blog', 'comment.created'], true) && !empty($payload['awaiting_moderation'])) {
            $key .= '_pending';
        }

        return [
            'key' => 'notifications.types.'.$key,
            'actor' => self::actor($type, $payload)['name'] ?? null,
            'target' => $target,
            'params' => self::params($type, $payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function target(string $type, array $payload): ?string
    {
        $key = array_key_exists('target', self::TYPES[$type]) ? self::TYPES[$type]['target'] : 'post_title';

        return $key === null ? null : self::text($payload, $key);
    }

    /**
     * The smaller values a sentence fills in besides actor and target.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private static function params(string $type, array $payload): array
    {
        return match ($type) {
            'analytics.milestone' => ['count' => number_format((int) ($payload['threshold'] ?? 0))],
            'admin.report_threshold' => ['count' => (string) (int) ($payload['report_count'] ?? 0)],
            'admin.mail_queue_failures' => [
                'count' => (string) (int) ($payload['failed_count'] ?? 0),
                'minutes' => (string) (int) ($payload['window_minutes'] ?? 60),
            ],
            'admin.scheduler_stalled' => ['minutes' => (string) (int) round(($payload['heartbeat_age_seconds'] ?? 0) / 60)],
            default => [],
        };
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
        $handleKey = self::TYPES[$type]['actor'] ?? null;
        $nameKey = self::TYPES[$type]['name'] ?? null;

        $handle = $handleKey !== null ? self::text($payload, $handleKey) : null;
        $id = $type === 'blog.invite' && !empty($payload['invited_by']) ? (int) $payload['invited_by'] : null;
        $name = $nameKey !== null ? self::text($payload, $nameKey) : null;

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
        return self::TYPES[$type]['icon'] ?? 'bell';
    }

    private static function tone(string $type): string
    {
        return self::TYPES[$type]['tone'] ?? self::TONE_NEUTRAL;
    }

    /**
     * Where opening this notification should land, or '' when the notification
     * is the whole message and there is nowhere further to go.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function href(string $type, array $payload): string
    {
        // Rows written before the payload carried blog_id have nowhere to point.
        if ($type === 'collaborator.role_changed' && empty($payload['blog_id'])) {
            return '';
        }

        $href = self::TYPES[$type]['href'] ?? '';

        return match ($href) {
            'post' => self::postUrl($payload),
            'comment' => !empty($payload['awaiting_moderation'])
                ? self::fill('/dashboard/blog/{blog_id}/comments', $payload)
                : self::commentUrl($payload),
            'invite' => '/invite/'.rawurlencode((string) ($payload['token'] ?? '')),
            default => self::fill($href, $payload),
        };
    }

    /**
     * Put the payload's ids into a path template. A missing id becomes 0, as
     * the casts this replaced did, so an old row still renders a link.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function fill(string $path, array $payload): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            static fn (array $m): string => (string) (int) ($payload[$m[1]] ?? 0),
            $path
        ) ?? $path;
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
