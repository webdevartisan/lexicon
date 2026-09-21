<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

/**
 * Reader reports on posts and comments, one per person per item.
 *
 * Every report belongs to a moderation case. The rows have no foreign key to
 * the item they are about, so they survive its deletion: the case history
 * stays readable, and a report later ruled unfounded still counts against the
 * person who filed it.
 */
class ContentReportModel extends AppModel
{
    protected ?string $table = 'content_reports';

    public const OUTCOMES = ['pending', 'upheld', 'dismissed', 'unfounded'];

    /** Report subject type => the table carrying its reports_count badge. */
    private const SUBJECT_TABLES = ['post' => 'posts', 'comment' => 'comments'];

    /**
     * File a report, or do nothing if this person already reported the item.
     *
     * @param  array{case_id: int, subject_type: string, subject_id: int, reporter_id: int, reporter_handle: ?string, category: string, details: ?string, counts_toward_threshold: bool, not_counted_reason: ?string}  $report
     * @return bool True when a new row was written
     */
    public function record(array $report): bool
    {
        $this->subjectTable($report['subject_type']);

        // The unique key already means "once per person per item", so let it
        // decide rather than racing a separate existence check.
        $affected = $this->database->execute(
            "INSERT IGNORE INTO {$this->getTable()}
                (case_id, subject_type, subject_id, reporter_id, reporter_handle, category, details,
                 counts_toward_threshold, not_counted_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $report['case_id'],
                $report['subject_type'],
                $report['subject_id'],
                $report['reporter_id'],
                $report['reporter_handle'],
                $report['category'],
                $report['details'],
                $report['counts_toward_threshold'] ? 1 : 0,
                $report['not_counted_reason'],
            ]
        );

        return $affected > 0;
    }

    /**
     * Whether this person has ever reported this item, in any case.
     */
    public function hasReported(int $reporterId, string $subjectType, int $subjectId): bool
    {
        $sql = "SELECT 1 FROM {$this->getTable()}
                 WHERE reporter_id = ? AND subject_type = ? AND subject_id = ? LIMIT 1";

        return (bool) $this->database->query($sql, [$reporterId, $subjectType, $subjectId])->fetchColumn();
    }

