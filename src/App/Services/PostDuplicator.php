<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostModel;
use App\Models\TagModel;
use App\Resources\PostResource;
use App\ValueObjects\CopyName;
use App\ValueObjects\PostFieldRules;

/**
 * Copies a post into a new draft in the same blog.
 *
 * The copy keeps what the writer made (text, category, tags, image, SEO and
 * sharing fields) and starts its own history: a draft by whoever copied it,
 * not featured, never published or reviewed, with no comments or views.
 */
final class PostDuplicator
{
    /** Columns a copy carries over; every other column takes its default. */
    private const COPIED = [
        'category_id', 'content', 'excerpt', 'featured_image', 'visibility', 'comments_enabled', 'timezone',
        'focus_keyword', 'meta_title', 'meta_description', 'canonical_url', 'meta_noindex', 'meta_nofollow',
        'og_title', 'og_description', 'og_image', 'og_image_alt',
        'twitter_card_type', 'twitter_title', 'twitter_description', 'twitter_image',
    ];

    public function __construct(
        private PostModel $posts,
        private TagModel $tags,
    ) {}

    /**
     * The title and address offered for a copy, both unused in the blog.
     *
     * @return array{title: string, slug: string}
     */
    public function suggest(PostResource $post): array
    {
        $taken = $this->posts->titlesStartingWith($post->blogId(), CopyName::titleBase($post->title()));

        return [
            'title' => CopyName::title($post->title(), $taken),
            'slug' => $this->freeSlug($post->blogId(), CopyName::slug($post->slug())),
        ];
    }

    /**
     * Create the copy and return its id. A taken address gets the next free number.
     *
     * @param  PostResource  $post  The post to copy
     * @param  string  $title  Validated title for the copy
     * @param  string  $slug  Validated address for the copy
     * @param  int  $authorId  Whoever made the copy
     */
    public function duplicate(PostResource $post, string $title, string $slug, int $authorId): int
    {
        $blogId = $post->blogId();
        $data = array_intersect_key($post->toArray(), array_flip(self::COPIED)) + [
            'blog_id' => $blogId,
            'author_id' => $authorId,
            'title' => $title,
            'slug' => $this->freeSlug($blogId, $slug),
            'status' => 'draft',
        ];
        $tagIds = array_map(static fn (array $tag): int => (int) $tag['id'], $this->posts->tags($post->id()));

        // A copy without its tags would look complete but quietly drop out of tag pages.
        return $this->posts->transaction(function () use ($data, $tagIds): int {
            $copyId = $this->posts->create($data);
            $this->tags->syncIdsForPost($copyId, $tagIds);

            return $copyId;
        });
    }

    private function freeSlug(int $blogId, string $slug): string
    {
        return $this->posts->availableSlug($blogId, $slug, null, PostFieldRules::SLUG_MAX);
    }
}
