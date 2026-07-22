<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\TaskRunnerInterface;
use App\Models\ScheduledTaskModel;
use App\Models\ScheduledTaskRunModel;
use App\Models\SettingModel;

/**
 * Decides what runs on a tick and hands it to a runner.
 *
 * Cron calls one command, schedule:run, and this works out the rest. Tasks are
 * claimed with a write, so two ticks landing on top of each other cannot both
 * pick up the same job, and the claim stays held for as long as the task is
 * running. That is also the overlap guard: a task still going when the next
 * minute comes round is simply passed over instead of started a second time.
 *
 * A tick stops handing out work once it has used its budget. Whatever it did
 * not reach keeps its place and comes up again straight away, which is much
 * safer than letting one busy minute run into the next.
 *
 * Nothing here executes a command. That belongs to TaskExecutor, reached
 * either through a detached child or, on hosts that block process control,
 * directly through InlineRunner.
 */
class ScheduleService
{
    /** Where the heartbeat is kept, read by the panel to spot a dead crontab. */
    private const HEARTBEAT_KEY = 'scheduler_last_tick';

    /**
     * @param  array<string, mixed>  $config  config/schedule.php
     */
    public function __construct(
        private ScheduledTaskModel $tasks,
        private ScheduledTaskRunModel $runs,
        private ScheduleCalculator $calculator,
        private TaskRunnerInterface $runner,
        private SettingModel $settings,
        private array $config = [],
    ) {}

    /**
     * Run one tick.
     *
     * @param  string  $trigger  'cron' or 'pseudo'
     * @return array{reaped: int, started: int, failed: int, deferred: int}
     */
    public function tick(string $trigger = 'cron'): array
    {
        // Written first, and on every tick including empty ones. Leaving it to
        // the end would mean a task taking the process down looked exactly like
        // cron never firing, and a quiet scheduler looked exactly like a dead
        // one.
        $this->touchHeartbeat();

        $result = ['reaped' => $this->reapAbandoned(), 'started' => 0, 'failed' => 0, 'deferred' => 0];

        $deadline = microtime(true) + (float) ($this->config['run_budget_seconds'] ?? 50);
        $claimed = $this->tasks->claimDue((int) ($this->config['max_tasks_per_tick'] ?? 20));

        foreach ($claimed as $task) {
            $taskId = (int) $task['id'];

            // Out of budget. Drop the claim without advancing next_run_at so
            // the task is simply due again on the next tick.
            if (microtime(true) >= $deadline) {
                $this->tasks->releaseClaim($taskId);
                $result['deferred']++;

                continue;
            }

            if ($this->start($task, $trigger)) {
                $result['started']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Start a claimed task and open its run row.
     *
     * @param  array<string, mixed>  $task
     * @return bool Whether the task got going
     */
    private function start(array $task, string $trigger): bool
    {
        $taskId = (int) $task['id'];

        $runId = $this->runs->start($taskId, $trigger);
        $this->tasks->attachRun($taskId, $runId);

        if ($this->runner->dispatch($taskId, $runId)) {
            return true;
        }

        // Nothing is going to report back, so close it here. This is what a
        // missing or misconfigured PHP binary looks like, and it needs to be
        // visible rather than a task that silently never runs.
        $this->runs->finish($runId, 'failed', 1, 'The task could not be started. Check the scheduler PHP binary setting.');
        $this->tasks->completeRun($taskId, 'failed', null, $this->calculator->nextRunAt($task));

        return false;
    }

    /**
     * Stop and write off tasks whose claim has outlived their timeout.
     *
     * Nobody supervises a detached child, so this is the only thing standing
     * between a hung task and a schedule that never moves again.
     *
     * @return int Tasks reaped
     */
    public function reapAbandoned(): int
    {
        $grace = (int) ($this->config['reap_grace_seconds'] ?? 30);
        $reaped = 0;

        foreach ($this->tasks->findAbandoned($grace) as $task) {
            $taskId = (int) $task['id'];
            $timeout = (int) $task['timeout_seconds'];

            foreach ($this->runs->openRunsForTask($taskId) as $run) {
                if (!empty($run['pid'])) {
                    $this->runner->kill((int) $run['pid']);
                }

                $this->runs->finish(
                    (int) $run['id'],
                    'timed_out',
                    null,
                    "Stopped after running longer than its {$timeout} second timeout."
                );
            }

            $this->tasks->completeRun($taskId, 'timed_out', null, $this->calculator->nextRunAt($task));
            $reaped++;
        }

        return $reaped;
    }

    /**
     * Start one task by hand from the panel.
     *
     * Returns rather than throws, because every outcome here is something the
     * operator needs told about in plain words.
     *
     * @return array{ok: bool, message: string}
     */
    public function runNow(int $taskId): array
    {
        $task = $this->tasks->findById($taskId);

        if ($task === null) {
            return ['ok' => false, 'message' => 'That task no longer exists.'];
        }

        $claimed = $this->tasks->claimOne($taskId);

        if ($claimed === null) {
            // Refused rather than queued. Two admins pressing at once, or a
            // double click, should not start the same job twice.
            $this->runs->record($taskId, 'manual', 'skipped_locked', 'Skipped because the task was already running.');

            return ['ok' => false, 'message' => 'That task is already running, so it was left alone.'];
        }

        if ($this->start($claimed, 'manual')) {
            return $this->runner->isDetached()
                ? ['ok' => true, 'message' => 'Started in the background. Output appears here when it finishes.']
                : ['ok' => true, 'message' => 'Finished. See the run history below for the output.'];
        }

        return ['ok' => false, 'message' => 'The task could not be started. Check the scheduler PHP binary setting.'];
    }

    /**
     * Seconds since the last tick, or null when it has never run.
     */
    public function heartbeatAge(): ?int
    {
        $last = $this->settings->get(self::HEARTBEAT_KEY);

        if ($last === null || $last === '') {
            return null;
        }

        return max(0, time() - (int) strtotime($last.' UTC'));
    }

    /**
     * Whether the panel should be warning that cron looks dead.
     */
    public function heartbeatIsStale(): bool
    {
        $age = $this->heartbeatAge();

        return $age !== null && $age > (int) ($this->config['heartbeat_warn_after'] ?? 300);
    }

    /**
     * Whether tasks run as separate processes on this host.
     */
    public function runsDetached(): bool
    {
        return $this->runner->isDetached();
    }

    /**
     * Record that a tick happened.
     */
    private function touchHeartbeat(): void
    {
        $this->settings->set(self::HEARTBEAT_KEY, gmdate(ScheduleCalculator::FORMAT));
    }
}
