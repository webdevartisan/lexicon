<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Adopts the account's interface language as the session locale.
 *
 * The session locale is what buildLocalizedUrl() and the LocalePrefixIntake
 * resolution chain read when a URL carries no prefix of its own, and it is
 * written from whatever URL the visitor last hit. That is the right source
 * until identity changes: someone who browsed /en/ as a guest and then signs in
 * with Greek stored keeps being sent to /en/ URLs, because the guest's value
 * still sits ahead of the preference in the chain and nothing re-derives it.
 *
 * Calling this at the two moments the preference becomes newly authoritative,
 * signing in and saving account settings, is what closes that gap.
 */
final class SessionLocaleSync
{
    public function __construct(private UserLocalePreference $preference) {}

    /**
     * Point the session locale at the user's stored language, if they have one.
     *
     * An account set to "auto" is stored as NULL and resolves to null here,
     * which leaves the session untouched on purpose so the interface keeps
     * following whatever language the URL asks for.
     *
     * Returns the language it adopted so a caller building a redirect can use
     * the same answer without asking the database a second time.
     *
     * @param  int  $userId  The user whose preference has just become authoritative
     * @return string|null The adopted locale, or null when the account has none
     */
    public function apply(int $userId): ?string
    {
        $locale = $this->preference->resolve($userId);

        if ($locale === null) {
            return null;
        }

        $_SESSION['locale'] = $locale;

        return $locale;
    }

    /**
     * Drop the language the session adopted for a reader who has now signed out.
     *
     * The mirror of apply(), and it matters for the same shared-browser reason:
     * left in place, the next visitor inherits the interface language of
     * whoever used the machine before them. Clearing it sends the next request
     * back through the normal guest chain of cookie, Accept-Language, default.
     */
    public function forget(): void
    {
        unset($_SESSION['locale']);
    }
}
