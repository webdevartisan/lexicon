<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\ScheduledTaskRunModel;
use Throwable;

/**
 * Trims scheduled task history.
 *
 * Scheduled through the panel like everything else, which means the retention
 * window is the operator to set and there is one fewer thing that only works
 * if somebody remembered to add a crontab line.
 *
 * Usage: php cli schedule:prune-runs --days=30
 */
class SchedulePruneRunsCommand implements SchedulableCommandInterface
{
    public function __construct(
        private ScheduledTaskRunModel $runs,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Prune task history';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [
            'days' => [
                'type' => 'int',
                'label' => 'Keep history for (days)',
                'min' => 1,
                'max' => 365,
                'default' => 30,
            ],
        ];
    }

    /**
     * Delete finished runs past the retention window.
     *
     * @param  array<string, mixed>  $arguments
     * @return int Exit code, 0 for success
     */
    public function handle(array $arguments = []): int
    {
        $days = (int) ($arguments['days'] ?? 30);

        try {
            $deleted = $this->runs->prune($days);

            echo "Removed {$deleted} run(s) older than {$days} day(s).\n";

            return 0;
        } catch (Throwable $e) {
            echo "Error pruning task history: {$e->getMessage()}\n";

            return 1;
        }
    }
}
