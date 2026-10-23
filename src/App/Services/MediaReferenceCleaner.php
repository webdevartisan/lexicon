<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Resources\BlogResource;

/**
 * Empties the image slots that point at a file the library is about to lose,
 * so a deleted image leaves an empty banner or featured image rather than a broken one.
 */
final class MediaReferenceCleaner
{
    public function __construct(
        private readonly MediaUsageResolver $usage,
        private readonly PostModel $posts,
        private readonly BlogSettingsModel $settings,
        private readonly BlogCacheInvalidator $blogCache,
    ) {}

    /**
     * Clear every post and branding field that holds this URL. Images embedded in a
     * post body are left alone: cutting them out of someone's writing is not ours to do.
     *
     * @param  BlogResource  $blog  The blog the image belongs to
     * @param  string  $url  Public URL of the image
     */
    public function clear(BlogResource $blog, string $url): void
    {
        $brandingCleared = false;

        foreach ($this->usage->usages($blog->id(), $url) as $use) {
            $fields = array_fill_keys($use['fields'], null);

            if ($fields === []) {
                continue;
            }

            if ($use['type'] === 'post') {
                $this->posts->update($use['post_id'], $fields);

                continue;
            }

            $this->settings->updateForBlog($blog->id(), $fields);
            $brandingCleared = true;
        }

        // Branding shows on every page of the blog and on Discover.
        if ($brandingCleared) {
            $this->blogCache->blogChanged($blog->slug());
        }
    }
}
