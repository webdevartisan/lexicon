<?php

declare(strict_types=1);

namespace App\Presenters;

/**
 * Decides which save/publish affordances the post editor offers.
 *
 * The editor used to compute this inline in the template, which made the rules
 * impossible to test and easy to drift from the backend. This class is the
 * single place that answers "what can this person do to this post right now",
 * and it deliberately mirrors WorkflowService::constrainStatusForRole() — that
 * service stays the authority, this one only decides what to render.
 */
final class PostActionPresenter
{
    public const INTENT_SAVE_DRAFT = 'save_draft';

    public const INTENT_SUBMIT_REVIEW = 'submit_review';

    public const INTENT_PUBLISH = 'publish';

    public const INTENT_SCHEDULE = 'schedule';

    public const INTENT_UPDATE = 'update';

    public const INTENT_UNPUBLISH = 'unpublish';

    public const INTENT_ARCHIVE = 'archive';

    /** Every intent the form is allowed to submit. */
    public const INTENTS = [
        self::INTENT_SAVE_DRAFT,
        self::INTENT_SUBMIT_REVIEW,
        self::INTENT_PUBLISH,
        self::INTENT_SCHEDULE,
        self::INTENT_UPDATE,
        self::INTENT_UNPUBLISH,
        self::INTENT_ARCHIVE,
    ];

    /** @var array<string, array{label: string, tone: string}> */
    private const PILLS = [
        'draft' => ['label' => 'Draft', 'tone' => 'slate'],
        'pending' => ['label' => 'Pending review', 'tone' => 'amber'],
        'scheduled' => ['label' => 'Scheduled', 'tone' => 'violet'],
        'published' => ['label' => 'Published', 'tone' => 'emerald'],
        'archived' => ['label' => 'Archived', 'tone' => 'zinc'],
    ];

    /**
     * Build the action set for one post in one person's hands.
     *
     * @param  string  $status  Current post status
     * @param  string|null  $role  Viewer's effective role on the blog
     * @param  bool  $workflowOn  Whether the blog runs the review pipeline
     * @param  bool  $hasFutureDate  Whether published_at is set to a future time
     * @param  string|null  $workflowState  Review state, e.g. 'needs_changes'
     * @return array{primary: array<string, string>, secondary: array<string, string>|null, menu: array<int, array<string, string>>, pill: array{label: string, tone: string}}
     */
    public static function for(
        string $status,
        ?string $role,
        bool $workflowOn,
        bool $hasFutureDate = false,
        ?string $workflowState = null
    ): array {
        $canPublish = in_array($role, ['owner', 'editor'], true)
            || ($role === 'author' && !$workflowOn);

        return [
            'primary' => self::primary($status, $canPublish, $workflowOn, $hasFutureDate, $workflowState),
            'secondary' => self::secondary($status, $canPublish, $workflowOn),
            'menu' => self::menu($status, $canPublish, $hasFutureDate),
            'pill' => self::PILLS[$status] ?? self::PILLS['draft'],
        ];
    }

    /**
     * Translate a submitted intent into the status it is asking for.
     *
     * This is only a request. WorkflowService::constrainStatusForRole() still
     * has the final say, so a forged intent buys nothing.
     *
     * @param  string  $intent  Value of the clicked submit button
     * @param  string  $currentStatus  Status the post holds today
     * @param  bool  $hasFutureDate  Whether published_at is set to a future time
     * @return string The requested status
     */
    public static function statusForIntent(string $intent, string $currentStatus, bool $hasFutureDate): string
    {
        return match ($intent) {
            self::INTENT_SAVE_DRAFT, self::INTENT_UNPUBLISH => 'draft',
            self::INTENT_SUBMIT_REVIEW => 'pending',
            self::INTENT_SCHEDULE => $hasFutureDate ? 'scheduled' : 'published',
            // Publishing with a future date still defers; the date wins over
            // the button so the two can never disagree.
            self::INTENT_PUBLISH => $hasFutureDate ? 'scheduled' : 'published',
            self::INTENT_ARCHIVE => 'archived',
            default => $currentStatus,
        };
    }

