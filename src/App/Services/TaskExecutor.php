<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ScheduledTaskModel;
use App\Models\ScheduledTaskRunModel;
use Framework\Core\Container;
use Throwable;

/**
 * Runs one already claimed task and writes down what happened.
 *
 * This is the single place a scheduled command is actually executed. The
 * detached child reaches it through schedule:run-task and the inline fallback
 * calls it directly, so both paths behave identically and there is only one
 * version of the recording logic to get right.
 *
 * The command name and its arguments are checked here rather than trusted from
 * the row. A task saved before a command was renamed, or one whose arguments no
 * longer fit the schema, is refused and recorded as a failure with the reason
 * attached. Catching it at the form alone would leave those rows executing on
 * whatever the code happens to mean today.
 */
class TaskExecutor
{
    /** Set while a task is running so the shutdown handler knows what to close. */
    private ?int $openRunId = null;

    public function __construct(
        private Container $container,
        private ScheduledTaskModel $tasks,
        private ScheduledTaskRunModel $runs,
        private ScheduleRegistry $registry,
        private ScheduleCalculator $calculator,
    ) {}

    /**
     * Execute a claimed task against an open run row.
     *
     * @param  int  $taskId  Row in scheduled_tasks
     * @param  int  $runId  Open row in scheduled_task_runs
     * @return int Exit code from the command, or 1 when it never got to run
     */
    public function execute(int $taskId, int $runId): int
    {
        $task = $this->tasks->findById($taskId);

        if ($task === null) {
            $this->runs->finish($runId, 'failed', 1, 'Task no longer exists.');

            return 1;
        }

        // Recorded by the process doing the work rather than the one that
        // started it, so the reaper can find a detached child through the
        // shell that let go of it.
        $this->runs->attachPid($runId, (int) getmypid());

        $command = (string) $task['command'];

        try {
            $arguments = $this->argumentsFor($task);
            $instance = $this->container->get($this->registry->classFor($command));
        } catch (Throwable $e) {
            return $this->close($task, $runId, 'failed', 1, $e->getMessage());
        }

        $this->guardAgainstFatal($task, $runId);

        ob_start();

        try {
            $exitCode = (int) $instance->handle($arguments);
            $output = (string) ob_get_clean();
        } catch (Throwable $e) {
            $output = (string) ob_get_clean();
            $output .= "\n".get_class($e).': '.$e->getMessage();

            return $this->close($task, $runId, 'failed', 1, $output);
        }

        $this->openRunId = null;

        return $this->close($task, $runId, $exitCode === 0 ? 'success' : 'failed', $exitCode, $output);
    }

    /**
     * Decode and re-check the arguments stored on a task.
     *
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function argumentsFor(array $task): array
    {
        $raw = $task['arguments'] ?? null;
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

        return $this->registry->validate((string) $task['command'], is_array($decoded) ? $decoded : []);
    }

    /**
     * Close the run and line up the next one.
     *
     * @param  array<string, mixed>  $task
     * @return int The exit code, passed straight back out
     */
    private function close(array $task, int $runId, string $status, int $exitCode, string $output): int
    {
        $this->openRunId = null;

        $this->runs->finish($runId, $status, $exitCode, $output);

        $this->tasks->completeRun(
            (int) $task['id'],
            $status,
            null,
            $this->calculator->nextRunAt($task)
        );

        return $exitCode;
    }

    /**
     * Make sure a fatal error still closes the run.
     *
     * Without this a task that runs out of memory leaves its row marked
     * running for ever and the panel shows a spinner that never stops. The
     * reaper would eventually clear it, but recording the real reason here is
     * far more use to whoever has to work out what went wrong.
     *
     * @param  array<string, mixed>  $task
     */
    private function guardAgainstFatal(array $task, int $runId): void
    {
        $this->openRunId = $runId;

        register_shutdown_function(function () use ($task) {
            if ($this->openRunId === null) {
                return;
            }

            $error = error_get_last();
            $reason = $error['message'] ?? 'The process stopped without reporting a result.';

            $buffered = ob_get_length() !== false ? (string) ob_get_clean() : '';

            $this->runs->finish($this->openRunId, 'failed', 255, $buffered."\n".$reason);

            $this->tasks->completeRun(
                (int) $task['id'],
                'failed',
                null,
                $this->calculator->nextRunAt($task)
            );

            $this->openRunId = null;
        });
    }
}
