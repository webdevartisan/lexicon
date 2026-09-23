<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ReportRejectedException;
use App\Models\ContentReportModel;
use App\Models\ModerationCaseModel;
use App\Models\ModerationCategoryModel;
use Framework\Database;
use InvalidArgumentException;

/**
 * Files a reader's report: checks it, joins it to the item's case, and hands
 * the case to the rule engine.
 *
 * Everything the client sends is checked again here. The category must be a
 * live one, the item must exist, and whether the report counts toward an
 * automatic action is decided from the reporter's own record, never from
 * anything in the request.
 */
class ReportIntakeService
{
    public const DETAILS_MAX = 1000;

    /** Reports from reporters in good standing before admins hear about a case. */
    private const ADMIN_ALERT_REPORT_THRESHOLD = 3;

    public function __construct(
        private Database $database,
        private ModerationCategoryModel $categories,
        private ModerationCaseModel $cases,
        private ContentReportModel $reports,
        private ModerationSettings $settings,
        private ModerationRuleEngine $rules,
        private AdminNotificationDispatcher $adminNotifier,
        private string $deletedUserHandle,
    ) {}

    /**
     * @param  string  $subjectType  'post' or 'comment'
     * @return array{recorded: bool, case_id: int|null, category: string}
     *
     * @throws ReportRejectedException With a message for the reader
     */
    public function file(int $reporterId, string $subjectType, int $subjectId, string $category, ?string $details): array
    {
        $category = $this->validCategory($category);
        $details = $this->cleanDetails($details);
        $subject = $this->loadSubject($subjectType, $subjectId);
        $reporter = $this->loadReporter($reporterId);

        if ($this->reports->hasReported($reporterId, $subjectType, $subjectId)) {
            return ['recorded' => false, 'case_id' => null, 'category' => $category];
        }

        $caseId = $this->database->transaction(function () use ($reporterId, $subjectType, $subjectId, $category, $details, $subject, $reporter): ?int {
            $caseId = $this->cases->openFor(
                $subjectType,
                $subjectId,
                $subject['author_id'],
                $subject['author_handle'],
                $subject['blog_id'],
                $subject['snapshot']
            );

            $recorded = $this->reports->record([
                'case_id' => $caseId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'reporter_id' => $reporterId,
                'reporter_handle' => $reporter['handle'],
                'category' => $category,
                'details' => $details,
                'counts_toward_threshold' => $reporter['not_counted_reason'] === null,
                'not_counted_reason' => $reporter['not_counted_reason'],
            ]);

            // A second submit racing the first passed the check above; the
            // unique key caught it, so undo the case it may have opened.
            if (!$recorded) {
                $this->cases->discardIfEmpty($caseId);

                return null;
            }

            $this->cases->recount($caseId);
            $this->reports->syncSubjectBadge($subjectType, $subjectId);

            return $caseId;
        });

        if ($caseId === null) {
            return ['recorded' => false, 'case_id' => null, 'category' => $category];
        }

        $this->rules->evaluate($caseId);
        $this->maybeNotifyAdmins($caseId);

        return ['recorded' => true, 'case_id' => $caseId, 'category' => $category];
    }

    /**
     * Tell admins once a case has accumulated enough standing reports to be
     * worth a look, instead of relying on someone happening to check the queue.
     */
    private function maybeNotifyAdmins(int $caseId): void
    {
        $case = $this->cases->findById($caseId);

        if ($case === null || (int) ($case['counted_report_count'] ?? 0) < self::ADMIN_ALERT_REPORT_THRESHOLD) {
            return;
        }

        $this->adminNotifier->dispatch(
            'admin.report_threshold',
            'handle_reports',
            [
                'case_id' => $caseId,
                'report_count' => (int) $case['counted_report_count'],
                'top_category' => $case['top_category'] ?? null,
            ],
            60,
            (string) $caseId
        );
    }

