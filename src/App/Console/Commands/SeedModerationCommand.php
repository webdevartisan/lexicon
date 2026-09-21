<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\ModerationSeeder;

/**
 * Fill the moderation area with cases in every state, or clear it again.
 *
 * Usage:  php cli moderation:seed           add a fresh set of cases
 *         php cli moderation:seed --reset   remove every case and report, and restore what they hid
 *
 * Works on whatever content is already there, so run php cli db:seed first on
 * an empty database. Development only: it refuses to run when APP_ENV=production.
 */
final class SeedModerationCommand
{
    public function __construct(private ModerationSeeder $seeder) {}

    /**
     * @param  array<int|string, string>  $arguments  Parsed CLI arguments
     * @return int Exit code (0 = success, 1 = failure/refused)
     */
    public function handle(array $arguments = []): int
    {
        if (env('APP_ENV', 'development') === 'production') {
            echo "✗ Refusing to seed: APP_ENV is production.\n";

            return 1;
        }

        try {
            return isset($arguments['reset']) ? $this->reset() : $this->seed();
        } catch (\Throwable $e) {
            echo "✗ {$e->getMessage()}\n";

            return 1;
        }
    }

    private function seed(): int
    {
        echo "Filing reports and making decisions through the real services...\n";

        foreach ($this->seeder->run() as $line) {
            echo "  case {$line}\n";
        }

        echo "✓ Done. Open /admin/reports to see them.\n";
        echo "  Warnings were queued as email like any other; they go out only if the mail worker runs with mail enabled.\n";

        return 0;
    }

    private function reset(): int
    {
        $result = $this->seeder->reset();

        echo "✓ Removed {$result['cases']} case(s) with their reports, audit rows, notifications and queued warnings.\n";
        echo "  Restored {$result['restored_comments']} hidden comment(s) and {$result['restored_posts']} hidden post(s). Reporting pauses cleared.\n";

        if ($result['open_suspensions'] > 0) {
            echo "  ! {$result['open_suspensions']} suspension(s) made from a case are still in force. Lift them from the user's page in the admin.\n";
        }

        return 0;
    }
}
