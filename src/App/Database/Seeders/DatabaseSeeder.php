<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Framework\Database;

/**
 * Orchestrates a full dev-data seed run.
 *
 * Owns the single shared Faker instance (so its unique() state is consistent
 * across phases) and runs the sub-seeders in foreign-key dependency order,
 * threading a SeedContext through them so each phase can wire onto the ids the
 * previous ones produced. The content graph is written inside one transaction
 * for atomicity; an optional --fresh truncate runs first, outside it, because
 * TRUNCATE implicitly commits in MySQL.
 */
final class DatabaseSeeder
{
    /**
     * Content tables owned by the seeder, child-before-parent for --fresh.
     * Reference data (roles, permissions, settings) and transient tables are
     * deliberately excluded so a reseed never disturbs them.
     */
    private const CONTENT_TABLES = [
        'reviews',
        'post_reviewers',
        'submissions',
        'post_votes',
        'post_bookmarks',
        'post_tags',
        'comments',
        'notifications',
        'posts',
        'blog_invitations',
        'blog_subscribers',
        'blog_users',
        'blog_settings',
        'categories',
        'tags',
        'blogs',
        'user_social_links',
        'user_preferences',
        'user_profiles',
        'user_roles',
        'users',
    ];

    public function __construct(
        private Database $db,
        private UserSeeder $users,
        private BlogSeeder $blogs,
        private TaxonomySeeder $taxonomy,
        private PostSeeder $posts,
        private CommentSeeder $comments,
        private WorkflowSeeder $workflow,
    ) {}

    /**
     * Run every phase and return the populated context (used for the summary).
     */
    public function run(SeedConfig $config): SeedContext
    {
        $faker = FakerFactory::create();
        $context = new SeedContext();

        if ($config->fresh) {
            $this->truncateContentTables();
        }

        $this->db->transaction(function () use ($config, $context, $faker): void {
            $this->users->seed($config, $context, $faker);
            $this->blogs->seed($config, $context, $faker);
            $this->taxonomy->seed($config, $context, $faker);
            $this->posts->seed($config, $context, $faker);
            $this->comments->seed($config, $context, $faker);
            $this->workflow->seed($config, $context, $faker);

            if ($config->withNotifications) {
                $this->seedNotifications($context, $faker);
            }
        });

        return $context;
    }

    /**
     * Empty the seeded content tables in FK-safe order.
     *
     * We drop foreign-key checks for the duration so children and parents can be
     * cleared without ordering fights, mirroring the test suite's cleanup helper.
     */
    private function truncateContentTables(): void
    {
        $connection = $this->db->getConnection();
        $connection->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach (self::CONTENT_TABLES as $table) {
            $connection->exec("TRUNCATE TABLE {$table}");
        }

        $connection->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Seed a handful of in-app notifications linked to real published posts so
     * the notification feed has working entries. Opt-in via --with-notifications.
     */
    private function seedNotifications(SeedContext $context, Generator $faker): void
    {
        if ($context->publishedPosts === [] || $context->userIds === []) {
            return;
        }

        $rows = [];
        foreach ($context->userIds as $userId) {
            for ($n = random_int(0, 4); $n > 0; $n--) {
                $post = $faker->randomElement($context->publishedPosts);

                $rows[] = [
                    'user_id' => $userId,
                    'type' => 'post.published',
                    'data' => json_encode([
                        'post_id' => $post['id'],
                        'blog_id' => $post['blog_id'],
                    ], JSON_THROW_ON_ERROR),
                    'read_at' => $faker->boolean(50)
                        ? $faker->dateTimeBetween('-1 month')->format('Y-m-d H:i:s')
                        : null,
                    'created_at' => $faker->dateTimeBetween('-2 months')->format('Y-m-d H:i:s'),
                ];
            }
        }

        $this->insertNotifications($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insertNotifications(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, ?)'));
            $values = [];
            foreach ($chunk as $row) {
                array_push($values, $row['user_id'], $row['type'], $row['data'], $row['read_at'], $row['created_at']);
            }

            $this->db->execute(
                "INSERT INTO notifications (user_id, type, data, read_at, created_at) VALUES {$placeholders}",
                $values
            );
        }
    }
}
