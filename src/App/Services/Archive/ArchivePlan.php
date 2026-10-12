<?php

declare(strict_types=1);

namespace App\Services\Archive;

/**
 * What an archive would do to a blog, worked out before anything is written.
 *
 * Built by BlogArchiveReader and applied by BlogArchiveImporter. It holds the
 * checked posts and media, the slug clashes with the target blog, and every
 * problem found, so the preview and the import can never disagree about them.
 */
final class ArchivePlan
{
    /**
     * @param  array{name: string, slug: string, exported_at: string}  $source  Blog the archive came from
     * @param  list<array{data: array<string, mixed>, errors: list<string>, clash: array{id: int, status: string, title: string}|null}>  $posts
     * @param  array<string, array{path: string, original_name: string, alt: ?string, usable: bool, reason: ?string}>  $media  Keyed by archive path
     * @param  list<array{type: string, subject: string, detail: string}>  $problems
     * @param  array<string, true>  $missingAtExport  Upload URLs the exporter found missing on disk
     */
    public function __construct(
        public readonly array $source,
        public readonly array $posts,
        public readonly array $media,
        public readonly array $problems,
        public readonly array $missingAtExport = [],
    ) {}

    /**
     * Posts that passed every check.
     *
     * @return list<array{data: array<string, mixed>, errors: list<string>, clash: array{id: int, status: string, title: string}|null}>
     */
    public function validPosts(): array
    {
        return array_values(array_filter($this->posts, static fn (array $p): bool => $p['errors'] === []));
    }

    /**
     * Valid posts whose slug the target blog already uses.
     *
     * @return list<array{data: array<string, mixed>, errors: list<string>, clash: array{id: int, status: string, title: string}}>
     */
    public function clashes(): array
    {
        return array_values(array_filter($this->validPosts(), static fn (array $p): bool => $p['clash'] !== null));
    }

    /**
     * Media entries that can be stored.
     *
     * @return array<string, array{path: string, original_name: string, alt: ?string, usable: bool, reason: ?string}>
     */
    public function usableMedia(): array
    {
        return array_filter($this->media, static fn (array $m): bool => $m['usable']);
    }

    /**
     * Totals for the preview.
     *
     * @return array{posts: int, valid: int, invalid: int, clashes: int, tags: int, categories: int, media: int, media_skipped: int, scheduled_past: int}
     */
    public function counts(): array
    {
        $valid = $this->validPosts();
        $tags = [];
        $categories = [];
        $scheduledPast = 0;
        $now = gmdate('Y-m-d H:i:s');

        foreach ($valid as $post) {
            foreach ($post['data']['tags'] as $tag) {
                $tags[$tag['slug']] = true;
            }
            if ($post['data']['category'] !== null) {
                $categories[$post['data']['category']['slug']] = true;
            }
            if ($post['data']['status'] === 'scheduled' && ($post['data']['published_at'] ?? $now) <= $now) {
                $scheduledPast++;
            }
        }

        $usable = count($this->usableMedia());

        return [
            'posts' => count($this->posts),
            'valid' => count($valid),
            'invalid' => count($this->posts) - count($valid),
            'clashes' => count($this->clashes()),
            'tags' => count($tags),
            'categories' => count($categories),
            'media' => $usable,
            'media_skipped' => count($this->media) - $usable,
            'scheduled_past' => $scheduledPast,
        ];
    }
}
