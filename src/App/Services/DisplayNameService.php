<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;

/**
 * Keeps the denormalized users.display_name_cached column in sync.
 *
 * The cached name depends on both the user's name fields and their display
 * preference, and those are edited on two different settings pages, so the
 * recompute lives here rather than in either controller.
 */
class DisplayNameService
{
    public function __construct(
        private UserModel $users,
        private UserPreferencesModel $prefs,
        private UserProfileModel $profiles
    ) {}

    /**
     * Recompute and persist the cached display name for a user.
     *
     * Values are re-read rather than passed in so a caller that only touched
     * one side of the calculation still produces a correct result.
     *
     * @param  int  $userId  User whose cached name should be refreshed
     * @return string The newly persisted display name
     */
    public function refreshCached(int $userId): string
    {
        $user = $this->users->findById($userId) ?: [];
        $pref = $this->prefs->findOrCreate($userId) ?: [];
        $profile = $this->profiles->findOrCreate($userId) ?: [];

        $display = $this->compute(
            $pref['display_name_preference'] ?? 'username',
            $user['first_name'] ?? '',
            $user['last_name'] ?? '',
            $this->handle($profile['slug'] ?? null, $user['username'] ?? '')
        );

        $this->users->updateById($userId, ['display_name_cached' => $display]);

        return $display;
    }

    /**
     * Resolve the display name for a given preference and name set.
     *
     * @param  string  $preference  Either 'name' or 'username'
     * @param  string  $handle  The public handle, from handle()
     * @return string The resolved name, falling back to the handle when empty
     */
    public function compute(string $preference, string $first, string $last, string $handle): string
    {
        if ($preference === 'name') {
            $full = trim($first.' '.$last);

            return $full !== '' ? $full : $handle;
        }

        return $handle;
    }

    /**
     * The one-word handle readers see: the profile slug, or the username until
     * one is chosen. Mirrors the @mention join in CommentModel.
     */
    public function handle(?string $slug, string $username): string
    {
        return $slug !== null && $slug !== '' ? $slug : $username;
    }
}
