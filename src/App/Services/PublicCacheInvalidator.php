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
     * Every cached blog page, all locales.
     *
     * Listings bake in the set of visible posts, so a post appearing or
     * disappearing has to clear them or it stays invisible until the TTL runs
     * out. Which blog a change belongs to isn't part of the cache key, so this
     * is necessarily broad.
     */
    public function purgeBlogSurfaces(): void
    {
        cache()->deletePattern('*:GET:/blog/*');
    }

    /**
     * Blog surfaces that render author names, plus one public profile page.
     *
     * Author names and links are baked into cached blog pages, so renaming or
     * hiding an author has to clear them. Which blogs an author has posted to
     * isn't tracked, so this purges all blog surfaces, which is acceptable
     * because identity changes are rare.
     *
     * @param  string  $handle  The handle whose profile page is cached, the old one after a rename
     */
    public function purgeAuthorSurfaces(string $handle): void
    {
        $this->purgeBlogSurfaces();

        // Threads cache the rendered author name under their own keys, not a
        // page path, so the pattern above cannot reach them.
        fragment()->forgetPattern('post-comments:*');

        cache()->deletePattern("*:GET:/profile/{$handle}*");
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
