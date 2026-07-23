<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Faker\Generator;

/**
 * Seeds a threaded discussion on published posts: top-level comments from random
 * users, with a share of them drawing one or two replies. Top-level rows are
 * inserted individually because replies need concrete parent ids, which a
 * batched insert would not return.
 */
final class CommentSeeder extends Seeder
{
    public function seed(SeedConfig $config, SeedContext $context, Generator $faker): void
    {
        $userIds = $context->userIds;
        if ($userIds === []) {
            return;
        }

        foreach ($context->publishedPosts as $post) {
            $this->seedPostComments($post['id'], $userIds, $faker);
        }
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function seedPostComments(int $postId, array $userIds, Generator $faker): void
    {
        $topLevelIds = [];

        for ($n = random_int(0, 5); $n > 0; $n--) {
            $topLevelIds[] = $this->insertOne('comments', [
                'post_id' => $postId,
                'user_id' => (int) $faker->randomElement($userIds),
                'content' => $faker->paragraph(),
                'status' => $this->status($faker),
                'created_at' => $faker->dateTimeBetween('-1 year')->format('Y-m-d H:i:s'),
            ]);
        }

        $replies = [];
        foreach ($topLevelIds as $parentId) {
            // Only some threads attract replies.
            if (!$faker->boolean(40)) {
                continue;
            }

            for ($r = random_int(1, 2); $r > 0; $r--) {
                $replies[] = [
                    'post_id' => $postId,
                    'user_id' => (int) $faker->randomElement($userIds),
                    'parent_comment_id' => $parentId,
                    'content' => $faker->sentence(random_int(8, 20)),
                    'status' => $this->status($faker),
                    'created_at' => $faker->dateTimeBetween('-11 months')->format('Y-m-d H:i:s'),
                ];
            }
        }

        $this->insertMany('comments', $replies);
    }

    /**
     * Weight moderation state toward approved, with the occasional held or spam
     * comment so moderation queues are not empty.
     */
    private function status(Generator $faker): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 85) {
            return 'approved';
        }

        return $roll <= 95 ? 'pending' : 'spam';
    }
}
