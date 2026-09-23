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
 * Cron:  * * * * * cd /var/www/html && php cli schedule:run >> storage/logs/cron.log 2>&1
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
                $this->line("Reaped {$result['reaped']} task(s) that overran their timeout.");
            }

            if ($result['started'] === 0 && $result['failed'] === 0) {
                $this->line('Nothing due.');

                return 0;
            }

            $this->line("Started {$result['started']} task(s).");

            if ($result['failed'] > 0) {
                $this->line("Failed to start {$result['failed']} task(s).");
            }

            if ($result['deferred'] > 0) {
                $this->line("Ran out of budget with {$result['deferred']} left, they will go on the next tick.");
            }

            return 0;
        } catch (Throwable $e) {
            $this->line("Error running the schedule: {$e->getMessage()}");
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }

    /**
     * Write one line of cron output.
     *
     * This output is usually only ever read months later in cron.log, where an
     * unstamped line says nothing about whether cron is still alive, so every
     * line carries the time it was written. UTC matches the rest of the app.
     */
    private function line(string $message): void
    {
        echo '['.gmdate('Y-m-d H:i:s').' UTC] '.$message."\n";
    }
}
