<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Interfaces\TaskRunnerInterface;
use App\Services\TaskExecutor;

/**
 * Fallback for hosts that will not let PHP start processes.
 *
 * The task runs inside whatever called it, so a tick lasts as long as its
 * tasks do and a fatal error inside one takes the rest of that tick with it.
 * Worse, a task that hangs cannot be stopped, because there is no separate
 * process to stop. Nothing here can fix that, which is why isDetached() says
 * so plainly and the panel passes the warning on.
 *
 * Execution itself is shared with the detached path rather than reimplemented,
 * so the two cannot drift apart in how they record results.
 */
class InlineRunner implements TaskRunnerInterface
{
    public function __construct(
        private TaskExecutor $executor,
    ) {}

    /**
     * Run the task here and now.
     */
    public function dispatch(int $taskId, int $runId): bool
    {
        $this->executor->execute($taskId, $runId);

        return true;
    }

    public function isDetached(): bool
    {
        return false;
    }

    /**
     * Always fails. The work is happening in this process, so there is nothing
     * separate left to signal.
     */
    public function kill(int $pid): bool
    {
        return false;
    }
}
