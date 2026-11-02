<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogSettingsModel;
use App\Models\BlogTextModel;
use App\Resources\BlogResource;

/**
 * The texts a blog owner writes for the front page, in each of the blog's languages.
 *
 * The blog's own language lives in the blog_settings columns. Any other
 * language is stored in blog_texts and falls back to the blog's own wording,
 * so a reader never sees less than before the owner translated anything.
 */
class FrontPageTexts
{
    /** Each text and its validation rule. */
    public const RULES = [
        'tagline' => 'max:160',
        'subtitle' => 'max:255',
        'about_text' => 'max:500',
        'founded_year' => 'max:20',
        'newsletter_heading' => 'max:255',
        'newsletter_text' => 'max:255',
    ];

    /** How each text is named to the person editing it. */
    public const LABELS = [
        'tagline' => 'tagline',
        'subtitle' => 'intro line',
        'about_text' => 'about text',
        'founded_year' => 'about label',
        'newsletter_heading' => 'newsletter heading',
        'newsletter_text' => 'newsletter line',
    ];

    public function __construct(
        private BlogSettingsModel $settings,
        private BlogTextModel $texts,
        private BlogCacheInvalidator $blogCache
    ) {}

    /**
     * The settings row with the texts readers of this language see.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @return array<string, mixed>
     */
    public function shownIn(int $blogId, array $settings, string $locale): array
    {
        if ($locale === BlogLocales::base($settings)) {
            return $settings;
        }

        $translated = array_intersect_key($this->texts->forLocale($blogId, $locale), self::RULES);

        return array_replace($settings, $translated);
    }

    /**
     * Each text as stored in this language, and what readers get while it is empty.
     *
     * The fallback is the blog's own wording in another language, or '' in the
     * blog's own language, where the theme supplies its default instead.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @return array<string, array{value: string, fallback: string}>
     */
    public function forEditing(int $blogId, array $settings, string $locale): array
    {
        $isBase = $locale === BlogLocales::base($settings);
        $stored = $isBase ? [] : $this->texts->forLocale($blogId, $locale);

        $fields = [];
        foreach (array_keys(self::RULES) as $field) {
            $own = trim((string) ($settings[$field] ?? ''));
            $fields[$field] = $isBase
                ? ['value' => $own, 'fallback' => '']
                : ['value' => (string) ($stored[$field] ?? ''), 'fallback' => $own];
        }

        return $fields;
    }

    /**
     * Store the texts that changed in one language and clear the blog's cached pages.
     *
     * Texts not passed are left alone, so a caller can save just the ones it shows.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @param  array<string, mixed>  $values  Text => new value, already validated
     * @return array<string, string> The texts that changed
     */
    public function save(BlogResource $blog, array $settings, string $locale, array $values): array
    {
        $current = array_map(
            static fn (array $field): string => $field['value'],
            $this->forEditing($blog->id(), $settings, $locale)
        );

        $changes = [];
        foreach (array_intersect_key($values, self::RULES) as $field => $value) {
            $value = trim((string) $value);
            if ($value !== $current[$field]) {
                $changes[$field] = $value;
            }
        }

        if ($changes === []) {
            return [];
        }

        if ($locale === BlogLocales::base($settings)) {
            $this->settings->updateForBlog($blog->id(), $changes);
        } else {
            // A cleared text has no row, so readers see the blog's own language or the theme's default
            $this->texts->saveForLocale($blog->id(), $locale, array_map(
                static fn (string $value): ?string => $value === '' ? null : $value,
                $changes
            ));
        }

        $this->blogCache->forgetBlog($blog->slug());

        return $changes;
    }
}
