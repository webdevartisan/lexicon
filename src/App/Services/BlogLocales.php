<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostTranslationModel;

/**
 * The languages a blog's own pages exist in.
 */
class BlogLocales
{
    public function __construct(private PostTranslationModel $translations) {}

    /**
     * The blog's base language first, plus any language a post has been
     * translated into once the blog opts into translations.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     * @return string[]
     */
    public function for(int $blogId, array $settings): array
    {
        $default = self::base($settings);

        if (empty($settings['translations_enabled'])) {
            return [$default];
        }

        return array_values(array_unique(
            array_merge([$default], $this->translations->localesForBlog($blogId))
        ));
    }

    /**
     * The blog's own language.
     *
     * @param  array<string, mixed>  $settings  Blog settings row
     */
    public static function base(array $settings): string
    {
        return (string) ($settings['default_locale'] ?? 'en');
    }
}
