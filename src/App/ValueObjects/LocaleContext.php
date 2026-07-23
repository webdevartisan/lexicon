<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * The two locales in play on any request.
 *
 * contentLocale is the language of the page's primary content and comes from
 * the URL prefix. chromeLocale is the language of the interface around it.
 * These were a single value before, which is why a locale prefix could set
 * dir="rtl" and lang="el" on a page whose text was English.
 */
final class LocaleContext
{
    public function __construct(
        public readonly string $contentLocale,
        public readonly string $chromeLocale
    ) {}

    /**
     * Anonymous visitors read in one language: the interface follows the content.
     *
     * This keeps guest pages single-language and cacheable at one entry per URL.
     * Signed-in visitors get their stored preference instead, and their traffic
     * is never cached, so that branch costs no cache entries.
     */
    public static function forGuest(string $contentLocale): self
    {
        return new self($contentLocale, $contentLocale);
    }
}
