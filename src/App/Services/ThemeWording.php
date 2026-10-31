<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogTextModel;
use App\Resources\BlogResource;

/**
 * An owner's own wording for the theme's texts, per blog and language.
 *
 * Only the texts the theme lists in its theme.json can be reworded. Wording is
 * kept per theme, so switching themes and back finds it again.
 */
class ThemeWording
{
    public const MAX_LENGTH = 160;

    public function __construct(
        private BlogTextModel $texts,
        private ThemeService $themes,
        private BlogCacheInvalidator $blogCache
    ) {}

    /**
     * The wording one blog shows in one language with the given theme.
     */
    public function load(int $blogId, ?string $theme, string $locale): BlogWording
    {
        $editable = $this->themes->editableTexts($theme);
        if ($editable === []) {
            return BlogWording::none();
        }

        $own = array_intersect_key($this->texts->forLocale($blogId, $locale), array_flip($editable));

        return new BlogWording($own, $editable);
    }

    /**
     * The wording for the page being drawn: the theme it is drawn with, in the reader's interface
     * language, which is the language theme texts are shown in. A preview of another theme shows
     * that theme's wording but offers none of it for editing, since saving goes to the blog's own.
     */
    public function forPage(int $blogId, ?string $blogTheme): BlogWording
    {
        $shown = $this->themes->getActive();
        $wording = $this->load($blogId, $shown, LocaleState::get()->chromeLocale);

        return $shown === $blogTheme ? $wording : $wording->fixed();
    }

    /**
     * Whether the theme lets an owner reword this text.
     */
    public function isEditable(?string $theme, string $key): bool
    {
        return in_array($key, $this->themes->editableTexts($theme), true);
    }

    /**
     * Store the wording that changed in one language and clear the blog's cached pages.
     *
     * @param  array<string, string>  $values  Translation key => wording, already validated; '' removes the text
     * @return array<string, string|null> The wording that changed, null where it follows the theme again
     */
    public function save(BlogResource $blog, ?string $theme, string $locale, array $values): array
    {
        $current = $this->texts->forLocale($blog->id(), $locale);
        $themes = new TranslationService($locale);

        $changes = [];
        foreach ($values as $key => $value) {
            // Worded exactly as the theme words it, the text follows the theme, so later theme updates reach it
            $own = $value === $themes->translate($key) ? null : $value;
            if ($this->isEditable($theme, $key) && $own !== ($current[$key] ?? null)) {
                $changes[$key] = $own;
            }
        }

        if ($changes !== []) {
            $this->texts->saveForLocale($blog->id(), $locale, $changes);
            $this->blogCache->forgetBlog($blog->slug());
        }

        return $changes;
    }
}
