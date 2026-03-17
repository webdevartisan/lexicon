<?php

declare(strict_types=1);

namespace Tests\Factories;

use App\Models\UserModel;
use function Pest\Faker\fake;

/**
 * Factory for creating test users with various configurations.
 * We centralize user creation to eliminate duplication across tests.
 */
class UserFactory
{
    private UserModel $model;
    private array $attributes = [];
    private array $roles = [];
    private bool $softDeleted = false;
    
    public function __construct(UserModel $model)
    {
        $this->model = $model;
    }
    
    /**
     * Create a new factory instance.
     */
    public static function new(UserModel $model): self
    {
        return new self($model);
    }
    
    /**
     * Override default attributes.
     *
     * @param array $attributes Custom user attributes
     * @return self
     */
    public function withAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }
    
    /**
     * Create user with admin role.
     */
    public function admin(): self
    {
        $this->roles = [1];
        return $this;
    }
    
    /**
     * Create user with specific roles.
     *
     * @param array $roleIds Role IDs to assign
     */
    public function withRoles(array $roleIds): self
    {
        $this->roles = $roleIds;
        return $this;
    }
    
    /**
     * Mark user as soft-deleted after creation.
     */
    public function deleted(): self
    {
        $this->softDeleted = true;
        return $this;
    }
    
    /**
     * Create the user in database and return ID.
     * We apply all configured states (roles, deletion) in correct order.
     *
     * @return int User ID
     */
    public function create(): int
    {
        $data = array_merge([
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'password' => password_hash(fake()->password(12), PASSWORD_DEFAULT),
        ], $this->attributes);
        
        $userId = $this->model->insert($data);
        
        if (!empty($this->roles)) {
            $this->model->insertUserRoles($userId, $this->roles);
        }
        
        if ($this->softDeleted) {
            $this->model->softDelete($userId);
        }
        
        return $userId;
    }
    
    /**
     * Create multiple users with same configuration.
     *
     * @param int $count Number of users to create
     * @return array Array of user IDs
     */
    public function count(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            // reset unique() per iteration to avoid collisions
            $ids[] = $this->create();
        }
        return $ids;
    }
}