    /**
     * The one button that carries the post forward.
     *
     * @return array<string, string>
     */
    private static function primary(
        string $status,
        bool $canPublish,
        bool $workflowOn,
        bool $hasFutureDate,
        ?string $workflowState
    ): array {
        if ($status === 'draft') {
            if ($canPublish) {
                return $hasFutureDate
                    ? self::button(self::INTENT_SCHEDULE, 'Schedule', 'calendar-clock', 'blue')
                    : self::button(self::INTENT_PUBLISH, 'Publish', 'send', 'green');
            }

            // Without the pipeline there is nobody to submit to, so saving is
            // the only move a contributor has.
            return $workflowOn
                ? self::button(self::INTENT_SUBMIT_REVIEW, 'Submit for review', 'send', 'blue')
                : self::button(self::INTENT_SAVE_DRAFT, 'Save', 'save', 'blue');
        }

        if ($status === 'pending') {
            if ($canPublish) {
                return $hasFutureDate
                    ? self::button(self::INTENT_SCHEDULE, 'Schedule', 'calendar-clock', 'blue')
                    : self::button(self::INTENT_PUBLISH, 'Approve & publish', 'send', 'green');
            }

            // A blog can hold pending posts after the pipeline is switched
            // off, and offering to resubmit to a queue nobody reads would lie.
            return ($workflowOn && $workflowState === 'needs_changes')
                ? self::button(self::INTENT_SUBMIT_REVIEW, 'Resubmit for review', 'send', 'blue')
                : self::button(self::INTENT_SUBMIT_REVIEW, 'Save changes', 'save', 'blue');
        }

        if ($status === 'scheduled') {
            if (!$canPublish) {
                return self::button(self::INTENT_UPDATE, 'Save changes', 'save', 'blue');
            }

            // Clearing the date turns a scheduled post into an ordinary publish.
            return $hasFutureDate
                ? self::button(self::INTENT_SCHEDULE, 'Update schedule', 'calendar-clock', 'blue')
                : self::button(self::INTENT_PUBLISH, 'Publish now', 'send', 'green');
        }

        return self::button(self::INTENT_UPDATE, 'Update', 'save', 'blue');
    }

    /**
     * The quieter escape hatch beside the primary, when one makes sense.
     *
     * @return array<string, string>|null
     */
    private static function secondary(string $status, bool $canPublish, bool $workflowOn): ?array
    {
        if ($status === 'draft') {
            // The primary already is "Save" in this case; a second one is noise.
            if (!$canPublish && !$workflowOn) {
                return null;
            }

            return self::button(self::INTENT_SAVE_DRAFT, 'Save draft', 'save', 'slate');
        }

        // An author can pull their own submission back out of the queue.
        if ($status === 'pending' && !$canPublish) {
            return self::button(self::INTENT_SAVE_DRAFT, 'Withdraw to draft', 'undo-2', 'slate');
        }

        return null;
    }

    /**
     * Rarer state changes, tucked behind the overflow control.
     *
     * @return array<int, array<string, string>>
     */
    private static function menu(string $status, bool $canPublish, bool $hasFutureDate): array
    {
        if (!$canPublish) {
            return [];
        }

        return match ($status) {
            'pending' => [
                self::button(self::INTENT_SAVE_DRAFT, 'Return to draft', 'undo-2', 'slate'),
            ],
            'scheduled' => array_values(array_filter([
                $hasFutureDate ? self::button(self::INTENT_PUBLISH, 'Publish now', 'send', 'slate') : null,
                self::button(self::INTENT_SAVE_DRAFT, 'Cancel schedule', 'undo-2', 'slate'),
                self::button(self::INTENT_ARCHIVE, 'Archive', 'archive', 'slate'),
            ])),
            'published' => [
                self::button(self::INTENT_UNPUBLISH, 'Unpublish', 'eye-off', 'slate'),
                self::button(self::INTENT_ARCHIVE, 'Archive', 'archive', 'slate'),
            ],
            'archived' => [
                self::button(self::INTENT_SAVE_DRAFT, 'Restore as draft', 'undo-2', 'slate'),
                self::button(self::INTENT_PUBLISH, 'Publish', 'send', 'slate'),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private static function button(string $intent, string $label, string $icon, string $variant): array
    {
        return [
            'intent' => $intent,
            'label' => $label,
            'icon' => $icon,
            'variant' => $variant,
        ];
    }
}
