<?php

declare(strict_types=1);

namespace App\Database\Seeders;

use Faker\Generator;

/**
 * Seeds per-blog categories and tags. Taxonomy is scoped to a blog in this
 * schema (unique on blog_id + slug), so each blog gets its own fresh set and we
 * de-duplicate slugs within a blog rather than across the whole run.
 */
final class TaxonomySeeder extends Seeder
{
    public function seed(SeedConfig $config, SeedContext $context, Generator $faker): void
    {
        foreach ($context->blogs as $blog) {
            $blogId = $blog['id'];

            $context->categoriesByBlog[$blogId] = $this->seedTerms(
                'categories',
                $blogId,
                random_int(3, 6),
                $faker
            );

            $context->tagsByBlog[$blogId] = $this->seedTerms(
                'tags',
                $blogId,
                random_int(6, 12),
                $faker
            );
        }
    }

    /**
     * Insert a set of taxonomy terms for one blog and return their ids.
     *
     * We collect ids as we go because posts need concrete category/tag ids, and
     * a batched insert would not hand them back.
     *
     * @return array<int, int> Created term ids
     */
    private function seedTerms(string $table, int $blogId, int $count, Generator $faker): array
    {
        $ids = [];
        $usedSlugs = [];

        for ($i = 0; $i < $count; $i++) {
            $name = ucfirst($faker->words(random_int(1, 2), true));
            $slug = $this->uniqueSlug($name, $usedSlugs);

            $ids[] = $this->insertOne($table, [
                'blog_id' => $blogId,
                'name' => $name,
                'slug' => $slug,
            ]);
        }

        return $ids;
    }

    /**
     * Build a slug unique within the current blog, appending a counter on clash.
     *
     * @param  array<string, bool>  $used  Slugs already taken in this blog (by reference)
     */
    private function uniqueSlug(string $name, array &$used): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
        $base = $base !== '' ? $base : 'term';

        $slug = $base;
        $suffix = 2;
        while (isset($used[$slug])) {
            $slug = $base.'-'.$suffix++;
        }

        $used[$slug] = true;

        return $slug;
    }
}
