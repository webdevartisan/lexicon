<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserPreferencesModel;

/**
 * The signed-in reader's chosen interface language.
 *
 * Guests never have one, which is what keeps anonymous pages single-language and
 * cacheable at one entry per URL. Signed-in traffic skips the full-page cache
 * entirely, so personalising the chrome there costs no cache entries.
 *
 * This exists as its own class because LocalePrefixIntake, its only production
 * caller, is static and ends in exit(), so the decision would otherwise have no
 * test coverage at all.
 */
final class UserLocalePreference
{
    public function __construct(
        private LocaleRegistry $registry,
        private UserPreferencesModel $preferences
    ) {}

    /**
     * The user's stored interface language, if they have a usable one.
     *
     * Revalidated on every read rather than trusted: normalize() returns null
     * for anything the platform no longer serves, so a locale removed from the
     * registry after someone saved it simply stops applying.
     *
     * @param  int|null  $userId  Signed-in user, or null for a guest
     * @return string|null Supported locale code, or null to follow the content
     */
    public function resolve(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return $this->registry->normalize($this->preferences->getLocale($userId));
    }
}
