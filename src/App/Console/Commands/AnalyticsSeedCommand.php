<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\AnalyticsSeeder;

/**
 * Fill the Insights pages with invented visits, or clear them again.
 *
 * Usage:  php cli analytics:seed                    29 days for the three busiest blogs
 *         php cli analytics:seed --days=14 --blog=6 two weeks for one blog
 *         php cli analytics:seed --reset            delete every page view and rollup
 *
 * Load testing: more blogs, a long tail of traffic and months of older days.
 *         php cli analytics:seed --reset
 *         php cli analytics:seed --blogs=300 --history=365 --visitors=1500
 *
 *   --blogs=N      the N blogs with the most published posts (up to 200 of their posts get views)
 *   --history=N    N more days before the 29, kept as rollups only, the way production keeps them
 *   --visitors=N   daily visitors to the busiest blog; the others follow by rank
 *
 * Run --reset first when adding history, since older raw rows already in the table are pruned.
 * Refuses to run in production. --reset empties the analytics tables, local real views included.
 */
final class AnalyticsSeedCommand
{
    /** One day short of the raw retention period, so no seeded view is pruned straight away. */
    private const MAX_DAYS = 29;

    private const MAX_SPAN = 395;

    private const MAX_BLOGS = 5000;

    public function __construct(private AnalyticsSeeder $seeder) {}

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
            echo "Deleted every page view, rollup, salt, sign-up, goal, click, missing page and beacon count.\n";

            return 0;
        }

        $days = (int) ($arguments['days'] ?? self::MAX_DAYS);
        if ($days < 1 || $days > self::MAX_DAYS) {
            echo '--days must be from 1 to '.self::MAX_DAYS.", inside the raw retention period.\n";

            return 1;
        }

        $history = (int) ($arguments['history'] ?? 0);
        if ($history < 0 || $history + $days > self::MAX_SPAN) {
            echo '--history must be from 0 to '.(self::MAX_SPAN - $days).", so the oldest day is within 13 months.\n";

            return 1;
        }

        $blogCount = (int) ($arguments['blogs'] ?? 3);
        if ($blogCount < 1 || $blogCount > self::MAX_BLOGS || (isset($arguments['blog']) && isset($arguments['blogs']))) {
            echo '--blogs must be from 1 to '.self::MAX_BLOGS." and cannot be combined with --blog.\n";

            return 1;
        }

        $visitors = isset($arguments['visitors']) ? (int) $arguments['visitors'] : null;
        if ($visitors !== null && $visitors < 1) {
            echo "--visitors must be 1 or more.\n";

            return 1;
        }

        $blogIds = isset($arguments['blog']) ? [(int) $arguments['blog']] : [];
        $started = microtime(true);
        $progress = static function (string $line): void {
            echo "  {$line}\n";
        };

        if ($history > 0) {
            echo "Seeding {$history} older days as rollups, then {$days} raw days.\n";
        }

        foreach ($this->seeder->run($days, $blogIds, $blogCount, $history, $visitors, $progress) as $label => $written) {
            echo "{$label}: {$written} over {$days} days.\n";
        }

        echo 'Rollups rebuilt in '.round(microtime(true) - $started)."s total. Open /admin/insights or /dashboard/blog/{id}/insights to see them.\n";

        return 0;
    }
}
