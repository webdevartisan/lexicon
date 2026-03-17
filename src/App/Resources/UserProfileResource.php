<?php

namespace App\Resources;

/**
 * Read-only view of a user profile row plus some user metadata.
 *
 * We should keep this class free of DB concerns and only expose getters.
 */
class UserProfileResource
{
    /** @var array<string,mixed> */
    private array $data;

    /**
     * @param  array<string,mixed>  $data  Raw DB row data.
     */
    public function __construct(array $data)
    {
        // should trust that the repository passes a complete, sanitized row.
        $this->data = $data;
    }

    /** User ID owning this profile. */
    public function userId(): int
    {
        return (int) $this->data['user_id'];
    }

    /** Public profile slug used in /profile/{slug}. */
    public function slug(): string
    {
        return (string) $this->data['slug'];
    }

    /** Whether this profile is visible publicly. */
    public function isPublic(): bool
    {
        return (bool) $this->data['is_public'];
    }

    /** Short text bio. */
    public function bio(): ?string
    {
        return $this->data['bio'] ?? null;
    }

    /** Avatar URL (already validated/sanitized server-side). */
    public function avatarUrl(): ?string
    {
        return $this->data['avatar_url'] ?? null;
    }

    public function location(): ?string
    {
        return $this->data['location_text'] ?? null;
    }

    /** Cached display name for listing (from users.display_name_cached). */
    public function displayName(): ?string
    {
        return $this->data['display_name_cached'] ?? null;
    }

    /** Posts count for this user. */
    public function postsCount(): int
    {
        return (int) ($this->data['posts_count'] ?? 0);
    }

    /** Comments received count. */
    public function commentsReceivedCount(): int
    {
        return (int) ($this->data['comments_received_count'] ?? 0);
    }

    /** Preference for what to show as primary display name. */
    public function displayNamePreference(): ?string
    {
        return $this->data['display_name_preference'] ?? null;
    }

    /** Convert back to array for views or APIs. */
    public function toArray(): array
    {
        return $this->data;
    }
}
