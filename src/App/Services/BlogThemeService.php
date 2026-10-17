<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;

/**
 * Switches a blog's theme, for the Appearance page and the "Pick a look" step
 * of Start your blog.
 */
final class BlogThemeService
{
    public function __construct(
        private BlogModel $blogs,
        private BlogSettingsModel $settings,
        private ThemeService $themes,
    ) {}

    /**
     * Make a theme the blog's active one.
     *
     * @param  int  $actorId  Who made the change, for the audit log
     * @return bool False when the theme isn't installed or the blog is gone
     */
    public function activate(int $blogId, string $key, int $actorId, ?string $ip = null): bool
    {
        $blog = $this->blogs->getBlog($blogId);
        if (!$blog || !$this->themes->isValidKey($key)) {
            return false;
        }

        $current = $this->settings->findByBlogId($blogId);
        if (($current['theme'] ?? null) === $key) {
            return true;
        }

        if ($current === null) {
            $this->settings->createDefaultForBlog($blogId, ['theme' => $key]);
        } else {
            $this->settings->updateForBlog($blogId, ['theme' => $key]);
        }

        // The theme decides every public page of the blog, so purge them all.
        cache()->deletePattern('*:GET:/blog/'.$blog->slug().'*');

        audit()->log($actorId, 'blog.theme_changed', 'blog', $blogId, ['from' => $current['theme'] ?? null, 'to' => $key], $ip);

        return true;
    }
}
