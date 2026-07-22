<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Data layer for recurring console tasks.
 *
 * A task is due when it is active, unclaimed and next_run_at has passed.
 * Claiming flips claim_token first and reads the rows back after, so two
 * dispatchers racing on the same tick cannot both take the same task. The
 * claim doubles as the overlap lock, which is why a task still running when
 * the next tick arrives is simply skipped rather than started twice.
 *
 * All scheduling columns are UTC and every comparison uses UTC_TIMESTAMP().
 * NOW() would follow the MySQL host zone while PHP runs in UTC.
 */
class ScheduledTaskModel extends AppModel
{
    protected ?string $table = 'scheduled_tasks';

    /**
     * Take ownership of up to $limit due tasks.
     *
     * @return array<int, array<string, mixed>> Claimed rows, most overdue first
     */
    public function claimDue(int $limit = 20): array
    {
        if ($limit < 1) {
            return [];
        }

        $claimToken = bin2hex(random_bytes(16));

        // $limit is interpolated because MySQL rejects a placeholder in LIMIT
        // on an UPDATE. The int hint plus the guard above is what keeps that
        // safe, so never widen this parameter to a string.
        $claimed = $this->database->execute(
            "UPDATE {$this->getTable()}
             SET claim_token = ?, claimed_at = UTC_TIMESTAMP()
             WHERE is_active = 1
               AND claim_token IS NULL
               AND next_run_at IS NOT NULL
               AND next_run_at <= UTC_TIMESTAMP()
             ORDER BY next_run_at, id
             LIMIT {$limit}",
            [$claimToken]
        );

        if ($claimed === 0) {
            return [];
        }

        return $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE claim_token = ? ORDER BY next_run_at, id",
            [$claimToken]
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Claim one specific task, for the Run Now button.
     *
     * Returns null when the task is already claimed, which is how a double
     * click or two admins pressing at once gets refused rather than queued.
     *
     * @return array<string, mixed>|null The claimed row
     */
    public function claimOne(int $id): ?array
    {
        $claimToken = bin2hex(random_bytes(16));

        $claimed = $this->database->execute(
            "UPDATE {$this->getTable()}
             SET claim_token = ?, claimed_at = UTC_TIMESTAMP()
             WHERE id = ? AND claim_token IS NULL",
            [$claimToken, $id]
        );

        if ($claimed === 0) {
            return null;
        }

        $row = $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Note which run row a claim belongs to.
     */
    public function attachRun(int $id, int $runId): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()} SET current_run_id = ? WHERE id = ?",
            [$runId, $id]
        ) > 0;
    }

    /**
     * Drop a claim without counting it as a run.
     *
     * Used when the dispatcher runs out of budget before reaching a task, or
     * when spawning failed. next_run_at is left alone on purpose so the task
     * is simply due again on the next tick.
     */
    public function releaseClaim(int $id): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET claim_token = NULL, claimed_at = NULL, current_run_id = NULL
             WHERE id = ?",
            [$id]
        ) > 0;
    }

    /**
     * Close out a finished run and line up the next one.
     *
     * @param  string|null  $nextRunAt  UTC 'Y-m-d H:i:s', or null to stop scheduling
     */
    public function completeRun(int $id, string $status, ?int $durationMs, ?string $nextRunAt): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET claim_token = NULL, claimed_at = NULL, current_run_id = NULL,
                 last_run_at = UTC_TIMESTAMP(), last_status = ?, last_duration_ms = ?,
                 next_run_at = ?
             WHERE id = ?",
            [$status, $durationMs, $nextRunAt, $id]
        ) > 0;
    }

    /**
     * Tasks whose claim has outlived their own timeout.
     *
     * Nobody supervises a detached child, so this is how a hung or killed run
     * gets noticed. The grace margin covers the gap between claiming a task
     * and the child actually starting.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAbandoned(int $graceSeconds = 30): array
    {
        return $this->database->query(
            "SELECT * FROM {$this->getTable()}
             WHERE claim_token IS NOT NULL
               AND claimed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL (timeout_seconds + ?) SECOND)",
            [$graceSeconds]
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Every task with its open run, for the panel listing.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithStatus(): array
    {
        return $this->database->query(
            "SELECT t.*, r.started_at AS running_since, r.pid AS running_pid
             FROM {$this->getTable()} t
             LEFT JOIN scheduled_task_runs r ON r.id = t.current_run_id AND r.status = 'running'
             ORDER BY t.is_active DESC, t.label"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * One task by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE id = ?",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Command names that currently have an active task.
     *
     * Lets the panel warn when work is piling up with nothing scheduled to
     * clear it, which otherwise looks exactly like the feature being broken.
     *
     * @return array<int, string>
     */
    public function activeCommands(): array
    {
        return $this->database->query(
            "SELECT DISTINCT command FROM {$this->getTable()} WHERE is_active = 1"
        )->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Save a new task.
     *
     * @param  array<string, mixed>  $data  Already validated by the controller
     * @return int The new task ID
     */
    public function createTask(array $data): int
    {
        $this->database->query(
            "INSERT INTO {$this->getTable()}
                (label, command, arguments, schedule_type, interval_minutes, minute_of_hour,
                 run_at, schedule_timezone, timeout_seconds, is_active, next_run_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['label'],
                $data['command'],
                $data['arguments'],
                $data['schedule_type'],
                $data['interval_minutes'],
                $data['minute_of_hour'],
                $data['run_at'],
                $data['schedule_timezone'],
                $data['timeout_seconds'],
                $data['is_active'],
                $data['next_run_at'],
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * Update an existing task.
     *
     * A claimed task keeps its claim through an edit. The run in flight
     * finishes against the old settings and the new ones take over from the
     * next tick.
     *
     * @param  array<string, mixed>  $data  Already validated by the controller
     */
    public function updateTask(int $id, array $data): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET label = ?, command = ?, arguments = ?, schedule_type = ?,
                 interval_minutes = ?, minute_of_hour = ?, run_at = ?,
                 schedule_timezone = ?, timeout_seconds = ?, is_active = ?, next_run_at = ?
             WHERE id = ?",
            [
                $data['label'],
                $data['command'],
                $data['arguments'],
                $data['schedule_type'],
                $data['interval_minutes'],
                $data['minute_of_hour'],
                $data['run_at'],
                $data['schedule_timezone'],
                $data['timeout_seconds'],
                $data['is_active'],
                $data['next_run_at'],
                $id,
            ]
        ) > 0;
    }

    /**
     * Remove a task and its history.
     */
    public function deleteTask(int $id): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE id = ?",
            [$id]
        ) > 0;
    }
}
