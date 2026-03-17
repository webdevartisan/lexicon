<?php

declare(strict_types=1);

namespace Tests\Factories;

use App\Models\CommentModel;
use function Pest\Faker\fake;

/**
 * Factory for creating test comments.
 * 
 * Provides fluent interface for generating comments with
 * customizable attributes for integration tests.
 */
class CommentFactory
{
    private CommentModel $model;
    private array $attributes = [];

    /**
     * Create a new CommentFactory instance.
     * 
     * @param CommentModel $model The CommentModel instance
     * @return self
     */
    public static function new(CommentModel $model): self
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
     * Create comment in database.
     * 
     * Requires post_id and user_id to maintain referential integrity.
     * 
     * @param int $postId Post ID
     * @param int $userId User ID
     * @return int Comment ID
     */
    public function create(int $postId, int $userId): int
    {
        $data = array_merge([
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => faker()->paragraph(),
        ], $this->attributes);

        return $this->model->insert($data);
    }

    /**
     * Create multiple comments.
     * 
     * @param int $count Number of comments to create
     * @return array Array of comment IDs
     */
    public function count(int $count): array
    {
        $postId = $this->attributes['post_id'] ?? null;
        $userId = $this->attributes['user_id'] ?? null;
        
        if (!$postId || !$userId) {
            throw new \Exception('CommentFactory requires post_id and user_id in attributes');
        }
        
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->create($postId, $userId);
        }
        return $ids;
    }
}
