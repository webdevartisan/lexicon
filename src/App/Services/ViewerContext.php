<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\UserProfileModel;

/**
 * Builds the logged-in reader summary shown in theme mastheads.
 *
 * The blog pages and the auth-nav endpoint both need the same shape, so it
 * lives here instead of being rebuilt in each controller.
 */
final class ViewerContext
{
    public function __construct(
        private BlogModel $blogs,
        private UserProfileModel $profiles
    ) {}

    /**
     * Summary of the current user for masthead rendering.
     *
     * @return array{name: string, avatar_url: string|null, is_reader: bool}|null
     *         Null when nobody is logged in.
     */
    public function current(): ?array
    {
        if (!auth()->check()) {
            return null;
        }

        $user = auth()->user();

        return [
            'name' => $user['display_name_cached'] ?? ($user['username'] ?? ''),
            'avatar_url' => $this->profiles->getProfileAvatar((int) $user['id'])['avatar_url'] ?? null,
            // Readers get "My Reading Hub" in the masthead menu, creators "Dashboard"
            'is_reader' => $this->blogs->userIsReaderOnly((int) $user['id']),
        ];
    }
}
