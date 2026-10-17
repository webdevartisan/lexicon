<?php

declare(strict_types=1);

namespace App\Services;

use App\Resources\PostResource;
use Framework\Database;

/**
 * Forgets the cached pieces of a post once its data changes.
 *
 * Models call this after a write that went through, so the keys a post is
 * cached under are named in one place instead of in every model that touches
 * posts. Every clear waits for the surrounding transaction to commit, so a
 * reader in between cannot rebuild a page from rows that are about to change.
 */
class PostCacheInvalidator
{
    /**
     * A post was added to a blog.
     */
    public function postCreated(int $blogId): void
    {
        $this->afterCommit(function () use ($blogId): void {
            // Discover lists recent posts and each blog's post count.
            $this->publicCache()->purgeDiscover();

            // A new post shifts every neighbour/related list in its blog.
            if ($blogId > 0) {
                $this->forgetBlogPosts($blogId);
            }
        });
    }

    /**
     * A post was edited, deleted, or changed status or visibility.
     *
     * A new slug is cleared too, in case its address was cached before the
     * rename.
     */
    public function postChanged(PostResource $post, ?string $newSlug = null): void
    {
        // Read now: by commit time the post or its blog may be gone.
        $blog = $post->blog();
        $blogSlug = $blog->slug();
        $blogId = (int) $blog->id();
        $postSlug = $post->slug();
        $postId = (int) $post->id();

        $this->afterCommit(function () use ($blogSlug, $blogId, $postSlug, $postId, $newSlug): void {
            cache()->deletePattern("*:GET:/blog/{$blogSlug}/{$postSlug}*");

            if ($newSlug !== null && $newSlug !== $postSlug) {
                cache()->deletePattern("*:GET:/blog/{$blogSlug}/{$newSlug}*");
            }

            // Discover lists recent posts.
            $this->publicCache()->purgeDiscover();

            // Neighbour/related lists filter on status='published', so any change
            // can shift them for the whole blog.
            $this->forgetBlogPosts($blogId, $postId);
        });
    }

    /**
     * Drop cached data fragments tied to a blog's posts.
     *
     * Neighbour and related lists are blog-scoped, so any post write in the
     * blog invalidates all of them. When a specific post id is given, its own
     * tag and comment fragments are dropped too.
     */
    public function forgetBlogPosts(int $blogId, ?int $postId = null): void
    {
        $this->afterCommit(function () use ($blogId, $postId): void {
            fragment()->forgetPattern('post-nav:'.$blogId.':*');
            fragment()->forgetPattern('post-related:'.$blogId.':*');

            // Landing/listing data: featured pick, paginated lists, category grids,
            // and the category chips (their post_count tracks published posts).
            fragment()->forget('blog-featured:'.$blogId, false);
            fragment()->forgetPattern('blog-posts:'.$blogId.':*');
            fragment()->forgetPattern('blog-catposts:'.$blogId.':*');
            fragment()->forget('blog-pubcats:'.$blogId, false);

            if ($postId !== null) {
                $this->forgetTags($postId);
                $this->commentCache()->forgetThread($postId);
            }
        });
    }

    /**
     * The post's cached tag list (PostModel::tags).
     */
    public function forgetTags(int $postId): void
    {
        $this->afterCommit(fn () => fragment()->forget('post-tags:'.$postId, false));
    }

    private function afterCommit(callable $work): void
    {
        app(Database::class)->afterCommit($work);
    }

    private function publicCache(): PublicCacheInvalidator
    {
        return app(PublicCacheInvalidator::class);
    }

    private function commentCache(): CommentCacheInvalidator
    {
        return app(CommentCacheInvalidator::class);
    }
}
