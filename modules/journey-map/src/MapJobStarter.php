<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Models\ScheduledTaskModel;
use App\Services\ScheduleService;
use Framework\Database;

/**
 * Starts the map command in the background through the Scheduler's Run now, so the module needs
 * no process code of its own and two presses can never start two jobs.
 *
 * The module keeps one task row for this. It is switched off, so it only runs when started here,
 * and daily, so an admin who switches it on in the Scheduler gets one refresh a day, not one a minute.
 */
class MapJobStarter
{
    private const COMMAND = 'journey-map:map-data';

    /** A world download can take hours; the Scheduler stops a run that outlives this. */
    private const TIMEOUT_SECONDS = 43200;

    public function __construct(
        private Database $db,
        private ScheduledTaskModel $tasks,
        private ScheduleService $schedule,
    ) {
    }

    /**
     * Start the command now.
     *
     * @return array{ok: bool, message: string}
     */
    public function start(): array
    {
        return $this->schedule->runNow($this->taskId());
    }

    /**
     * Delete the module's task row; its run history goes with it.
     */
    public static function forget(Database $db): void
    {
        $db->execute('DELETE FROM scheduled_tasks WHERE command = ?', [self::COMMAND]);
    }

    /**
     * The module's task row, created on first use.
     */
    private function taskId(): int
    {
        $id = $this->db->query('SELECT id FROM scheduled_tasks WHERE command = ? ORDER BY id LIMIT 1', [self::COMMAND])
            ->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }

        return $this->tasks->createTask([
            'label' => 'Journey map download',
            'command' => self::COMMAND,
            'arguments' => json_encode([]),
            'schedule_type' => 'daily',
            'interval_minutes' => null,
            'minute_of_hour' => null,
            'run_at' => '04:00:00',
            'schedule_timezone' => 'UTC',
            'timeout_seconds' => self::TIMEOUT_SECONDS,
            'is_active' => 0,
            'next_run_at' => null,
        ]);
    }
}