    /**
     * Counted, still-pending reports on a case, grouped by category.
     *
     * span_seconds is the time between the first and last of them, which is
     * what the burst guard reads.
     *
     * @return array<int, array{category: string, counted: int, span_seconds: int}>
     */
    public function countedByCategory(int $caseId): array
    {
        $rows = $this->database->query(
            "SELECT category,
                    COUNT(*) AS counted,
                    TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) AS span_seconds
               FROM {$this->getTable()}
              WHERE case_id = ? AND counts_toward_threshold = 1 AND outcome = 'pending'
              GROUP BY category",
            [$caseId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'category' => (string) $row['category'],
            'counted' => (int) $row['counted'],
            'span_seconds' => (int) $row['span_seconds'],
        ], $rows);
    }

    /**
     * How many of this person's reports were ruled unfounded recently.
     */
    public function unfoundedCountWithin(int $reporterId, int $days): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()}
                 WHERE reporter_id = ? AND outcome = 'unfounded'
                   AND outcome_at >= NOW() - INTERVAL ? DAY";

        return (int) $this->database->query($sql, [$reporterId, $days])->fetchColumn();
    }

    /**
     * Recount the Reported badge the blog team sees on the item itself.
     *
     * Recounted rather than incremented, so two reports landing together
     * cannot leave it off by one.
     */
    public function syncSubjectBadge(string $subjectType, int $subjectId): void
    {
        $table = $this->subjectTable($subjectType);

        $this->database->execute(
            "UPDATE {$table} s
                SET s.reports_count = (
                    SELECT COUNT(*) FROM {$this->getTable()} r
                     WHERE r.subject_type = ? AND r.subject_id = s.id
                       AND r.outcome = 'pending' AND r.blog_reviewed_at IS NULL
                )
              WHERE s.id = ?",
            [$subjectType, $subjectId]
        );
    }

    /**
     * The blog team looked at the item and kept it.
     *
     * That clears their badge but not the platform case: a blog owner cannot
     * be the one who settles a report about their own blog.
     */
    public function markBlogReviewed(string $subjectType, int $subjectId): void
    {
        $this->database->execute(
            "UPDATE {$this->getTable()}
                SET blog_reviewed_at = NOW()
              WHERE subject_type = ? AND subject_id = ? AND outcome = 'pending' AND blog_reviewed_at IS NULL",
            [$subjectType, $subjectId]
        );

        $this->syncSubjectBadge($subjectType, $subjectId);
    }

    /**
     * Reporters who reached the unfounded limit and are not paused, most
     * unfounded first. Same limit and window that stop their reports counting
     * toward the rules, so the queue can point a moderator at them.
     *
     * @return array{total: int, reporters: array<int, array<string, mixed>>}
     */
    public function reportersOverLimit(int $limit, int $days, int $show = 5): array
    {
        $rows = $this->database->query(
            "SELECT u.id, u.handle, COUNT(*) AS unfounded,
                    (SELECT MAX(a.created_at) FROM activity_log a
                      WHERE a.resource_type = 'user' AND a.resource_id = u.id
                        AND a.action = 'moderation.reporter_warned') AS warned_at
               FROM {$this->getTable()} r
               JOIN users u ON u.id = r.reporter_id
              WHERE r.outcome = 'unfounded' AND r.outcome_at >= NOW() - INTERVAL ? DAY
                AND (u.reports_paused_until IS NULL OR u.reports_paused_until <= NOW())
              GROUP BY u.id, u.handle
             HAVING COUNT(*) >= ?
              ORDER BY unfounded DESC, u.id",
            [$days, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return ['total' => count($rows), 'reporters' => array_slice($rows, 0, $show)];
    }

    /**
     * The reports on a case, oldest first, each with the reporter's own track
     * record so a moderator can weigh a report from someone who often files
     * unfounded ones.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCase(int $caseId): array
    {
        return $this->database->query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM {$this->getTable()} x WHERE x.reporter_id = r.reporter_id) AS reporter_filed,
                    (SELECT COUNT(*) FROM {$this->getTable()} x
                      WHERE x.reporter_id = r.reporter_id AND x.outcome = 'unfounded') AS reporter_unfounded
               FROM {$this->getTable()} r
              WHERE r.case_id = ?
              ORDER BY r.created_at, r.id",
            [$caseId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Record how a case came out on each of its pending reports.
     *
     * Reports named in $unfoundedIds are marked unfounded, which counts
     * against whoever filed them; the rest take $outcome.
     *
     * @param  string  $outcome  'upheld' or 'dismissed'
     * @param  int[]  $unfoundedIds  Report ids a moderator ruled unfounded; only honoured on a dismissal
     */
    public function settleCase(int $caseId, string $outcome, int $actorId, array $unfoundedIds = []): void
    {
        if (!in_array($outcome, ['upheld', 'dismissed'], true)) {
            throw new InvalidArgumentException("Unknown report outcome '{$outcome}'.");
        }

        $unfoundedIds = $outcome === 'dismissed' ? array_values(array_unique(array_map('intval', $unfoundedIds))) : [];

        if ($unfoundedIds !== []) {
            $marks = implode(', ', array_fill(0, count($unfoundedIds), '?'));
            $this->database->execute(
                "UPDATE {$this->getTable()}
                    SET outcome = 'unfounded', outcome_by = ?, outcome_at = NOW()
                  WHERE case_id = ? AND outcome = 'pending' AND id IN ({$marks})",
                [$actorId, $caseId, ...$unfoundedIds]
            );
        }

        $this->database->execute(
            "UPDATE {$this->getTable()}
                SET outcome = ?, outcome_by = ?, outcome_at = NOW()
              WHERE case_id = ? AND outcome = 'pending'",
            [$outcome, $actorId, $caseId]
        );
    }

    /**
     * Every report a person has filed, for their data export.
     *
     * @return array<int, array<string, mixed>>
     */
    public function filedBy(int $reporterId): array
    {
        return $this->database->query(
            "SELECT subject_type, subject_id, category, details, outcome, created_at
               FROM {$this->getTable()}
              WHERE reporter_id = ?
              ORDER BY created_at",
            [$reporterId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * How a person's reports have turned out, the figures DSA Art. 23(3) asks
     * a moderator to weigh: how many, and what share proved unfounded.
     *
     * @return array{filed: int, pending: int, upheld: int, dismissed: int, unfounded: int}
     */
    public function reporterSummary(int $reporterId): array
    {
        $summary = ['filed' => 0, 'pending' => 0, 'upheld' => 0, 'dismissed' => 0, 'unfounded' => 0];
        $rows = $this->database->query(
            "SELECT outcome, COUNT(*) AS total FROM {$this->getTable()} WHERE reporter_id = ? GROUP BY outcome",
            [$reporterId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $summary[(string) $row['outcome']] = (int) $row['total'];
            $summary['filed'] += (int) $row['total'];
        }

        return $summary;
    }

    /**
     * A person's most recent reports with the case each one joined.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reporterHistory(int $reporterId, int $limit = 50): array
    {
        return $this->database->query(
            "SELECT r.id, r.case_id, r.subject_type, r.subject_id, r.category, r.details, r.outcome,
                    r.counts_toward_threshold, r.not_counted_reason, r.created_at,
                    mc.status AS case_status, mc.subject_snapshot, mc.subject_author_handle
               FROM {$this->getTable()} r
               JOIN moderation_cases mc ON mc.id = r.case_id
              WHERE r.reporter_id = ?
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT ".max(1, min($limit, 200)),
            [$reporterId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function subjectTable(string $subjectType): string
    {
        return self::SUBJECT_TABLES[$subjectType]
            ?? throw new InvalidArgumentException("Unknown report subject type '{$subjectType}'.");
    }
}
