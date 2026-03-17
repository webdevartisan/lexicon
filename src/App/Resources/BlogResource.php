<?php

namespace App\Resources;

use App\Models\BlogModel;

/**
 * BlogResource
 * We should keep authors() as a compatibility alias and migrate templates over time.
 */
class BlogResource
{
    private array $data;

    public function __construct(array $data, private BlogModel $model)
    {
        $this->data = $data;
    }

    // Accessors
    public function id(): int
    {
        return (int) $this->data['id'];
    }

    public function ownerId(): int
    {
        return (int) $this->data['owner_id'];
    }

    public function name(): string
    {
        return $this->data['blog_name'];
    }

    public function slug(): string
    {
        return $this->data['blog_slug'];
    }

    public function description(): string
    {
        return $this->data['description'];
    }

    public function status(): string
    {
        return $this->data['status'];
    }

    public function publishedAt(): ?string
    {
        return $this->data['published_at'];
    }

    public function archivedAt(): ?string
    {
        return $this->data['archived_at'];
    }

    // Lazy getters
    public function posts(): array
    {
        return $this->model->getBlogPosts($this->id());
    }

    /** We should prefer users() naming for clarity going forward. */
    public function users(): array
    {
        // should implement getBlogUsers($blogId) in BlogModel for DRY and performance.
        return $this->model->getBlogUsers($this->id());
    }

    public function availableUsers(): array
    {
        return $this->model->getAvailableUsers($this->id());
    }

    // Chainable setter
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function postCount(): int
    {
        return count($this->posts());
    }

    public function userCount(): int
    {
        return count($this->users());
    }

    // Convert back to array for views
    public function toArray(): array
    {
        return array_merge($this->data, [
            'post_count' => $this->postCount(),
            'user_count' => $this->userCount(),
        ]);
    }

    public function roleForUser(int $userId): ?string
    {
        foreach ($this->users() as $u) {
            if ((int) $u['user_id'] === $userId && (int) $u['is_active'] === 1) {
                return $u['role'] ?? 'author'; // safe default
            }
        }

        return null;
    }

    /**
     * Effective role, combining structural owner and collaborative blog_users roles.
     * - Returns 'owner' if user is the blog owner (blogs.owner_id).
     * - Otherwise returns the collaborative role from blog_users, or null.
     */
    public function effectiveRoleForUser(int $userId): ?string
    {
        // should always check structural ownership first.
        if ($this->ownerId() === $userId) {
            return 'owner';
        }

        return $this->roleForUser($userId); // editor/author/contributor/reviewer/viewer/null
    }
}
