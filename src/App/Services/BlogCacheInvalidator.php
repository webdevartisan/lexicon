<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Forgets the cached pieces of a blog once its data changes.
 *
 * Models call this after a write that went through, so the keys a blog is
 * cached under are named in one place instead of in every model that touches
 * blogs.
 */
class BlogCacheInvalidator
{
    /**
     * Drop every cached public copy of one blog: its pages and the looked-up row.
     *
     * Anything that changes a blog without going through BlogModel::update()
     * must call this, or the cached row keeps answering "published" and the
     * blog stays up. The home page is listed on its own because
     * "/blog/{slug}/*" does not match "/blog/{slug}".
     */
    public function forgetBlog(string $slug): void
    {
        cache()->deletePattern("*:GET:/blog/{$slug}");
        cache()->deletePattern("*:GET:/blog/{$slug}?*");
        cache()->deletePattern("*:GET:/blog/{$slug}/*");
        fragment()->forget('blog-by-slug:'.$slug, false);
    }

    /**
     * A blog was edited. A new slug is cleared too, in case its address was
     * cached as "not found" before the rename.
     */
    public function blogChanged(string $slug, ?string $newSlug = null): void
    {
        $this->forgetBlog($slug);

        if ($newSlug !== null && $newSlug !== $slug) {
            $this->forgetBlog($newSlug);
        }

        // Discover lists the blog.
        $this->publicCache()->purgeDiscover();
    }

    /**
     * A blog was deleted.
     */
    public function blogRemoved(string $slug): void
    {
        $this->forgetBlog($slug);
        $this->publicCache()->purgeDiscover();
    }

    /**
     * A blog was created; drop any cached 404 for its slug from before it existed.
     */
    public function blogCreated(string $slug): void
    {
        fragment()->forget('blog-by-slug:'.$slug, false);
    }

    /**
     * The blog's cached settings row.
     */
    public function forgetSettings(int $blogId): void
    {
        fragment()->forget('blog-settings:'.$blogId, false);
    }

    /**
     * A category was renamed or edited.
     *
     * Discover prints each post's category, and the blog's category list
     * carries the name.
     */
    public function categoryChanged(int $blogId): void
    {
        $this->publicCache()->purgeDiscover();
        $this->forgetPublishedCategories($blogId);
    }

    /**
     * A category was deleted, so every blog page that linked to it is stale.
     */
    public function categoryRemoved(int $blogId): void
    {
        cache()->deletePattern('*:GET:/blog*');
        $this->forgetPublishedCategories($blogId);
    }

    /**
     * The blog's list of categories that hold published posts.
     */
    public function forgetPublishedCategories(int $blogId): void
    {
        if ($blogId > 0) {
            fragment()->forget('blog-pubcats:'.$blogId, false);
        }
    }

    private function publicCache(): PublicCacheInvalidator
    {
        return app(PublicCacheInvalidator::class);
    }
}
