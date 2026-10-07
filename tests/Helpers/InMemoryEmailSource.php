<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Mail\Templates\EmailSource;
use App\Mail\Templates\ShippedEmailSource;

/**
 * The shipped emails with some words, layouts or layout choices laid over
 * them in memory, for tests that need a translation or an edit without a
 * database.
 *
 * Contents are given per Mailable class and language; a missing field is
 * taken from the shipped English, so a test only writes what it is about.
 *
 * @phpstan-import-type Layout from EmailSource
 * @phpstan-import-type Content from EmailSource
 */
final class InMemoryEmailSource implements EmailSource
{
    private ShippedEmailSource $shipped;

    /**
     * @param  array<string, array<string, array<string, string|null>>>  $contents  Class => language => fields
     * @param  array<string, array<string, string>>  $layouts  Slug => fields
     * @param  array<string, string>  $settings  Class => layout slug
     */
    public function __construct(
        private array $contents = [],
        private array $layouts = [],
        private array $settings = [],
    ) {
        $this->shipped = new ShippedEmailSource();
    }

    public function layout(string $slug): ?array
    {
        if (!isset($this->layouts[$slug])) {
            return $this->shipped->layout($slug);
        }

        /** @var Layout */
        return $this->layouts[$slug] + ($this->shipped->layout($slug) ?? $this->shipped->layout(self::DEFAULT_LAYOUT) ?? []) + ['slug' => $slug, 'source' => 'custom'];
    }

    public function layouts(): array
    {
        $layouts = $this->shipped->layouts();

        foreach (array_keys($this->layouts) as $slug) {
            $layouts[$slug] = $this->layout($slug) ?? [];
        }

        /** @var array<string, Layout> */
        return $layouts;
    }

    public function layoutFor(string $mailable): string
    {
        return $this->settings[$mailable] ?? $this->shipped->layoutFor($mailable);
    }

    public function content(string $mailable, string $locale): ?array
    {
        if (!isset($this->contents[$mailable][$locale])) {
            return $this->shipped->content($mailable, $locale);
        }

        /** @var Content */
        return $this->contents[$mailable][$locale] + ($this->shipped->content($mailable, 'en') ?? []) + ['source' => 'custom', 'updated_at' => null];
    }

    public function locales(string $mailable): array
    {
        $locales = array_values(array_unique(array_merge($this->shipped->locales($mailable), array_keys($this->contents[$mailable] ?? []))));
        sort($locales);

        return $locales;
    }
}
