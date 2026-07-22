<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Starts a scheduled task that has already been claimed.
 *
 * Claiming, history and timing belong elsewhere. All this does is get the work
 * moving, which is the only part that depends on what the host lets PHP do.
 *
 * Two implementations exist because proc_open is not guaranteed. The detached
 * one is what we want everywhere, since the task then outlives the request or
 * tick that started it and can be killed if it hangs. The inline one is a
 * safety net for hosts that block process control, and it admits as much
 * through isDetached() so the panel can be honest about the weaker guarantees.
 *
 * The started process records its own pid, so nothing here has to trace a
 * child through the shell that detached it.
 */
interface TaskRunnerInterface
{
    /**
     * Begin executing a claimed task.
     *
     * @param  int  $taskId  Row in scheduled_tasks
     * @param  int  $runId  Open row in scheduled_task_runs to report against
     * @return bool Whether the work was started
     */
    public function dispatch(int $taskId, int $runId): bool;

    /**
     * Whether work leaves this process and can therefore be killed later.
     */
    public function isDetached(): bool;

    /**
     * Stop a running child.
     *
     * @param  int  $pid  Process id the run recorded for itself
     * @return bool Whether the process was signalled
     */
    public function kill(int $pid): bool;
}
