<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Shared behaviour for reader reports on a piece of content.
 *
 * Reporting is a signal, not a verdict: nothing is hidden automatically. The
 * count surfaces to the blog team so a human decides. Capping it at one row per
 * reporter is what stops a single annoyed reader manufacturing a pile-on.
 *
 * Comments and posts keep separate tables rather than sharing a polymorphic
 * one, because a real foreign key is what guarantees reports disappear with the
 * thing they were about.
 */
abstract class ReportModel extends AppModel
{
    public const REASONS = ['spam', 'harassment', 'hate', 'misinformation', 'other'];

    /**
     * Column on this report table pointing at the reported row.
     */
    abstract protected function subjectColumn(): string;

    /**
     * Table holding the reported rows; it carries the `reports_count` mirror.
     */
    abstract protected function subjectTable(): string;

    /**
     * File a report, or do nothing if this person already reported it.
     *
     * @param  int  $userId  Reporter
     * @param  int  $subjectId  Comment or post being reported
     * @param  string  $reason  One of self::REASONS; anything else becomes 'other'
     * @return bool True when a new report was recorded
     */
    public function report(int $userId, int $subjectId, string $reason): bool
    {
        if (!in_array($reason, self::REASONS, true)) {
            $reason = 'other';
        }

        return (bool) $this->transaction(function () use ($userId, $subjectId, $reason): bool {
            $column = $this->subjectColumn();

            // IGNORE rather than an existence check: the unique key already
            // says "once per person", so let it enforce that and report back.
            $affected = $this->database->execute(
                "INSERT IGNORE INTO {$this->getTable()} ({$column}, user_id, reason) VALUES (?, ?, ?)",
                [$subjectId, $userId, $reason]
            );

            if ($affected > 0) {
                $this->database->execute(
                    "UPDATE {$this->subjectTable()} s
                     SET s.reports_count = (SELECT COUNT(*) FROM {$this->getTable()} WHERE {$column} = s.id)
                     WHERE s.id = ?",
                    [$subjectId]
                );
            }

            return $affected > 0;
        });
    }

    /**
     * Whether this person already reported this item.
     */
    public function hasReported(int $userId, int $subjectId): bool
    {
        $sql = "SELECT 1 FROM {$this->getTable()} WHERE user_id = ? AND {$this->subjectColumn()} = ? LIMIT 1";

        return (bool) $this->database->query($sql, [$userId, $subjectId])->fetchColumn();
    }

    /**
     * Reasons given, most common first, for the moderation queue.
     *
     * @return array<int, array{reason: string, total: int}>
     */
    public function reasonsFor(int $subjectId): array
    {
        $sql = "SELECT reason, COUNT(*) AS total
                FROM {$this->getTable()}
                WHERE {$this->subjectColumn()} = ?
                GROUP BY reason
                ORDER BY total DESC";

        $rows = $this->database->query($sql, [$subjectId])->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $r): array => ['reason' => (string) $r['reason'], 'total' => (int) $r['total']],
            $rows
        );
    }

    /**
     * Drop every report once a moderator has ruled the content fine.
     *
     * Leaving them would keep the queue arguing with a decision it just made.
     */
    public function clearFor(int $subjectId): void
    {
        $this->transaction(function () use ($subjectId): void {
            $this->database->execute(
                "DELETE FROM {$this->getTable()} WHERE {$this->subjectColumn()} = ?",
                [$subjectId]
            );
            $this->database->execute(
                "UPDATE {$this->subjectTable()} SET reports_count = 0 WHERE id = ?",
                [$subjectId]
            );
        });
    }
}
