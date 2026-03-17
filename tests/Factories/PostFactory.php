<?php

declare(strict_types=1);

namespace Tests\Factories;

use App\Models\PostModel;
use function Pest\Faker\fake;

/**
 * Factory for creating test posts.
 * We generate realistic post data to catch edge cases in production.
 */
class PostFactory
{
    private PostModel $model;
    private array $attributes = [];
    
    public function __construct(PostModel $model)
    {
        $this->model = $model;
    }
    
    public static function new(PostModel $model): self
    {
        return new self($model);
    }
    
    public function withAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }
    
    /**
     * Create published post.
     */
    public function published(): self
    {
        $this->attributes['status'] = 'published';
        $this->attributes['published_at'] = date('Y-m-d H:i:s');
        return $this;
    }
    
    /**
     * Create draft post.
     */
    public function draft(): self
    {
        $this->attributes['status'] = 'draft';
        return $this;
    }
    
    /**
     * Create post and return ID.
     * We require author_id and blog_id to maintain referential integrity.
     *
     * @return int Post ID
     */
    public function create(): int
    {
        if (!isset($this->attributes['author_id']) || !isset($this->attributes['blog_id'])) {
            throw new \Exception('PostFactory requires author_id and blog_id');
        }
        
        $data = array_merge([
            'title' => fake()->sentence(6),
            'slug' => fake()->unique()->slug(),
            'content' => fake()->paragraphs(5, true),
            'status' => 'draft',
        ], $this->attributes);
        
        return $this->model->insert($data);
    }
    
    public function count(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->create();
        }
        return $ids;
    }
}
