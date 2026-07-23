<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Decides whether a requested locale is one this page genuinely exists in.
 *
 * Every page has a locale set. A request for a locale outside that set is not a
 * translation, it is a duplicate wearing the wrong lang attribute, so it
 * redirects rather than renders.
 */
final class ContentLocaleResolver
{
    public function __construct(private LocaleRegistry $registry) {}

    /**
     * Where this request should go, or null to serve it as asked.
     *
     * Callers issue a 302 rather than a 301. A locale set changes the moment an
     * author adds a translation, and permanent redirects sit in browser caches
     * with no way to recall them.
     *
     * @param  string[]  $localeSet  Locales this page exists in
     * @param  string  $requested  Locale taken from the URL prefix
     * @param  string  $default  The page's own default locale
     * @return string|null Target locale, or null when the request is already right
     */
    public function redirectTarget(array $localeSet, string $requested, string $default): ?string
    {
        $set = array_values(array_unique(array_filter(
            array_map(static fn ($code): string => strtolower(trim((string) $code)), $localeSet),
            fn (string $code): bool => $this->registry->isSupported($code)
        )));

        // Knowing nothing about a page's languages is not the same as knowing it
        // has none, so serve rather than invent a redirect target.
        if ($set === []) {
            return null;
        }

        if (in_array(strtolower(trim($requested)), $set, true)) {
            return null;
        }

        $default = strtolower(trim($default));

        // A blog whose default_locale still names a dropped locale would send
        // readers nowhere, so fall back to something the page really has.
        return in_array($default, $set, true) ? $default : $set[0];
    }
}
