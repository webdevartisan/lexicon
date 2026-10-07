<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * Another source with unsaved changes laid over it: one email's words in one
 * language, its layout choice, or a layout. The control panel renders drafts
 * through this to preview them, send them as tests and check them before
 * they are saved, so a draft is built by exactly the code that sends email.
 *
 * @phpstan-import-type Layout from EmailSource
 * @phpstan-import-type Content from EmailSource
 */
final class DraftEmailSource implements EmailSource
{
    /**
     * @param  array<string, array<string, Content>>  $contents  Class => language => words
     * @param  array<string, Layout>  $layouts  Slug => layout
     * @param  array<string, string>  $settings  Class => layout slug
     */
    public function __construct(
        private EmailSource $base,
        private array $contents = [],
        private array $layouts = [],
        private array $settings = [],
    ) {}

    public function layout(string $slug): ?array
    {
        return $this->layouts[$slug] ?? $this->base->layout($slug);
    }

    public function layouts(): array
    {
        $layouts = $this->layouts + $this->base->layouts();
        ksort($layouts);

        return $layouts;
    }

    public function layoutFor(string $mailable): string
    {
        return $this->settings[$mailable] ?? $this->base->layoutFor($mailable);
    }

    public function content(string $mailable, string $locale): ?array
    {
        return $this->contents[$mailable][$locale] ?? $this->base->content($mailable, $locale);
    }

    public function locales(string $mailable): array
    {
        $locales = array_values(array_unique(array_merge(
            $this->base->locales($mailable),
            array_map('strval', array_keys($this->contents[$mailable] ?? []))
        )));
        sort($locales);

        return $locales;
    }
}
