<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Services\AccountErasureSchedulerService;

/**
 * Runs self-requested erasures whose grace period has passed.
 *
 * Usage: php cli privacy:process-due-erasures
 */
class ProcessDueErasuresCommand implements SchedulableCommandInterface
{
    public function __construct(
        private AccountErasureSchedulerService $scheduler,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Process due account erasures';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments = []): int
    {
        $counts = $this->scheduler->processDue();

        echo "Erased: {$counts['erased']}\n";
        echo "Cancelled (reactivated during grace period): {$counts['cancelled']}\n";
        echo "Still blocked, left queued: {$counts['still_blocked']}\n";

        return 0;
    }
}
