<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TaskExecutor;
use Throwable;

/**
 * Runs a single claimed task and reports back.
 *
 * This is what a detached child actually starts. It is deliberately not
 * schedulable, so it never turns up in the panel dropdown and cannot be
 * pointed at itself.
 *
 * Both ids are required. The task has already been claimed and its run row
 * already opened by whoever spawned this, so running it by hand with made up
 * numbers does nothing useful.
 *
 * Usage: php cli schedule:run-task <taskId> <runId>
 */
class ScheduleRunTaskCommand
{
    public function __construct(
        private TaskExecutor $executor,
    ) {}

    /**
     * Execute the given task.
     *
     * @param  array<int|string, string>  $arguments  Task id then run id
     * @return int Exit code from the task itself
     */
    public function handle(array $arguments = []): int
    {
        $taskId = (int) ($arguments[0] ?? 0);
        $runId = (int) ($arguments[1] ?? 0);

        if ($taskId < 1 || $runId < 1) {
            echo "Usage: php cli schedule:run-task <taskId> <runId>\n";

            return 1;
        }

        try {
            return $this->executor->execute($taskId, $runId);
        } catch (Throwable $e) {
            // TaskExecutor records its own failures, so anything reaching here
            // broke before the run row could be touched. The reaper picks the
            // row up once the claim goes stale.
            echo "Error executing task {$taskId}: {$e->getMessage()}\n";

            return 1;
        }
    }
}
