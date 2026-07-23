<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Database\Seeders\DatabaseSeeder;
use App\Database\Seeders\SeedConfig;
use Framework\Database;

/**
 * Populate the database with realistic fake content for local development.
 *
 * Builds a full graph. users with profiles and roles, blogs with settings,
 * collaborators and subscribers, categorized and tagged posts, threaded
 * comments, likes, bookmarks, and review-workflow rows, so the running app can
 * be browsed with lifelike data.
 *
 * Usage:  php cli db:seed [--fresh] [--users=N] [--blogs=N]
 *                         [--posts-per-blog=N] [--password=SECRET]
 *                         [--with-notifications]
 *
 * This is a development-only tool and refuses to run when APP_ENV=production.
 */
final class SeedCommand
{
    public function __construct(
        private DatabaseSeeder $seeder,
        private Database $db,
    ) {}

    /**
     * Execute the seed run.
     *
     * @param  array<int|string, string>  $arguments  Parsed CLI arguments
     * @return int Exit code (0 = success, 1 = failure/refused)
     */
    public function handle(array $arguments = []): int
    {
        if (env('APP_ENV', 'development') === 'production') {
            echo "✗ Refusing to seed: APP_ENV is production.\n";
            echo "  Seeding is a development-only tool.\n";

            return 1;
        }

        $config = SeedConfig::fromArguments($arguments);

        echo "Seeding development data...\n";
        echo "  users={$config->users} blogs={$config->blogs} posts-per-blog={$config->postsPerBlog}"
            .($config->fresh ? ' (fresh)' : '')."\n";
        if ($config->password !== null) {
            echo "  shared password set for all seeded users\n";
        }

        try {
            $start = microtime(true);

            $this->seeder->run($config);

            $duration = round(microtime(true) - $start, 1);

            echo "✓ Seed complete in {$duration}s\n";
            $this->printSummary();

            if ($config->password === null) {
                echo "\nNote: user passwords are random. Re-run with --password=secret to log in as any user.\n";
            }

            return 0;
        } catch (\Throwable $e) {
            echo "✗ Seeding failed: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }

    /**
     * Print resulting row counts for the tables the seeder populates.
     */
    private function printSummary(): void
    {
        $tables = [
            'users', 'blogs', 'categories', 'tags', 'posts',
            'comments', 'post_likes', 'blog_subscribers', 'reviews', 'notifications',
        ];

        echo "\nRow counts:\n";
        foreach ($tables as $table) {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            echo '  '.str_pad($table, 20).$count."\n";
        }
    }
}
