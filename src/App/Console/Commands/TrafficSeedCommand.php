<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\TrafficSeeder;

/**
 * Fill the Traffic dashboard with invented page views, or clear them again.
 *
 * Usage:  php cli traffic:seed                    29 days for the three busiest blogs
 *         php cli traffic:seed --days=14 --blog=6 two weeks for one blog
 *         php cli traffic:seed --reset            delete every page view and rollup
 *
 * Refuses to run in production. --reset empties the traffic tables, local real views included.
 */
final class TrafficSeedCommand
{
    /** One day short of the raw retention period, so no seeded view is pruned straight away. */
    private const MAX_DAYS = 29;

    public function __construct(private TrafficSeeder $seeder) {}

    /**
     * @param  array<int|string, string>  $arguments  Parsed CLI arguments
     * @return int Exit code (0 = success, 1 = failure/refused)
     */
    public function handle(array $arguments = []): int
    {
        if (env('APP_ENV', 'development') === 'production') {
            echo "Refusing to seed: APP_ENV is production.\n";

            return 1;
        }

        if (isset($arguments['reset'])) {
            $this->seeder->reset();
            echo "Deleted every page view, rollup, salt and sign-up event.\n";

            return 0;
        }

        $days = (int) ($arguments['days'] ?? self::MAX_DAYS);
        if ($days < 1 || $days > self::MAX_DAYS) {
            echo '--days must be from 1 to '.self::MAX_DAYS.", inside the raw retention period.\n";

            return 1;
        }

        $blogIds = isset($arguments['blog']) ? [(int) $arguments['blog']] : [];

        foreach ($this->seeder->run($days, $blogIds) as $label => $written) {
            echo "{$label}: {$written} over {$days} days.\n";
        }

        echo "Rollups rebuilt. Open /admin/traffic or /dashboard/blog/{id}/analytics/traffic to see them.\n";

        return 0;
    }
}
