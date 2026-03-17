<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\UsernameHelper;
use App\Models\ReservedSlugModel;
use App\Models\UserModel;

/**
 * UsernameValidationService
 *
 * Validates username availability with fuzzy matching for spam prevention.
 * Checks against reserved words (with normalization, substring matching,
 * and Levenshtein distance) and existing users.
 */
class UsernameValidationService
{
    /**
     * Cached reserved words from database.
     *
     * Loaded once per request to avoid repeated queries during validation.
     */
    private ?array $reservedWordsCache = null;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private UserModel $users,
        private ReservedSlugModel $reservedSlugs
    ) {}

    /**
     * Check whether a username is available for assignment.
     *
     * Applies multiple validation layers:
     * 1. Exact match against normalized reserved words (fast path)
     * 2. Substring matching to catch variations like "theadmin", "admin123"
     * 3. Levenshtein distance to catch typos within 2 characters
     * 4. Check against existing usernames
     *
     * @param  string  $username  Username to check
     * @param  int|null  $ignoreUserId  User ID to exclude from check (for profile updates)
     * @return bool True if available
     */
    public function isAvailable(string $username, ?int $ignoreUserId = null): bool
    {
        $normalized = UsernameHelper::normalize($username);

        // Step 1: Quick exact match check against reserved slugs (fail fast)
        if ($this->reservedSlugs->isReserved($normalized)) {
            return false;
        }

        // Step 2: Load reserved words cache if not already loaded
        if ($this->reservedWordsCache === null) {
            $this->reservedWordsCache = $this->reservedSlugs->getAll();
        }

        // Step 3: Check fuzzy matches (substring and Levenshtein)
        if ($this->isFuzzyMatch($normalized)) {
            return false;
        }

        // Step 4: Check against existing users
        return $this->users->isUsernameUnique($username, $ignoreUserId);
    }

    /**
     * Check if normalized username fuzzy-matches any reserved word.
     *
     * Uses two detection methods:
     * - Substring matching: Catches "theadmin", "superadmin", "admin_user"
     * - Levenshtein distance ≤2: Catches "adm1n", "admln", "adminm"
     *
     * @param  string  $normalized  Normalized username
     * @return bool True if fuzzy match found
     */
    private function isFuzzyMatch(string $normalized): bool
    {
        foreach ($this->reservedWordsCache as $reserved) {
            // Substring check catches common prefix/suffix spam patterns
            if (str_contains($normalized, $reserved)) {
                return true;
            }

            // Levenshtein catches typos and character substitutions
            if (levenshtein($normalized, $reserved) <= 2) {
                return true;
            }
        }

        return false;
    }
}
