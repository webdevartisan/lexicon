<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentReportModel;
use App\Models\ModerationCaseModel;
use App\Models\ModerationCategoryModel;
use Framework\Database;
use RuntimeException;

/**
 * A moderator's decisions on a case: pick it up, escalate it, dismiss it, or
 * uphold it with whatever follows (hide, warn, suspend).
 *
 * Each decision is one transaction: the action on the content or account, the
 * outcome on every report, the case resolution and the audit row either all
 * happen or none do. A decision on a case someone else just closed fails
 * rather than stacking a second verdict on top.
 */
class ModerationCaseService
{
    public function __construct(
        private Database $database,
        private ModerationCaseModel $cases,
        private ContentReportModel $reports,
        private ModerationCategoryModel $categories,
        private ModerationActionService $actions,
        private ReporterStandingService $standing,
    ) {}

    /**
     * @param  array<string, mixed>  $case
     *
     * @throws RuntimeException When the case is closed
     */
    public function startReview(array $case, int $actorId): void
    {
        $this->assertOpen($case);

        if ($this->cases->startReview((int) $case['id'])) {
            $this->actions->record($case, 'moderation.review_started', $actorId, null);
        }
    }

    /**
     * Hand the case up to someone who can do more, such as suspend.
     *
     * @param  array<string, mixed>  $case
     *
     * @throws RuntimeException When the case is closed
     */
    public function escalate(array $case, int $actorId, string $note): void
    {
        $this->assertOpen($case);

        $this->atomically(function () use ($case, $actorId, $note): void {
            $this->cases->escalate((int) $case['id']);
            $this->actions->record($case, 'moderation.escalated', $actorId, null, ['note' => $note]);
        });
    }

    /**
     * The reports were wrong. Content a rule or moderator hid comes back, and
     * reports the moderator names as unfounded count against their reporters.
     *
     * @param  array<string, mixed>  $case
     * @param  int[]  $unfoundedIds  Report ids ruled unfounded
     * @return bool Whether hidden content was restored
     *
     * @throws RuntimeException When the case is closed or the content cannot be restored
     */
    public function dismiss(array $case, int $actorId, string $note, array $unfoundedIds): bool
    {
        $this->assertOpen($case);
        $restore = $case['content_status'] === 'hidden';

        $this->atomically(function () use ($case, $actorId, $note, $unfoundedIds, $restore): void {
            if ($restore) {
                $this->actions->restoreContent($case, $actorId, $note);
            }

            $this->reports->settleCase((int) $case['id'], 'dismissed', $actorId, $unfoundedIds);
            $this->close($case, 'dismissed', $actorId, $note, [
                'unfounded_report_ids' => array_values(array_map('intval', $unfoundedIds)),
                'restored' => $restore,
            ]);
            $this->warnUnfoundedReporters((int) $case['id'], $unfoundedIds);
        });

        return $restore;
    }

    /**
     * The reports were right. Optionally hide the item and warn its author.
     *
     * @param  array<string, mixed>  $case
     * @param  string|null  $warning  Message to the author, or null for no warning
     *
     * @throws RuntimeException When the case is closed, or hiding or warning fails
     */
    public function uphold(array $case, int $actorId, string $note, bool $hide, ?string $warning): void
    {
        $this->assertOpen($case);
        $hide = $hide && $case['content_status'] === 'visible';

        $this->atomically(function () use ($case, $actorId, $note, $hide, $warning): void {
            if ($hide) {
                $this->actions->hideContent($case, $actorId, $note);
            }

            if ($warning !== null) {
                $this->actions->warnAuthor($case, $actorId, $warning, $this->categoryLabel($case));
            }

            $this->reports->settleCase((int) $case['id'], 'upheld', $actorId);
            $this->close($case, 'upheld', $actorId, $note, ['hidden' => $hide, 'warned' => $warning !== null]);
        });
    }

    /**
     * Uphold the case by suspending its author through the regular cascade.
     *
     * @param  array<string, mixed>  $case
     * @param  string|null  $expiresAt  UTC end, or null for permanent
     * @return array{blogs: int, comments: int} What the suspension hid
     *
     * @throws RuntimeException When the case is closed or the suspension fails
     */
    public function suspendAuthor(array $case, int $actorId, ?string $expiresAt, string $reason): array
    {
        $this->assertOpen($case);

        return $this->atomically(function () use ($case, $actorId, $expiresAt, $reason): array {
            $hidden = $this->actions->suspendAuthor($case, $expiresAt, $actorId, $reason);

            $this->reports->settleCase((int) $case['id'], 'upheld', $actorId);
            $this->close($case, 'upheld', $actorId, $reason, ['suspended' => true, 'expires_at' => $expiresAt]);

            return $hidden;
        });
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $details
     */
    private function close(array $case, string $resolution, int $actorId, string $note, array $details): void
    {
        if (!$this->cases->resolve((int) $case['id'], $resolution, $actorId, $note)) {
            throw new RuntimeException('Someone else closed this case a moment ago. Nothing was changed.');
        }

        $this->reports->syncSubjectBadge((string) $case['subject_type'], (int) $case['subject_id']);
        $this->actions->record($case, "moderation.case_{$resolution}", $actorId, null, ['note' => $note] + $details);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function assertOpen(array $case): void
    {
        if ($case['status'] === 'resolved') {
            throw new RuntimeException('This case is already closed.');
        }
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function categoryLabel(array $case): string
    {
        $slug = (string) ($case['top_category'] ?? '');
        $category = $slug === '' ? null : $this->categories->findBySlug($slug);

        return $category === null ? 'a rules violation' : (string) $category['label'];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    /**
     * @param  int[]  $unfoundedIds
     */
    private function warnUnfoundedReporters(int $caseId, array $unfoundedIds): void
    {
        $unfoundedIds = array_map('intval', $unfoundedIds);
        $reporterIds = [];

        foreach ($this->reports->forCase($caseId) as $report) {
            if (in_array((int) $report['id'], $unfoundedIds, true) && $report['reporter_id'] !== null) {
                $reporterIds[(int) $report['reporter_id']] = true;
            }
        }

        foreach (array_keys($reporterIds) as $reporterId) {
            $this->standing->warnIfOverLimit($reporterId);
        }
    }

    private function atomically(callable $work): mixed
    {
        return $this->database->inTransaction() ? $work() : $this->database->transaction($work);
    }
}
