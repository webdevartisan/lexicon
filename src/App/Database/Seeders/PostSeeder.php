<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Faker\Generator;

/**
 * Seeds posts for every blog, authored by that blog's members, with a realistic
 * spread of lifecycle states. Each published post also picks up tags, likes and
 * bookmarks so the engagement surfaces are populated. Child rows are flushed per
 * blog to keep memory flat across large runs.
 */
final class PostSeeder extends Seeder
{
    public function seed(SeedConfig $config, SeedContext $context, Generator $faker): void
    {
        foreach ($context->blogs as $blog) {
            $this->seedBlogPosts($config, $context, $faker, $blog);
        }
    }

    /**
     * @param  array{id:int, owner_id:int, workflow:bool, members:array<int,int>}  $blog
     */
    private function seedBlogPosts(SeedConfig $config, SeedContext $context, Generator $faker, array $blog): void
    {
        $blogId = $blog['id'];
        $members = $blog['members'];
        $categoryIds = $context->categoriesByBlog[$blogId] ?? [];
        $tagIds = $context->tagsByBlog[$blogId] ?? [];

        // Jitter the per-blog count so archives are not all the same length.
        $target = (int) round($config->postsPerBlog * $faker->randomFloat(2, 0.6, 1.2));
        $usedSlugs = [];

        $postTags = [];
        $postLikes = [];
        $postBookmarks = [];

        for ($i = 0; $i < $target; $i++) {
            $title = rtrim($faker->sentence(random_int(4, 9)), '.');
            $slug = $this->uniqueSlug($title, $usedSlugs);
            $authorId = (int) $faker->randomElement($members);
            [$status, $workflowState, $publishedAt, $createdAt] = $this->lifecycle($faker);

            $postId = $this->insertOne('posts', [
                'blog_id' => $blogId,
                'author_id' => $authorId,
                'category_id' => $categoryIds !== [] ? (int) $faker->randomElement($categoryIds) : null,
                'title' => $title,
                'slug' => $slug,
                'content' => $faker->paragraphs(random_int(15, 30), true),
                'excerpt' => $faker->text(200),
                'status' => $status,
                'workflow_state' => $workflowState,
                'published_at' => $publishedAt,
                'created_at' => $createdAt,
            ]);

            foreach ($this->randomSubset($tagIds, 1, 4) as $tagId) {
                $postTags[] = ['post_id' => $postId, 'tag_id' => (int) $tagId];
            }

            if ($status === 'published') {
                foreach ($this->randomSubset($context->userIds, 0, 10) as $userId) {
                    $postLikes[] = ['post_id' => $postId, 'user_id' => (int) $userId];
                }
                foreach ($this->randomSubset($context->userIds, 0, 5) as $userId) {
                    $postBookmarks[] = ['post_id' => $postId, 'user_id' => (int) $userId];
                }

                $context->publishedPosts[] = [
                    'id' => $postId,
                    'blog_id' => $blogId,
                    'author_id' => $authorId,
                ];
            } elseif ($blog['workflow'] && in_array($status, ['draft', 'pending'], true)) {
                $context->reviewablePostsByBlog[$blogId][] = $postId;
            }
        }

        $this->insertMany('post_tags', $postTags);
        $this->insertMany('post_votes', $postLikes);
        $this->insertMany('post_bookmarks', $postBookmarks);
    }

    /**
     * Choose a lifecycle state and the dates that go with it.
     *
     * @return array{0:string, 1:string, 2:string|null, 3:string} status, workflow_state, published_at, created_at
     */
    private function lifecycle(Generator $faker): array
    {
        $roll = random_int(1, 100);

        if ($roll <= 60) {
            $published = $faker->dateTimeBetween('-1 year', '-1 hour');

            return ['published', 'approved', $published->format('Y-m-d H:i:s'), $published->format('Y-m-d H:i:s')];
        }

        if ($roll <= 68) {
            $future = $faker->dateTimeBetween('+1 hour', '+3 weeks');

            return ['scheduled', 'approved', $future->format('Y-m-d H:i:s'), $faker->dateTimeBetween('-1 week')->format('Y-m-d H:i:s')];
        }

        if ($roll <= 75) {
            return ['pending', 'in_review', null, $faker->dateTimeBetween('-2 months')->format('Y-m-d H:i:s')];
        }

        if ($roll <= 95) {
            return ['draft', 'draft', null, $faker->dateTimeBetween('-6 months')->format('Y-m-d H:i:s')];
        }

        $archived = $faker->dateTimeBetween('-2 years', '-6 months');

        return ['archived', 'approved', $archived->format('Y-m-d H:i:s'), $archived->format('Y-m-d H:i:s')];
    }

    /**
     * Build a slug unique within the current blog (schema is unique per blog_id).
     *
     * @param  array<string, bool>  $used  Slugs already taken in this blog (by reference)
     */
    private function uniqueSlug(string $title, array &$used): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '', '-');
        $base = $base !== '' ? $base : 'post';

        $slug = $base;
        $suffix = 2;
        while (isset($used[$slug])) {
            $slug = $base.'-'.$suffix++;
        }

        $used[$slug] = true;

        return $slug;
    }
}
