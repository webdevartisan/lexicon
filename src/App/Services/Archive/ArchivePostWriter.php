<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\CategoryModel;
use App\Models\PostArchiveModel;
use App\Models\TagModel;
use App\Resources\BlogResource;
use App\Services\PostContentSanitizer;

/**
 * Turns one checked archive post into a posts row and writes it with its category and tags.
 *
 * prepare() has no side effects. write() expects to run inside the caller's
 * transaction, so a failure in any part of it leaves no trace of the post.
 */
final class ArchivePostWriter
{
    /** Columns an import sets. Author, curation flags and review state are not among them. */
    private const COPIED = [
        'title', 'slug', 'excerpt', 'visibility', 'published_at', 'timezone',
        'focus_keyword', 'meta_title', 'meta_description', 'canonical_url',
        'og_title', 'og_description', 'og_image_alt', 'twitter_card_type', 'twitter_title', 'twitter_description',
    ];

    public function __construct(
        private PostArchiveModel $posts,
        private TagModel $tags,
        private CategoryModel $categories,
        private PostContentSanitizer $sanitizer,
        private ArchiveMediaUrls $urls,
    ) {}

    /**
     * Build the row for a post, with media references pointing at the stored files.
     *
     * @param  array<string, mixed>  $data  A post that passed ArchivePostValidator
     * @param  array<string, string>  $stored  Archive path => stored URL
     * @param  array<string, true>  $missingAtExport  Upload URLs whose files were already gone at export
     * @return array{row: array<string, mixed>, category: ?array{name: string, slug: string}, tags: list<array{name: string, slug: string}>, notes: list<string>}
     *
     * @throws ArchiveException When the body cannot be made safe
     */
    public function prepare(array $data, array $stored, bool $keepStatus, array $missingAtExport = []): array
    {
        $notes = [];
        $row = array_intersect_key($data, array_flip(self::COPIED));

        $row['content'] = $this->content((string) $data['content'], $stored, $missingAtExport, $notes);
        $row['featured_image'] = $this->image($data['featured_image'], $stored, false, 'featured image', $notes);
        $row['og_image'] = $this->image($data['og_image'], $stored, true, 'share image', $notes);
        $row['twitter_image'] = $this->image($data['twitter_image'], $stored, true, 'X image', $notes);
        $row['comments_enabled'] = (int) $data['comments_enabled'];
        $row['meta_noindex'] = (int) $data['meta_noindex'];
        $row['meta_nofollow'] = (int) $data['meta_nofollow'];

        $row['status'] = $this->status($data, $keepStatus, $notes);
        // Never pending (see status()), so there is no review state to carry.
        $row['workflow_state'] = 'draft';
        if ($row['status'] === 'published' && $row['published_at'] === null) {
            $row['published_at'] = gmdate('Y-m-d H:i:s');
        }

        return ['row' => $row, 'category' => $data['category'], 'tags' => $data['tags'], 'notes' => $notes];
    }

    /**
     * Write a prepared post. Must run inside a transaction.
     *
     * A new post belongs to the importer. An overwritten one keeps its author, its
     * id and with it its address, comments and stats, and its subscriber log.
     *
     * @param  array{row: array<string, mixed>, category: ?array{name: string, slug: string}, tags: list<array{name: string, slug: string}>}  $prepared
     * @return int The post id
     */
    public function write(array $prepared, BlogResource $target, int $userId, ?int $overwriteId): int
    {
        $blogId = (int) $target->id();
        $row = $prepared['row'];
        $row['category_id'] = $this->categoryId($blogId, $prepared['category']);

        // Never announce an imported post: the notifier skips anything already stamped.
        $alreadyNotified = $overwriteId !== null && $this->posts->subscribersNotified($overwriteId);
        if ($row['status'] !== 'draft' && !$alreadyNotified) {
            $row['subscribers_notified_at'] = gmdate('Y-m-d H:i:s');
        }

        if ($overwriteId !== null) {
            $this->posts->update($overwriteId, $row);
            $postId = $overwriteId;
        } else {
            $postId = (int) $this->posts->insert($row + ['blog_id' => $blogId, 'author_id' => $userId]);
            if ($postId === 0) {
                throw new \RuntimeException('The post row was not inserted.');
            }
        }

        $this->syncTags($postId, $blogId, $prepared['tags']);

        return $postId;
    }

