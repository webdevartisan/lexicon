<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScheduleService;
use Throwable;

/**
 * The one thing cron ever needs to call.
 *
 * Everything else is configured in the control panel, so a fresh install works
 * after a single crontab line and never needs another one. Which tasks are due
 * and what they do is worked out here rather than spread across the host crontab,
 * which is also what makes Run Now, enabling and disabling, and run history
 * possible at all.
 *
 * Usage: php cli schedule:run
 * Cron:  * * * * * cd /var/www/html && php cli schedule:run >> /dev/null 2>&1
 */
class ScheduleRunCommand
{
    public function __construct(
        private ScheduleService $schedule,
    ) {}

    /**
     * Dispatch everything due right now.
     *
     * @return int Exit code, 0 for success
     */
    public function handle(): int
    {
        try {
            $result = $this->schedule->tick('cron');

            if ($result['reaped'] > 0) {
                echo "Reaped {$result['reaped']} task(s) that overran their timeout.\n";
            }

            if ($result['started'] === 0 && $result['failed'] === 0) {
                echo "Nothing due.\n";

                return 0;
            }

            echo "Started {$result['started']} task(s).\n";

            if ($result['failed'] > 0) {
                echo "Failed to start {$result['failed']} task(s).\n";
            }

            if ($result['deferred'] > 0) {
                echo "Ran out of budget with {$result['deferred']} left, they will go on the next tick.\n";
            }

            return 0;
        } catch (Throwable $e) {
            echo "Error running the schedule: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }
}
