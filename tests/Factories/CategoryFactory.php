<?php

declare(strict_types=1);

namespace Tests\Factories;

use App\Models\CategoryModel;

use function Pest\Faker\fake;

/**
 * Factory for creating test categories.
 *
 * Provides fluent interface for generating categories with
 * unique slugs and customizable attributes for integration tests.
 */
class CategoryFactory
{
    private CategoryModel $model;

    private array $attributes = [];

    private int $count = 1;

    /**
     * Create a new CategoryFactory instance.
     *
     * @param  CategoryModel  $model  The CategoryModel instance
     */
    public static function new(CategoryModel $model): self
    {
        $instance = new self();
        $instance->model = $model;

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
     * Set number of categories to create.
     *
     * @param  int  $count  Number of categories
     */
    public function count(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    /**
     * Create category/categories in the database.
     *
     * @return int|array Returns single category ID or array of IDs
     */
    public function create(): int|array
    {
        if ($this->count === 1) {
            return $this->createOne();
        }

        $ids = [];
        for ($i = 0; $i < $this->count; $i++) {
            $ids[] = $this->createOne();
        }

        return $ids;
    }

    /**
     * Create a single category record.
     *
     * @return int Category ID
     */
    private function createOne(): int
    {
        // generate unique slugs to avoid collisions in parallel tests
        $name = $this->attributes['name'] ?? fake()->words(2, true);
        $slug = $this->attributes['slug'] ?? fake()->slug(2).'-'.fake()->unique()->numberBetween(1000, 9999);

        $data = array_merge([
            'name' => $name,
            'slug' => $slug,
        ], $this->attributes);

        return $this->model->create($data);
    }
}
