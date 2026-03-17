<?php

declare(strict_types=1);

namespace Tests\Factories;

use App\Models\TagModel;
use function Pest\Faker\fake;

/**
 * Factory for creating test tags.
 * 
 * Provides fluent interface for generating tags with
 * unique slugs for integration tests.
 */
class TagFactory
{
    private TagModel $model;
    private array $attributes = [];

    /**
     * Create a new TagFactory instance.
     * 
     * @param TagModel $model The TagModel instance
     * @return self
     */
    public static function new(TagModel $model): self
    {
        $instance = new self();
        $instance->model = $model;
        return $instance;
    }

    /**
     * Override default attributes with custom values.
     * 
     * @param array $attributes Custom attribute overrides
     * @return self
     */
    public function withAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Create tag in database.
     * 
     * @return int Tag ID
     */
    public function create(): int
    {
        $name = $this->attributes['name'] ?? faker()->word();
        $slug = $this->attributes['slug'] ?? faker()->slug(1) . '-' . faker()->unique()->numberBetween(1000, 9999);
        
        $data = array_merge([
            'name' => $name,
            'slug' => $slug,
        ], $this->attributes);

        return $this->model->insert($data);
    }

    /**
     * Create multiple tags.
     * 
     * @param int $count Number of tags to create
     * @return array Array of tag IDs
     */
    public function count(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->create();
        }
        return $ids;
    }
}
