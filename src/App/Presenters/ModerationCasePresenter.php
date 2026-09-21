<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Derives what the reports queue and case page show about a case's item:
 * where it lives publicly and what readers see of it right now.
 *
 * content_status on the case records moderation decisions; whether the item
 * still exists at all is read from the live join, since deleting a post or
 * comment never goes through the case.
 */
final class ModerationCasePresenter
{
    public const STATUS_LABELS = [
        'open' => 'Open',
        'in_review' => 'In review',
        'escalated' => 'Escalated',
        'resolved' => 'Resolved',
    ];

    /**
     * @param  array<string, mixed>  $case  A row from findForQueue() or findDetail()
     * @return array<string, mixed> The row plus public_url, content_state and content_label
     */
    public static function present(array $case): array
    {
        $gone = $case['live_id'] === null
            || ($case['subject_type'] === 'comment' && $case['comment_deleted_at'] !== null);

        $state = match (true) {
            $gone => 'deleted',
            $case['content_status'] === 'hidden' => 'hidden',
            default => 'visible',
        };

        $case['content_state'] = $state;
        $case['content_label'] = ['deleted' => 'Deleted', 'hidden' => 'Hidden', 'visible' => 'Visible'][$state];
        $case['status_label'] = self::STATUS_LABELS[$case['status']] ?? (string) $case['status'];
        $case['public_url'] = $gone ? null : self::publicUrl($case);

        return $case;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private static function publicUrl(array $case): ?string
    {
        if (empty($case['blog_slug']) || empty($case['post_slug'])) {
            return null;
        }

        $url = '/blog/'.rawurlencode((string) $case['blog_slug']).'/'.rawurlencode((string) $case['post_slug']);

        return $case['subject_type'] === 'comment' ? $url.'#comment-'.(int) $case['subject_id'] : $url;
    }
}
