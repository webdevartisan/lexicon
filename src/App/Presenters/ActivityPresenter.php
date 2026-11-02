<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Turns activity log codes into words for the admin screens.
 *
 * Most codes read fine once their dots and underscores become spaces
 * ("blog.invite_sent" is "Blog invite sent"), and that covers codes added
 * later too. The list below holds only the ones that would read badly.
 */
final class ActivityPresenter
{
    /** @var array<string, string> */
    private const LABELS = [
        'auth.login' => 'Signed in',
        'auth.login_failed' => 'Sign-in failed',
        'auth.captcha_failed' => 'Bot check failed',
        'auth.two_factor_failed' => 'Wrong two-step code',
        'user.two_factor_enabled' => 'Two-step sign-in turned on',
        'user.two_factor_disabled' => 'Two-step sign-in turned off',
        'user.two_factor_reset' => 'Two-step sign-in reset',
        'user.two_factor_codes_renewed' => 'New recovery codes made',
        'user.password_set_by_admin' => 'Password set by an admin',
        'password_reset.attempt_unknown_email' => 'Password reset asked for an unknown email',
        'transition_workflow' => 'Review state changed',
        'post.voted' => 'Voted on a post',
        'post.bookmark.toggled' => 'Saved or unsaved a post',
        'post.bulk_publish' => 'Posts published',
        'post.bulk_draft' => 'Posts moved to draft',
        'post.bulk_archive' => 'Posts archived',
        'post.bulk_review' => 'Posts sent for review',
        'post.bulk_delete' => 'Posts deleted',
        'comment.spam' => 'Comment marked as spam',
        'comment.bulk_approve' => 'Comments approved',
        'comment.bulk_unapprove' => 'Comments unapproved',
        'comment.bulk_spam' => 'Comments marked as spam',
        'comment.bulk_delete' => 'Comments deleted',
        'blog.self_removed' => 'Left a blog',
        'blog.subscribed' => 'Subscribed to a blog',
        'rate_limit.blocked' => 'Blocked an email or IP',
        'rate_limit.unlocked' => 'Unlocked an email or IP',
        'site.maintenance_on' => 'Maintenance mode turned on',
        'site.maintenance_off' => 'Maintenance mode turned off',
        'moderation.rule_proposed' => 'Rule proposed an action',
        'moderation.rule_escalated' => 'Rule sent the case to a person',
        'moderation.rule_skipped' => 'Rule skipped, already done',
        'moderation.action_failed' => 'Automatic action failed',
        'moderation.content_hidden' => 'Content hidden',
        'moderation.content_restored' => 'Content restored',
        'moderation.author_warned' => 'Author warned',
        'moderation.author_suspended' => 'Author suspended',
        'moderation.review_started' => 'Review started',
        'moderation.escalated' => 'Escalated',
        'moderation.case_dismissed' => 'Case dismissed',
        'moderation.case_upheld' => 'Case upheld',
        'moderation.reporter_warned' => 'Warned about unfounded reports',
        'moderation.reporting_paused' => 'Reporting paused',
        'moderation.reporting_resumed' => 'Pause ended early',
    ];

    /**
     * Words for an activity code.
     *
     * @param  string  $action  Code as stored, e.g. 'blog.invite_sent'
     * @return string Label such as "Blog invite sent"
     */
    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? self::words($action);
    }

    /**
     * Words for what an entry acted on, e.g. "Blog invitation #240".
     *
     * Some entries name no single record and store 0, which says nothing, so
     * only a real id is shown.
     *
     * @param  string|null  $type  Resource type as stored, e.g. 'blog_invitation'
     * @param  int|string|null  $id  Resource id
     */
    public static function target(?string $type, int|string|null $id): string
    {
        $words = self::words((string) $type);

        if ($id === null || (int) $id <= 0) {
            return $words;
        }

        return trim($words.' #'.(int) $id);
    }

    private static function words(string $code): string
    {
        return ucfirst(trim((string) preg_replace('/[._]+/', ' ', $code)));
    }
}
