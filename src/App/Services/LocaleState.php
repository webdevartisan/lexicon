<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\LocaleContext;

/**
 * Request-scoped holder for the resolved locale context and the current page's
 * locale set.
 *
 * Pre-routing resolves the context before the DI container exists, so a static
 * holder is the one seam both phases can reach. Hanging it off the Request
 * object is not an option, since Framework classes must not reference App types.
 */
final class LocaleState
{
    private static ?LocaleContext $context = null;

    /** @var string[] */
    private static array $localeSet = [];

    public static function set(LocaleContext $context): void
    {
        self::$context = $context;
    }

    /**
     * The resolved context, falling back to the default locale when pre-routing
     * has not run: console commands, queued jobs and tests.
     */
    public static function get(): LocaleContext
    {
        if (self::$context === null) {
            return LocaleContext::forGuest(LocaleRegistry::instance()->default());
        }

        return self::$context;
    }

    /**
     * Record which locales the current page actually exists in.
     *
     * Controllers that know set this. An empty set means no page-specific
     * knowledge, which downstream reads as every supported locale, and that is
     * the right answer for chrome-only pages such as the home page.
     *
     * Codes the platform no longer serves are dropped here rather than at each
     * reader. The sets are built from stored translation rows, which outlive the
     * registry: a dropped locale leaves its rows behind, and unfiltered those
     * produced an hreflang and a switcher entry pointing at a URL that redirects
     * straight back. Filtering at the one place every set enters means no
     * consumer can be handed a locale that has no strings file.
     *
     * @param  string[]  $set
     */
    public static function setLocaleSet(array $set): void
    {
        $registry = LocaleRegistry::instance();

        self::$localeSet = array_values(array_unique(array_filter(
            array_map(
                static fn ($code): string => strtolower(trim((string) $code)),
                $set
            ),
            static fn (string $code): bool => $registry->isSupported($code)
        )));
    }

    /**
     * @return string[]
     */
    public static function localeSet(): array
    {
        return self::$localeSet;
    }

    /**
     * Clear the holder. Tests and long-running workers, so one request's locale
     * cannot leak into the next.
     */
    public static function reset(): void
    {
        self::$context = null;
        self::$localeSet = [];
    }
}
