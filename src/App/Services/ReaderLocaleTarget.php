<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Where a reader should land when their own language disagrees with the URL's.
 *
 * Two kinds of page sit behind a locale prefix. A blog post or a static page
 * exists in particular languages and the prefix is part of its identity, so a
 * shared link has to keep pointing at the language it was shared in. The
 * platform's own pages exist identically in every language, and there the
 * prefix means nothing more than "what the last visitor happened to be
 * reading" - which on a shared browser is the previous person's choice rather
 * than this reader's.
 *
 * Only the second kind follows the account preference. Anything this class has
 * not been taught about keeps its URL, so an unrecognised path degrades to the
 * existing behaviour: getting that wrong costs one language switch, while
 * getting it wrong in the other direction would send a shared link somewhere
 * its author never meant.
 */
final class ReaderLocaleTarget
{
    /**
     * First path segments whose pages exist in every language.
     *
     * The empty string is the site root. Deliberately excluded: '/blog' and the
     * static page slugs, whose controllers resolve real per-locale content.
     */
    private const LOCALE_AGNOSTIC = ['', 'home', 'blogs', 'profile', 'dashboard', 'library', 'admin'];

    public function __construct(private LocaleRegistry $registry) {}

    /**
     * Re-point a redirect target at the reader's language when the page has no
     * language of its own.
     *
     * @param  string  $target  Site-local path, with or without a locale prefix
     * @param  string|null  $locale  The reader's stored language, or null when they have none
     * @return string The path to redirect to
     */
    public function resolve(string $target, ?string $locale): string
    {
        if ($locale === null || !$this->registry->isSupported($locale)) {
            return $target;
        }

        $path = parse_url($target, PHP_URL_PATH) ?: '/';
        $query = parse_url($target, PHP_URL_QUERY);

        $segments = array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== ''
        ));

        // Drop the locale prefix if the target carries one; what is left is the
        // page itself, which is what decides whether the prefix may change.
        if (isset($segments[0]) && $this->registry->isSupported(strtolower($segments[0]))) {
            array_shift($segments);
        }

        if (!in_array($segments[0] ?? '', self::LOCALE_AGNOSTIC, true)) {
            return $target;
        }

        $rest = $segments === [] ? '' : '/'.implode('/', $segments);

        return '/'.$locale.$rest.($query !== null && $query !== '' ? '?'.$query : '');
    }
}
