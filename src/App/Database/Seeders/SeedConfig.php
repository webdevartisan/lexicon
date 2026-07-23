<?php

declare(strict_types=1);

namespace App\Database\Seeders;

/**
 * Immutable configuration for a single seed run.
 *
 * Built from the parsed CLI flags in SeedCommand. Holds the tunable volume
 * counts and behavioural switches so the seeders stay free of argument parsing.
 */
final class SeedConfig
{
    /**
     * @param  int  $users  Total users to create
     * @param  int  $blogs  Total blogs to create
     * @param  int  $postsPerBlog  Baseline posts per blog (jittered per blog)
     * @param  bool  $fresh  Truncate content tables before seeding
     * @param  string|null  $password  Shared plaintext password for all seeded users, or null for random
     * @param  bool  $withNotifications  Also generate a handful of in-app notifications
     */
    public function __construct(
        public readonly int $users = 50,
        public readonly int $blogs = 100,
        public readonly int $postsPerBlog = 100,
        public readonly bool $fresh = false,
        public readonly ?string $password = null,
        public readonly bool $withNotifications = false,
    ) {}

    /**
     * Build a config from the kernel's parsed argument array.
     *
     * Named options arrive keyed by name (e.g. 'users' => '20'); we coerce the
     * numeric flags and clamp them to at least 1 so a typo can never produce a
     * zero-work or negative run.
     *
     * @param  array<int|string, string>  $arguments  Parsed CLI arguments
     */
    public static function fromArguments(array $arguments): self
    {
        $count = static function (array $args, string $key, int $default): int {
            if (!isset($args[$key]) || !is_numeric($args[$key])) {
                return $default;
            }

            return max(1, (int) $args[$key]);
        };

        // A bare --password with no value parses to '1'; treat that as "no shared
        // password" so the flag only takes effect when the user supplies one.
        $password = $arguments['password'] ?? null;
        if ($password === '1' || $password === '') {
            $password = null;
        }

        return new self(
            users: $count($arguments, 'users', 50),
            blogs: $count($arguments, 'blogs', 100),
            postsPerBlog: $count($arguments, 'posts-per-blog', 100),
            fresh: isset($arguments['fresh']),
            password: $password,
            withNotifications: isset($arguments['with-notifications']),
        );
    }
}
