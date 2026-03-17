<?php

declare(strict_types=1);

namespace Tests\Factories;

use Faker\Factory as Faker;

/**
 * Factory for creating blog test data.
 *
 * Provides fluent interface for generating blogs with various states
 * (draft, published) and customizable attributes for integration tests.
 */
class BlogFactory
{
    private $faker;

    private $blogModel;

    private array $attributes = [];

    private int $count = 1;

    /**
     * Create a new BlogFactory instance.
     *
     * @param  object  $blogModel  The BlogModel instance
     */
    public static function new($blogModel): self
    {
        $instance = new self();
        $instance->faker = Faker::create();
        $instance->blogModel = $blogModel;

        return $instance;
    }

    /**
     * Override default attributes with custom values.
     *
     * @param  array  $attributes  Custom attribute overrides
     */
    public function withAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Set blog status to published.
     */
    public function published(): self
    {
        $this->attributes['status'] = 'published';
        $this->attributes['published_at'] = date('Y-m-d H:i:s');

        return $this;
    }

    /**
     * Set blog status to draft.
     */
    public function draft(): self
    {
        $this->attributes['status'] = 'draft';
        $this->attributes['published_at'] = null;

        return $this;
    }

    /**
     * Set number of blogs to create.
     *
     * @param  int  $count  Number of blogs
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Create blog(s) in the database.
     *
     * @param  int  $ownerId  Required owner user ID
     * @return int|array Returns single blog ID or array of IDs
     */
    public function create(int $ownerId): int|array
    {
        if ($this->count === 1) {
            return $this->createOne($ownerId);
        }

        $ids = [];
        for ($i = 0; $i < $this->count; $i++) {
            $ids[] = $this->createOne($ownerId);
        }

        return $ids;
    }

    /**
     * Create a single blog record.
     *
     * @param  int  $ownerId  Owner user ID
     * @return int Blog ID
     */
    private function createOne(int $ownerId): int
    {
        // generate unique slugs by appending random strings to avoid collisions
        $baseName = $this->attributes['blog_name'] ?? $this->faker->words(3, true);
        $baseSlug = $this->attributes['blog_slug'] ?? $this->faker->slug(3);

        $data = array_merge([
            'blog_name' => $baseName,
            'blog_slug' => $baseSlug.'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->sentence(10),
            'owner_id' => $ownerId,
            'status' => 'draft',
        ], $this->attributes);

        $this->blogModel->insert($data);

        return $this->blogModel->getInsertID();
    }
}
