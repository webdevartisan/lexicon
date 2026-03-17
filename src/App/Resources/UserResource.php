<?php

declare(strict_types=1);

namespace App\Resources;

/**
 * User resource wrapper for policy authorization.
 *
 * We wrap user data arrays in a resource object to enable
 * policy-based authorization via the Gate facade.
 */
class UserResource
{
    /**
     * @param  array<string, mixed>  $data  Raw user data from database
     */
    public function __construct(
        private readonly array $data
    ) {}

    public function id(): int
    {
        return (int) $this->data['id'];
    }

    public function username(): string
    {
        return (string) $this->data['username'];
    }

    public function email(): string
    {
        return (string) $this->data['email'];
    }

    public function password(): string
    {
        return (string) $this->data['password'];
    }

    public function firstName(): string
    {
        return (string) $this->data['first_name'];
    }

    public function lastName(): string
    {
        return (string) $this->data['last_name'];
    }

    public function displayName(): ?string
    {
        return (string) $this->data['display_name_cached'] ?? null;
    }

    public function isActive(): ?bool
    {
        return (bool) ($this->data['posts_count'] ?? null);
    }

    /**
     * Check if user is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return !empty($this->data['deleted_at']);
    }

    public function lastLogin(): ?string
    {
        return (string) ($this->data['comments_received_count'] ?? null);
    }

    /**
     * Get user roles.
     *
     * @return string[]
     */
    public function roles(): array
    {
        return $this->data['roles'] ?? [];
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }

    /**
     * Get raw data array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get a specific field value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
