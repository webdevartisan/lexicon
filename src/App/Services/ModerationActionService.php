<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ModerationWarningMail;
use App\Models\CommentModel;
use App\Models\ModerationCaseModel;
use App\Models\NotificationModel;
use App\Models\PostModel;
use App\Models\UserModel;
use DateTimeImmutable;
use DateTimeZone;
use Framework\Database;
use RuntimeException;

/**
 * Carries out moderation decisions on a case, whoever made them.
 *
 * A rule and a moderator go through the same methods, and the only difference
 * is the actor: null means a rule applied it. Every action writes its audit row
 * inside the same transaction as the change, so the trail can never claim an
 * action that did not happen, and it always says which of the two was behind it.
 */
class ModerationActionService
{
    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_MODERATOR = 'moderator';

    public function __construct(
        private Database $database,
        private ModerationCaseModel $cases,
        private PostModel $posts,
        private CommentModel $comments,
        private UserSuspensionService $suspensions,
        private AuditService $audit,
        private UserModel $users,
        private NotificationModel $notifications,
        private MailQueueService $mailQueue,
    ) {}

    /**
     * Hide the reported item from readers.
     *
     * @param  array<string, mixed>  $case
     * @param  int|null  $actorId  The moderator, or null when a rule applied it
     * @param  string|null  $rule  Category whose rule applied it
     *
     * @throws RuntimeException When the item is already hidden or no longer exists
     */
    public function hideContent(array $case, ?int $actorId, string $reason, ?string $rule = null): void
    {
        $caseId = (int) $case['id'];
        $subjectId = (int) $case['subject_id'];

        $this->atomically(function () use ($case, $caseId, $subjectId, $actorId, $reason, $rule): void {
            $hidden = match ($case['subject_type']) {
                'post' => $this->posts->hideForModeration($subjectId),
                'comment' => $this->comments->hideForModeration($subjectId),
                default => throw new RuntimeException("Unknown report subject type '{$case['subject_type']}'."),
            };

            if (!$hidden) {
                throw new RuntimeException("The reported {$case['subject_type']} is already hidden or no longer exists.");
            }

            $this->cases->setContentStatus($caseId, 'hidden');
            $this->record($case, 'moderation.content_hidden', $actorId, $rule, ['reason' => $reason]);
        });
    }

    /**
     * Put a hidden item back the way it was.
     *
     * @param  array<string, mixed>  $case
     *
     * @throws RuntimeException When the item is not hidden by moderation, or a
     *                          post has no status recorded to return to
     */
    public function restoreContent(array $case, int $actorId, string $reason): void
    {
        $caseId = (int) $case['id'];
        $subjectId = (int) $case['subject_id'];

        $this->atomically(function () use ($case, $caseId, $subjectId, $actorId, $reason): void {
            $restored = match ($case['subject_type']) {
                'post' => $this->posts->restoreFromModeration($subjectId),
                'comment' => $this->comments->unhideFromModeration($subjectId),
                default => throw new RuntimeException("Unknown report subject type '{$case['subject_type']}'."),
            };

            if (!$restored) {
                throw new RuntimeException(
                    "The reported {$case['subject_type']} could not be restored: it is not hidden by moderation, "
                    .'it no longer exists, or it has no earlier status recorded. Nothing was changed.'
                );
            }

            $this->cases->setContentStatus($caseId, 'visible');
            $this->record($case, 'moderation.content_restored', $actorId, null, ['reason' => $reason]);
        });
    }

    /**
     * Warn the author: an in-app notification plus an email with the
     * moderator's own words. The email is queued, so it is part of the same
     * transaction and cannot go out for a decision that was rolled back.
     *
     * @param  array<string, mixed>  $case
     * @param  string  $categoryLabel  The reason the reports gave, in words
     *
     * @throws RuntimeException When there is no account to warn
     */
    public function warnAuthor(array $case, int $actorId, string $message, string $categoryLabel): void
    {
        $author = empty($case['subject_author_id']) ? null : $this->users->findById((int) $case['subject_author_id']);

        if ($author === null) {
            throw new RuntimeException('This item has no account behind it to warn.');
        }

        $label = mb_strimwidth((string) ($case['subject_snapshot'] ?? ''), 0, 80, '...');

        $this->atomically(function () use ($case, $author, $actorId, $message, $categoryLabel, $label): void {
            $this->notifications->create((int) $author['id'], 'moderation.warning', [
                'case_id' => (int) $case['id'],
                'subject_type' => $case['subject_type'],
                'subject_label' => $label,
                'category' => $categoryLabel,
                'message' => $message,
            ]);

            $this->mailQueue->enqueue(
                new ModerationWarningMail(
                    (string) $author['email'],
                    (string) $author['handle'],
                    (string) $case['subject_type'],
                    $label,
                    $categoryLabel,
                    $message
                ),
                'moderation_case',
                (int) $case['id']
            );

            $this->record($case, 'moderation.author_warned', $actorId, null, ['message' => $message]);
        });
    }

    /**
     * Suspend the author of the reported item through the regular suspension
     * cascade, so a rule and a person suspend in exactly the same way.
     *
     * @param  array<string, mixed>  $case
     * @param  string|null  $expiresAt  UTC 'Y-m-d H:i:s', or null for permanent
     * @param  int|null  $actorId  The moderator, or null when a rule applied it
     * @param  string|null  $rule  Category whose rule applied it
     * @return array{blogs: int, comments: int} What the cascade hid
     *
     * @throws RuntimeException When there is no account to suspend or the cascade fails
     */
    public function suspendAuthor(array $case, ?string $expiresAt, ?int $actorId, string $reason, ?string $rule = null): array
    {
        if (empty($case['subject_author_id'])) {
            throw new RuntimeException('This item has no account behind it to suspend.');
        }

        $authorId = (int) $case['subject_author_id'];

        return $this->atomically(function () use ($case, $authorId, $expiresAt, $actorId, $reason, $rule): array {
            $hidden = $this->suspensions->suspend($authorId, $expiresAt, $reason, $actorId, $rule, (int) $case['id']);

            $this->record($case, 'moderation.author_suspended', $actorId, $rule, [
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'blogs_hidden' => $hidden['blogs'],
                'comments_hidden' => $hidden['comments'],
            ]);

            return $hidden;
        });
    }

    /**
     * The UTC end of a suspension that starts now and lasts $hours.
     */
    public static function endsInHours(int $hours): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify("+{$hours} hours")->format('Y-m-d H:i:s');
    }

    /**
     * Write the audit row for a decision on a case.
     *
     * Public so the rule engine records proposals, escalations and failures in
     * the same shape as the actions themselves.
     *
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $details
     */
    public function record(array $case, string $action, ?int $actorId, ?string $rule, array $details = []): void
    {
        $this->audit->log(
            $actorId,
            $action,
            'moderation_case',
            (int) $case['id'],
            [
                'actor_type' => $actorId === null ? self::ACTOR_SYSTEM : self::ACTOR_MODERATOR,
                'rule' => $rule,
                'subject_type' => $case['subject_type'],
                'subject_id' => (int) $case['subject_id'],
                'target_user_id' => $case['subject_author_id'] === null ? null : (int) $case['subject_author_id'],
                'target_handle' => $case['subject_author_handle'],
            ] + $details
        );
    }

    /**
     * Join the caller's transaction when there is one; Database refuses to nest.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    private function atomically(callable $work): mixed
    {
        return $this->database->inTransaction() ? $work() : $this->database->transaction($work);
    }
}
