<?php

declare(strict_types=1);

use App\Models\ScheduledTaskModel;
use App\Services\ScheduleService;
use Modules\JourneyMap\MapJobStarter;
use Tests\Helpers\ModuleFixtures;

/**
 * Jobs start through the Scheduler's Run now, on one task row the module keeps for itself.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->db->execute("DELETE FROM scheduled_tasks WHERE command = 'journey-map:map-data'");
    $this->schedule = Mockery::mock(ScheduleService::class);
    $this->starter = new MapJobStarter($this->db, new ScheduledTaskModel($this->db), $this->schedule);
});

test('the first start creates a switched off daily row and runs it now', function () {
    $this->schedule->shouldReceive('runNow')->once()->andReturn(['ok' => true, 'message' => 'Started.']);

    expect($this->starter->start())->toBe(['ok' => true, 'message' => 'Started.']);

    $row = $this->db->query("SELECT * FROM scheduled_tasks WHERE command = 'journey-map:map-data'")
        ->fetch(PDO::FETCH_ASSOC);
    expect($row)->toMatchArray([
        'label' => 'Journey map download',
        'schedule_type' => 'daily',
        'is_active' => 0,
        'timeout_seconds' => 43200,
    ])->and($row['next_run_at'])->toBeNull();
});

test('later starts reuse the same row', function () {
    $ids = [];
    $this->schedule->shouldReceive('runNow')->twice()->andReturnUsing(function (int $id) use (&$ids): array {
        $ids[] = $id;

        return ['ok' => true, 'message' => 'Started.'];
    });

    $this->starter->start();
    $this->starter->start();

    $count = (int) $this->db->query("SELECT COUNT(*) FROM scheduled_tasks WHERE command = 'journey-map:map-data'")
        ->fetchColumn();
    expect($count)->toBe(1)
        ->and($ids[0])->toBe($ids[1]);
});

test('forget deletes the task row, so uninstall leaves no Scheduler entry', function () {
    $this->schedule->shouldReceive('runNow')->once()->andReturn(['ok' => true, 'message' => 'Started.']);
    $this->starter->start();

    MapJobStarter::forget($this->db);

    $rows = $this->db->query("SELECT COUNT(*) FROM scheduled_tasks WHERE command = 'journey-map:map-data'");

    expect($rows->fetchColumn())->toBe(0);
});