    /**
     * @throws ReportRejectedException
     */
    private function validCategory(string $slug): string
    {
        $category = $this->categories->findActive(trim($slug));

        if ($category === null) {
            throw new ReportRejectedException('Choose a reason from the list.');
        }

        return (string) $category['slug'];
    }

    /**
     * @throws ReportRejectedException
     */
    private function cleanDetails(?string $details): ?string
    {
        if ($details === null) {
            return null;
        }

        // Control characters have no business in a note to a moderator; line
        // breaks stay so a reader can lay out what happened.
        $details = trim((string) preg_replace('/[^\P{C}\n]/u', '', $details));

        if ($details === '') {
            return null;
        }

        if (mb_strlen($details) > self::DETAILS_MAX) {
            throw new ReportRejectedException('Keep the details under 1,000 characters.');
        }

        return $details;
    }

    /**
     * What the case needs to know about the item, read from the database
     * rather than taken from the request.
     *
     * @return array{author_id: int|null, author_handle: string|null, blog_id: int|null, snapshot: string|null}
     *
     * @throws ReportRejectedException When the item does not exist
     */
    private function loadSubject(string $subjectType, int $subjectId): array
    {
        $sql = match ($subjectType) {
            'post' => 'SELECT u.id AS author_id, u.handle AS author_handle, p.blog_id, p.title AS snapshot
                          FROM posts p
                          LEFT JOIN users u ON u.id = p.author_id AND u.handle <> ?
                         WHERE p.id = ?',
            'comment' => 'SELECT u.id AS author_id, u.handle AS author_handle, p.blog_id, LEFT(c.content, 500) AS snapshot
                             FROM comments c
                             JOIN posts p ON p.id = c.post_id
                             LEFT JOIN users u ON u.id = c.user_id AND u.handle <> ?
                            WHERE c.id = ?',
            default => throw new InvalidArgumentException("Unknown report subject type '{$subjectType}'."),
        };

        // Erased accounts hand their content to one shared account. It is nobody to warn or
        // suspend, and suspending it would hide every erased person's comments at once.
        $row = $this->database->query($sql, [$this->deletedUserHandle, $subjectId])->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new ReportRejectedException("That {$subjectType} is not available.");
        }

        return [
            'author_id' => $row['author_id'] === null ? null : (int) $row['author_id'],
            'author_handle' => $row['author_handle'],
            'blog_id' => $row['blog_id'] === null ? null : (int) $row['blog_id'],
            'snapshot' => $row['snapshot'],
        ];
    }

    /**
     * The reporter's handle, and why their report will not count toward an
     * automatic action (null when it will).
     *
     * A new account or a record of unfounded reports does not stop anyone
     * reporting; it only keeps their report from tipping a rule on its own.
     *
     * @return array{handle: string|null, not_counted_reason: string|null}
     *
     * @throws ReportRejectedException When the account is gone, or a moderator paused its reporting
     */
    private function loadReporter(int $reporterId): array
    {
        $row = $this->database->query(
            'SELECT handle, created_at <= NOW() - INTERVAL ? DAY AS seasoned,
                    reports_paused_until, reports_paused_until > NOW() AS paused
               FROM users
              WHERE id = ? AND deleted_at IS NULL',
            [$this->settings->minReporterAgeDays(), $reporterId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new ReportRejectedException('Sign in again to report this.');
        }

        if ((bool) $row['paused']) {
            throw new ReportRejectedException(
                'A moderator has paused your reports until '.local_datetime((string) $row['reports_paused_until'], 'M j, Y')
                .', because several of your earlier reports were found to be unfounded.'
            );
        }

        $reason = match (true) {
            !(bool) $row['seasoned'] => 'new_account',
            $this->reports->unfoundedCountWithin($reporterId, $this->settings->unfoundedWindowDays())
                >= $this->settings->unfoundedLimit() => 'unfounded_history',
            default => null,
        };

        return ['handle' => $row['handle'], 'not_counted_reason' => $reason];
    }
}
