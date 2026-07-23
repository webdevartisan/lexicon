<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use App\Models\BlogModel;
use Faker\Generator;

/**
 * Seeds blogs with their settings, collaborator memberships, subscribers and a
 * few pending invitations. Owners are drawn from the seeded users; each blog
 * picks up zero to three additional collaborators so the multi-user surfaces
 * (author lists, membership screens) have something to show.
 */
final class BlogSeeder extends Seeder
{
    public function seed(SeedConfig $config, SeedContext $context, Generator $faker): void
    {
        $themes = $this->installedThemes();
        $userIds = $context->userIds;
        $primaryAssigned = [];

        $settings = [];
        $memberships = [];
        $subscribers = [];
        $invitations = [];

        for ($i = 0; $i < $config->blogs; $i++) {
            $ownerId = (int) $faker->randomElement($userIds);
            $status = $faker->randomElement(['published', 'published', 'published', 'draft', 'archived']);
            $publishedAt = $status === 'draft'
                ? null
                : $faker->dateTimeBetween('-18 months', '-1 day')->format('Y-m-d H:i:s');

            // Built from words rather than catchPhrase(), which only exists on the
            // en_US provider and would break under any other Faker locale.
            $name = ucwords($faker->words(random_int(2, 3), true));
            $blogId = $this->insertOne('blogs', [
                'blog_name' => $name,
                'blog_slug' => $faker->slug(2).'-'.$faker->unique()->numberBetween(1000, 999999),
                'description' => $faker->paragraph(),
                'owner_id' => $ownerId,
                'status' => $status,
                'is_featured' => ($status === 'published' && $faker->boolean(10)) ? 1 : 0,
                'published_at' => $publishedAt,
                'archived_at' => $status === 'archived' ? $publishedAt : null,
            ]);

            $workflowEnabled = $faker->boolean(30);

            $settings[] = [
                'blog_id' => $blogId,
                'theme' => $faker->randomElement($themes),
                'default_locale' => 'en',
                'tagline' => rtrim($faker->sentence(5), '.'),
                'subtitle' => $faker->sentence(8),
                'about_text' => $faker->paragraph(),
                'comments_enabled' => 1,
                'workflow_enabled' => $workflowEnabled ? 1 : 0,
                'translations_enabled' => $faker->boolean(15) ? 1 : 0,
                'is_primary' => isset($primaryAssigned[$ownerId]) ? 0 : 1,
            ];
            $primaryAssigned[$ownerId] = true;

            // Collaborators: other users, distinct, never the owner.
            $candidates = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $ownerId));
            $collaborators = $this->randomSubset($candidates, 0, 3);
            $members = [$ownerId];

            foreach ($collaborators as $collaboratorId) {
                $members[] = (int) $collaboratorId;
                $memberships[] = [
                    'blog_id' => $blogId,
                    'user_id' => (int) $collaboratorId,
                    'role' => $faker->randomElement(BlogModel::ROLES),
                    'assigned_by' => $ownerId,
                    'is_active' => 1,
                ];
            }

            foreach ($this->randomSubset($userIds, 0, 6) as $subscriberUserId) {
                $subscribers[] = [
                    'blog_id' => $blogId,
                    'user_id' => (int) $subscriberUserId,
                    'email' => $faker->unique()->safeEmail(),
                    'token' => bin2hex(random_bytes(32)),
                ];
            }

            for ($n = random_int(0, 2); $n > 0; $n--) {
                $invitations[] = [
                    'blog_id' => $blogId,
                    'email' => $faker->unique()->safeEmail(),
                    'role' => $faker->randomElement(BlogModel::ROLES),
                    'token' => bin2hex(random_bytes(32)),
                    'invited_by' => $ownerId,
                    'expires_at' => $faker->dateTimeBetween('+1 day', '+2 weeks')->format('Y-m-d H:i:s'),
                ];
            }

            $context->blogs[] = [
                'id' => $blogId,
                'owner_id' => $ownerId,
                'workflow' => $workflowEnabled,
                'members' => $members,
            ];
        }

        $this->insertMany('blog_settings', $settings);
        $this->insertMany('blog_users', $memberships);
        $this->insertMany('blog_subscribers', $subscribers);
        $this->insertMany('blog_invitations', $invitations);
    }

    /**
     * Discover the installed theme folder names so seeded blogs reference themes
     * that actually render. Falls back to the framework default if none resolve.
     *
     * @return array<int, string>
     */
    private function installedThemes(): array
    {
        $dirs = glob(ROOT_PATH.'/themes/*', GLOB_ONLYDIR) ?: [];
        $themes = array_map('basename', $dirs);

        return $themes !== [] ? $themes : ['folio'];
    }
}
