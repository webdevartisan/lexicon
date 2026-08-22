<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Faker\Generator;

/**
 * Seeds users together with their profile, preferences, social links and a
 * site-wide role. One user is made an Administrator and a slice become Content
 * Managers so the admin area has varied accounts to browse; everyone else is a
 * Reader, matching how real sign-ups start.
 */
final class UserSeeder extends Seeder
{
    private const SOCIAL_NETWORKS = ['twitter', 'github', 'linkedin', 'website', 'mastodon', 'instagram'];

    /**
     * Resolve a system role slug to its id at seed time.
     *
     * Role ids drift across environments (roles get added and re-seeded), so
     * never hardcode them. Resolving by slug is the single robust approach.
     *
     * @var array<string, int>
     */
    private array $roleIdBySlug = [];

    public function seed(SeedConfig $config, SeedContext $context, Generator $faker): void
    {
        // Hash the shared password once; a hash is deliberately expensive and we
        // would otherwise pay for it on every seeded user.
        $sharedHash = $config->password !== null
            ? password_hash($config->password, PASSWORD_DEFAULT)
            : null;

        // Map system role slugs to their ids once; blog roles never land in
        // user_roles, so only system-scoped roles are relevant here.
        $this->roleIdBySlug = $this->db
            ->query("SELECT role_slug, id FROM roles WHERE scope = 'system'")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        $profiles = [];
        $preferences = [];
        $socialLinks = [];
        $roles = [];

        for ($i = 0; $i < $config->users; $i++) {
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();
            $username = $faker->unique()->userName();

            $userId = $this->insertOne('users', [
                'username' => $username,
                'email' => $faker->unique()->safeEmail(),
                'password' => $sharedHash ?? password_hash($faker->password(12), PASSWORD_DEFAULT),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'display_name_cached' => $firstName.' '.$lastName,
                'is_active' => 1,
                'created_at' => $faker->dateTimeBetween('-2 years')->format('Y-m-d H:i:s'),
            ]);

            $context->userIds[] = $userId;

            $profiles[] = [
                'user_id' => $userId,
                'slug' => $username,
                'bio' => $faker->sentence(12),
                'location' => $faker->city(),
                'occupation' => $faker->jobTitle(),
                'is_public' => 1,
            ];

            $preferences[] = [
                'user_id' => $userId,
                'display_name_preference' => $faker->randomElement(['name', 'username']),
                'timezone' => $faker->timezone(),
            ];

            foreach ($this->randomSubset(self::SOCIAL_NETWORKS, 0, 3) as $network) {
                $socialLinks[] = [
                    'user_id' => $userId,
                    'network' => $network,
                    'url' => $faker->url(),
                ];
            }

            $roles[] = [
                'user_id' => $userId,
                'role_id' => $this->roleIdBySlug[$this->roleSlugForIndex($i, $faker)],
            ];
        }

        $this->insertMany('user_profiles', $profiles);
        $this->insertMany('user_preferences', $preferences);
        $this->insertMany('user_social_links', $socialLinks);
        $this->insertMany('user_roles', $roles);
    }

    /**
     * Decide a site-wide role slug: the very first user administers the site,
     * roughly one in eight of the rest manage content, everyone else reads.
     */
    private function roleSlugForIndex(int $index, Generator $faker): string
    {
        if ($index === 0) {
            return 'administrator';
        }

        return $faker->boolean(12) ? 'content_manager' : 'reader';
    }
}
