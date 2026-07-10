<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Purges full-page cache entries for public surfaces after admin edits.
 *
 * Full-page cache keys follow "{locale}:{method}:{path}?{query}" (CacheKey),
 * so patterns match every locale variant of a path at once. Without this,
 * front page edits would sit invisible until the TTL ran out.
 */
class PublicCacheInvalidator
{
    /**
     * Front page, all locales, plus the cached stats strip numbers.
     */
    public function purgeHome(): void
    {
        cache()->deletePattern('*:GET:/');
        cache()->delete('front:stats');
    }

    /**
     * Explore page including every search/tab/pagination variant.
     */
    public function purgeExplore(): void
    {
        cache()->deletePattern('*:GET:/blogs*');
    }

    /**
     * One static page, all locales.
     */
    public function purgePage(string $slug): void
    {
        cache()->deletePattern("*:GET:/{$slug}*");
    }

    /**
     * Everything public at once, for site content edits that render on every
     * page (footer, sidebar, contact details).
     */
    public function purgeAllPublic(): void
    {
        $this->purgeHome();
        $this->purgeExplore();

        foreach (['about', 'contact', 'privacy', 'terms', 'cookies', 'getting-started'] as $slug) {
            $this->purgePage($slug);
        }
    }
}
