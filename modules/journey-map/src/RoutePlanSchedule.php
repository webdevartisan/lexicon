<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Models\ScheduledTaskModel;
use Framework\Database;

/**
 * The Scheduler row that plans waiting legs every minute. It exists only while a road network is built;
 * a run with nothing waiting returns at once without starting anything.
 */
final class RoutePlanSchedule
{
    /** Starting the planner and a full batch of legs fits well inside this. */
    private const TIMEOUT_SECONDS = 600;

    public function __construct(private Database $db, private ScheduledTaskModel $tasks) {}

    /**
     * Add the row once.
     */
    public function ensure(): void
    {
        $found = $this->db->query('SELECT id FROM scheduled_tasks WHERE command = ? LIMIT 1', [PlanRoutesCommand::NAME])
            ->fetchColumn();
        if ($found !== false) {
            return;
        }

        $this->tasks->createTask([
            'label' => 'Journey map road routes',
            'command' => PlanRoutesCommand::NAME,
            'arguments' => json_encode([]),
            'schedule_type' => 'every_minute',
            'interval_minutes' => null,
            'minute_of_hour' => null,
            'run_at' => null,
            'schedule_timezone' => 'UTC',
            'timeout_seconds' => self::TIMEOUT_SECONDS,
            'is_active' => 1,
            'next_run_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove the row, with the network or the module.
     */
    public static function forget(Database $db): void
    {
        $db->execute('DELETE FROM scheduled_tasks WHERE command = ?', [PlanRoutesCommand::NAME]);
    }
}