    /**
     * @param  array<string, string>  $stored
     * @param  array<string, true>  $missingAtExport
     * @param  list<string>  $notes
     *
     * @throws ArchiveException
     */
    private function content(string $html, array $stored, array $missingAtExport, array &$notes): string
    {
        $missing = [];
        $html = $this->urls->rewrite($html, function (string $value) use ($stored, $missingAtExport, &$missing): ?string {
            if (isset($stored[$value])) {
                return $stored[$value];
            }
            if (preg_match(ArchiveFormat::MEDIA_PATH_PATTERN, $value) === 1
                || isset($missingAtExport[(string) $this->urls->canonical($value)])) {
                $missing[$value] = true;
            }

            return null;
        });

        [$html, $removed] = $this->urls->remove($html, array_keys($missing));
        if ($removed > 0) {
            $notes[] = $removed === 1 ? '1 image could not be imported and was left out.' : "{$removed} images could not be imported and were left out.";
        }

        try {
            return $this->sanitizer->clean($html);
        } catch (\RuntimeException $e) {
            throw new ArchiveException($e->getMessage(), 'archive.errors.contentTooLong', [], $e);
        }
    }

    /**
     * Resolve an image field. Share images are stored as full addresses, because the
     * editor checks them as URLs and would refuse a relative path on the next save.
     *
     * @param  array<string, string>  $stored
     * @param  list<string>  $notes
     */
    private function image(?string $value, array $stored, bool $absolute, string $label, array &$notes): ?string
    {
        if ($value === null) {
            return null;
        }
        if (isset($stored[$value])) {
            return $absolute ? rtrim((string) base_url(), '/').$stored[$value] : $stored[$value];
        }
        if (preg_match(ArchiveFormat::MEDIA_PATH_PATTERN, $value) === 1) {
            $notes[] = 'The '.$label.' could not be imported and was removed.';

            return null;
        }

        return $value;
    }

    /**
     * Everything comes in as a draft unless the owner chose to keep statuses.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $notes
     */
    private function status(array $data, bool $keepStatus, array &$notes): string
    {
        $status = (string) $data['status'];
        if (!$keepStatus || $status === 'draft') {
            return 'draft';
        }

        if ($status === 'pending') {
            $notes[] = 'Was awaiting review; imported as a draft because reviews are not carried over.';

            return 'draft';
        }

        if ($status === 'scheduled' && ($data['published_at'] === null || $data['published_at'] <= gmdate('Y-m-d H:i:s'))) {
            $notes[] = 'Was scheduled for a time that has passed; imported as published.';

            return 'published';
        }

        return $status;
    }

    /**
     * @param  array{name: string, slug: string}|null  $category
     */
    private function categoryId(int $blogId, ?array $category): ?int
    {
        if ($category === null) {
            return null;
        }

        $existing = $this->categories->findBySlugInBlog($blogId, $category['slug']);

        return $existing !== null
            ? (int) $existing['id']
            : $this->categories->findOrCreateForBlog($blogId, $category['name']);
    }

    /**
     * Replace the post's tags with the archive's, matched by slug first, then by name.
     *
     * @param  list<array{name: string, slug: string}>  $tags
     */
    private function syncTags(int $postId, int $blogId, array $tags): void
    {
        $ids = [];
        foreach ($tags as $tag) {
            $existing = $this->tags->findBySlugInBlog($blogId, $tag['slug']);
            $id = $existing !== null ? (int) $existing['id'] : $this->tags->findOrCreateForBlog($blogId, $tag['name']);
            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        $this->tags->syncIdsForPost($postId, array_keys($ids));
    }
}
