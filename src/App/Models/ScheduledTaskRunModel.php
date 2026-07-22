<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Execution history for scheduled tasks.
 *
 * A row opens as 'running' the moment a task is claimed, so the list view can
 * show something turning before the child process has even booted, and closes
 * when the child reports back or the reaper gives up on it.
 *
 * Every timestamp here is UTC written with UTC_TIMESTAMP(). The connection
 * does not pin a session zone, so NOW() would follow the MySQL host while PHP
 * runs in UTC.
 */
class ScheduledTaskRunModel extends AppModel
{
    protected ?string $table = 'scheduled_task_runs';

    /** Captured output is for reading, not archiving, so it gets a ceiling. */
    private const MAX_OUTPUT = 65535;

    /**
     * Open a run row for a task that was just claimed.
     *
     * @param  string  $trigger  'cron', 'manual' or 'pseudo'
     * @return int The new run ID
     */
    public function start(int $taskId, string $trigger = 'cron'): int
    {
        $this->database->query(
            "INSERT INTO {$this->getTable()} (task_id, trigger_source, status, started_at)
             VALUES (?, ?, 'running', UTC_TIMESTAMP(3))",
            [$taskId, $trigger]
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * Record the child process id once it is known.
     */
    public function attachPid(int $runId, int $pid): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()} SET pid = ? WHERE id = ?",
            [$pid, $runId]
        ) > 0;
    }

    /**
     * Close a run row with its outcome.
     *
     * duration_ms is worked out in SQL from started_at so a detached child
     * never has to agree with the dispatcher about what time it is.
     *
     * @param  string  $status  'success', 'failed' or 'timed_out'
     */
    public function finish(int $runId, string $status, ?int $exitCode, string $output = ''): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = ?, exit_code = ?, output = ?,
                 finished_at = UTC_TIMESTAMP(3),
                 duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, UTC_TIMESTAMP(3)) / 1000
             WHERE id = ? AND status = 'running'",
            [$status, $exitCode, $this->trim($output), $runId]
        ) > 0;
    }

    /**
     * Write a run that never got off the ground.
     *
     * Used when a spawn fails or a manual trigger hits a task that is already
     * running. Both are things the operator needs told about, and a closed row
     * says it better than a flash message that disappears on the next click.
     */
    public function record(int $taskId, string $trigger, string $status, string $output): int
    {
        $this->database->query(
            "INSERT INTO {$this->getTable()}
                (task_id, trigger_source, status, exit_code, output, started_at, finished_at, duration_ms)
             VALUES (?, ?, ?, NULL, ?, UTC_TIMESTAMP(3), UTC_TIMESTAMP(3), 0)",
            [$taskId, $trigger, $status, $this->trim($output)]
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * Runs still marked running whose task lost its claim.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openRunsForTask(int $taskId): array
    {
        return $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE task_id = ? AND status = 'running' ORDER BY id",
            [$taskId]
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * History for one task, newest first.
     *
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function historyFor(int $taskId, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} WHERE task_id = ?",
            [$taskId]
        )->fetchColumn();

        $rows = $this->database->query(
            "SELECT * FROM {$this->getTable()}
             WHERE task_id = :task
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset",
            [':task' => $taskId, ':limit' => $perPage, ':offset' => ($page - 1) * $perPage]
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $totalPages = (int) ceil($total / $perPage);

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * Drop history past the retention window.
     *
     * Running rows are spared no matter how old. One that stale means a child
     * vanished without reporting, and deleting it would hide the problem.
     *
     * @return int Rows deleted
     */
    public function prune(int $days = 30): int
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()}
             WHERE status <> 'running' AND started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)",
            [$days]
        );
    }

    /**
     * Keep captured output inside the column, with a visible marker.
     */
    private function trim(string $output): string
    {
        if (strlen($output) <= self::MAX_OUTPUT) {
            return $output;
        }

        $marker = "\n... output truncated ...\n";

        return substr($output, 0, self::MAX_OUTPUT - strlen($marker)).$marker;
    }
}
