<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Templates\EmailSource;
use App\Mail\Templates\ShippedEmailSource;
use App\Models\EmailContentModel;
use App\Models\EmailLayoutModel;
use App\Models\EmailSettingModel;

/**
 * The emails in force: what was saved in the control panel, laid over the
 * shipped files.
 *
 * A layout row with a shipped slug replaces that file and is reported as
 * 'customized'; one with a new slug is 'custom'; anything with no row is
 * 'built-in'. The same goes for an English content row. Other languages exist
 * only as rows. Deleting a row is how it is reset.
 *
 * Everything is read in one go on first use and kept for the life of the
 * process: a subscriber fan-out renders the same email thousands of times and
 * the tables are small. Writes call flush().
 *
 * @phpstan-import-type Layout from EmailSource
 * @phpstan-import-type Content from EmailSource
 */
class EmailContentRepository implements EmailSource
{
    /** @var array{layouts: array<string, Layout>, settings: array<string, string>, contents: array<string, array<string, Content>>}|null */
    private ?array $loaded = null;

    public function __construct(
        private ShippedEmailSource $shipped,
        private EmailLayoutModel $layoutRows,
        private EmailSettingModel $settingRows,
        private EmailContentModel $contentRows,
    ) {}

    /**
     * The shipped files on their own, for resets and as the fallback.
     */
    public function shipped(): ShippedEmailSource
    {
        return $this->shipped;
    }

    /**
     * Forget what was read, so the next lookup sees a write just made.
     */
    public function flush(): void
    {
        $this->loaded = null;
    }

    public function layout(string $slug): ?array
    {
        return $this->load()['layouts'][$slug] ?? $this->shipped->layout($slug);
    }

    public function layouts(): array
    {
        $layouts = $this->load()['layouts'] + $this->shipped->layouts();
        ksort($layouts);

        return $layouts;
    }

    public function layoutFor(string $mailable): string
    {
        return $this->load()['settings'][$mailable] ?? $this->shipped->layoutFor($mailable);
    }

    public function content(string $mailable, string $locale): ?array
    {
        return $this->load()['contents'][$mailable][$locale] ?? $this->shipped->content($mailable, $locale);
    }

    public function locales(string $mailable): array
    {
        $locales = array_values(array_unique(array_merge(
            $this->shipped->locales($mailable),
            array_map('strval', array_keys($this->load()['contents'][$mailable] ?? []))
        )));
        sort($locales);

        return $locales;
    }

    /**
     * An email's saved words in one language, without the shipped English under it.
     *
     * @return Content|null
     */
    public function storedContent(string $mailable, string $locale): ?array
    {
        return $this->load()['contents'][$mailable][$locale] ?? null;
    }

    /**
     * @return array{layouts: array<string, Layout>, settings: array<string, string>, contents: array<string, array<string, Content>>}
     */
    private function load(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $layouts = [];
        foreach ($this->layoutRows->allRows() as $row) {
            $slug = (string) $row['slug'];
            $layouts[$slug] = [
                'slug' => $slug,
                'name' => (string) $row['name'],
                'html' => (string) $row['html'],
                'primary_color' => (string) $row['primary_color'],
                'background_color' => (string) $row['background_color'],
                'support_email' => (string) $row['support_email'],
                'company_address' => (string) $row['company_address'],
                'source' => $this->shipped->layout($slug) === null ? 'custom' : 'customized',
            ];
        }

        $settings = [];
        foreach ($this->settingRows->allRows() as $row) {
            $settings[(string) $row['mailable_class']] = (string) $row['layout_slug'];
        }

        $contents = [];
        foreach ($this->contentRows->allRows() as $row) {
            $mailable = (string) $row['mailable_class'];
            $locale = (string) $row['locale'];
            $contents[$mailable][$locale] = [
                'subject' => (string) $row['subject'],
                'preheader' => (string) $row['preheader'],
                'body' => (string) $row['body'],
                'footer_note' => (string) $row['footer_note'],
                'repeat' => $row['repeat_html'] === null ? null : (string) $row['repeat_html'],
                'source' => $this->shipped->content($mailable, $locale) === null ? 'custom' : 'customized',
                'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            ];
        }

        return $this->loaded = ['layouts' => $layouts, 'settings' => $settings, 'contents' => $contents];
    }
}
