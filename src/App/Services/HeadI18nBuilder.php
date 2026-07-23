<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds the language-related template globals: html lang and dir, canonical,
 * hreflang alternates and the Open Graph locale.
 *
 * All of it derives from the page's content locale and its locale set, never
 * from the URL. Deriving from the URL is what put dir="rtl" on English posts
 * under /ar/ and advertised five translations of a post that had one.
 *
 * Two callers: HeadI18nGlobals covers every page before the controller runs,
 * and a controller that later learns a narrower locale set rebuilds from here
 * so both use one implementation.
 */
final class HeadI18nBuilder
{
    /**
     * Region-qualified Open Graph locales. Anything absent falls back to a
     * doubled code, which is wrong often enough to be worth extending here as
     * locales are added.
     */
    private const OG_LOCALES = [
        'en' => 'en_US',
        'el' => 'el_GR',
        'ar' => 'ar_AE',
    ];

    public function __construct(private LocaleRegistry $registry) {}

    /**
     * @param  string  $path  Request path with the locale prefix already stripped
     * @param  string|null  $query  Raw query string, or null when there is none
     * @return array<string, mixed> Template globals, ready for addGlobals()
     */
    public function build(string $path, ?string $query = null): array
    {
        $current = LocaleState::get()->contentLocale;
        $suffix = ($path === '/' ? '' : $path).($query !== null && $query !== '' ? '?'.$query : '');
        $origin = $this->origin();

        // An empty set means the page never declared its languages, which is
        // correct for chrome-only pages such as the home page: they exist in
        // every locale that has strings.
        $localeSet = LocaleState::localeSet();
        if ($localeSet === []) {
            $localeSet = $this->registry->supported();
        }

        $pageDefault = in_array($this->registry->default(), $localeSet, true)
            ? $this->registry->default()
            : ($localeSet[0] ?? $current);

        return [
            'supportedLocales' => $this->registry->supported(),
            'defaultLocale' => $this->registry->default(),
            'currentLang' => $current,
            'isRtl' => $this->registry->isRtl($current) ? 'dir="rtl"' : '',

            'head' => [
                'canonicalUrl' => $origin.'/'.$current.$suffix,
                'alternates' => $this->alternates($localeSet, $origin, $suffix),
                'localeSet' => $localeSet,
                'xDefaultUrl' => $origin.'/'.$pageDefault.$suffix,
                'ogLocale' => self::OG_LOCALES[$current] ?? ($current.'_'.strtoupper($current)),
                'ogLocaleAlternates' => $this->ogAlternates($localeSet, $current),
            ],
        ];
    }

    /**
     * An alternate claims "these two URLs are translations of each other".
     * Emitting them for a page that exists in one language is a false claim, so
     * a single-member set emits none at all.
     *
     * @param  string[]  $localeSet
     * @return array<int, array{hreflang: string, href: string}>
     */
    private function alternates(array $localeSet, string $origin, string $suffix): array
    {
        if (count($localeSet) < 2) {
            return [];
        }

        $alternates = [];
        foreach ($localeSet as $lang) {
            $alternates[] = ['hreflang' => $lang, 'href' => $origin.'/'.$lang.$suffix];
        }

        return $alternates;
    }

    /**
     * @param  string[]  $localeSet
     * @return string[]
     */
    private function ogAlternates(array $localeSet, string $current): array
    {
        $out = [];
        foreach ($localeSet as $lang) {
            if ($lang === $current) {
                continue;
            }

            $out[] = self::OG_LOCALES[$lang] ?? ($lang.'_'.strtoupper($lang));
        }

        return $out;
    }

    private function origin(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');

        return ($https ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    }
}
