<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Services\UserSuspensionService;

/**
 * Lifts temporary suspensions whose end time has passed.
 *
 * Signing in lifts an expired suspension too, but only for someone who tries.
 * This is what puts a suspended blog back in front of its readers on time,
 * whether or not the account ever comes back.
 *
 * Usage: php cli users:lift-due-suspensions
 */
class LiftDueSuspensionsCommand implements SchedulableCommandInterface
{
    public function __construct(
        private UserSuspensionService $suspensions,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Lift expired user suspensions';
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
        $due = $this->suspensions->dueForLift();
        $lifted = 0;
        $failed = [];

        foreach ($due as $userId) {
            try {
                $this->suspensions->lift($userId, null, 'automatic');
                $lifted++;
            } catch (\Throwable $e) {
                // One account that cannot be restored must not stop the rest,
                // but it is reported rather than swallowed, and the exit code
                // turns the scheduled run red so somebody looks.
                $failed[] = "user {$userId}: ".$e->getMessage();
            }
        }

        echo 'Due: '.count($due)."\n";
        echo "Lifted: {$lifted}\n";

        if ($failed !== []) {
            echo 'Failed: '.count($failed)."\n";

            foreach ($failed as $line) {
                echo "  {$line}\n";
            }

            return 1;
        }

        return 0;
    }
}
