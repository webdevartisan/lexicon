<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use RuntimeException;

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
        private UserPreferencesModel $prefs
    ) {}

    /**
     * Recompute and persist the cached display name for a user.
     *
     * Values are re-read rather than passed in so a caller that only touched
     * one side of the calculation still produces a correct result.
     *
     * @param  int  $userId  User whose cached name should be refreshed
     * @return string The newly persisted display name
     *
     * @throws RuntimeException If the user no longer exists
     */
    public function refreshCached(int $userId): string
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new RuntimeException("Cannot refresh the display name of missing user {$userId}.");
        }

        $display = $this->compute(
            $this->prefs->findOrCreate($userId)['display_name_preference'],
            $user['first_name'] ?? '',
            $user['last_name'] ?? '',
            (string) $user['handle']
        );

        $this->users->updateById($userId, ['display_name_cached' => $display]);

        return $display;
    }

    /**
     * Resolve the display name for a given preference and name set.
     *
     * @param  string  $preference  Either 'name' or 'handle'
     * @param  string  $handle  The account handle
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
}
