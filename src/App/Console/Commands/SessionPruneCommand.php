<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use Framework\Session\SessionRegistry;

/**
 * Deletes user_sessions rows whose idle or absolute limit has passed.
 *
 * Expired sessions are already refused when opened; this keeps the table
 * small and removes the address and device they recorded.
 *
 * Usage: php cli session:prune
 */
class SessionPruneCommand implements SchedulableCommandInterface
{
    public function __construct(private SessionRegistry $registry) {}

    public static function scheduleLabel(): string
    {
        return 'Prune expired sessions';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    public function handle(array $arguments = []): int
    {
        $deleted = $this->registry->prune(time());

        echo "Expired sessions deleted: {$deleted}\n";

        return 0;
    }
}
