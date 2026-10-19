<?php

declare(strict_types=1);

namespace App\Services\Archive\Foreign;

use App\Services\Archive\ArchiveException;
use App\Services\Archive\ArchiveFormat;

/**
 * Reads a Ghost export (Settings, Labs, Export content) into archive posts.
 *
 * Ghost writes its own address into links as a placeholder, so the owner says where
 * the old site lived and the placeholder is replaced with it.
 */
final class GhostSource implements ForeignSource
{
    private const PLACEHOLDER = '__GHOST_URL__';

    public function name(): string
    {
        return 'Ghost';
    }

    public function extension(): string
    {
        return 'json';
    }

    public function needsSiteUrl(): bool
    {
        return true;
    }

    public function posts(string $path, string $siteUrl): array
    {
        if (filesize($path) > ArchiveFormat::MAX_JSON_BYTES) {
            throw new ArchiveException('That file is too large to read.', 'archive.foreign.tooLarge');
        }

        $json = json_decode((string) file_get_contents($path), true);
        $data = $json['db'][0]['data'] ?? $json['data'] ?? null;
        if (!is_array($data) || !is_array($data['posts'] ?? null)) {
            throw new ArchiveException('That file is not a Ghost export.', 'archive.foreign.notGhost');
        }

        $site = rtrim(trim($siteUrl), '/');
        if ($site === '' && str_contains((string) file_get_contents($path), self::PLACEHOLDER)) {
            throw new ArchiveException('Ghost leaves its own address out of links, so enter where the old site lived.', 'archive.foreign.needSiteUrl');
        }

        $tags = $this->tagsByPost($data);
        $authors = $this->authorsByPost($data);
        $extra = $this->metaByPost($data);

        $posts = [];
        foreach ($data['posts'] as $row) {
            if (!is_array($row) || ($row['type'] ?? 'post') !== 'post') {
                continue;
            }

            $id = (string) ($row['id'] ?? '');
            $row = $this->fill($row, $extra[$id] ?? []);
            $posts[] = $this->post($row, $site, $tags[$id] ?? [], $authors[$id] ?? '');
        }

        return $posts;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{name: string, slug: string}>  $tags
     * @return array<string, mixed>
     */
    private function post(array $row, string $site, array $tags, string $author): array
    {
        $open = ($row['visibility'] ?? 'public') === 'public';
        $status = in_array($row['status'] ?? '', ['published', 'scheduled'], true) && $open
            ? (string) $row['status']
            : 'draft';
        $at = $row['published_at'] ?? null;

        $fields = [
            'title' => $row['title'] ?? '',
            'slug' => $row['slug'] ?? '',
            'content' => $this->resolve((string) ($row['html'] ?? ''), $site),
            'excerpt' => strip_tags((string) ($row['custom_excerpt'] ?? '')),
            'featured_image' => $this->resolve((string) ($row['feature_image'] ?? ''), $site),
            'status' => $status,
            'published_at' => $at,
            'author_name' => $author,
            'tags' => $tags,
        ];
        foreach (['meta_title', 'meta_description', 'og_title', 'og_description', 'twitter_title', 'twitter_description'] as $field) {
            $fields[$field] = $row[$field] ?? null;
        }
        foreach (['canonical_url', 'og_image', 'twitter_image'] as $field) {
            $fields[$field] = $this->resolve((string) ($row[$field] ?? ''), $site);
        }

        return ForeignPost::make($fields, 'post-'.($row['id'] ?? bin2hex(random_bytes(3))));
    }

    /**
     * Ghost 5 keeps the SEO fields in a table of their own; older exports keep them on the post.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function fill(array $row, array $extra): array
    {
        foreach ($extra as $field => $value) {
            if (($row[$field] ?? '') === '' || ($row[$field] ?? null) === null) {
                $row[$field] = $value;
            }
        }

        return $row;
    }

    private function resolve(string $value, string $site): string
    {
        return str_replace(self::PLACEHOLDER, $site, $value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<array{name: string, slug: string}>>
     */
    private function tagsByPost(array $data): array
    {
        $names = [];
        foreach ($data['tags'] ?? [] as $tag) {
            // Names starting with # are Ghost's internal tags, not ones readers see
            if (is_array($tag) && is_string($tag['name'] ?? null) && !str_starts_with($tag['name'], '#')) {
                $names[(string) $tag['id']] = ForeignPost::term($tag['name'], (string) ($tag['slug'] ?? ''));
            }
        }

        $links = array_filter($data['posts_tags'] ?? [], 'is_array');
        usort($links, static fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $byPost = [];
        foreach ($links as $link) {
            $term = $names[(string) ($link['tag_id'] ?? '')] ?? null;
            if ($term !== null) {
                $byPost[(string) $link['post_id']][$term['slug']] = $term;
            }
        }

        return array_map('array_values', $byPost);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string> Post id to its first author's name
     */
    private function authorsByPost(array $data): array
    {
        $names = [];
        foreach ($data['users'] ?? [] as $user) {
            if (is_array($user) && is_string($user['name'] ?? null)) {
                $names[(string) $user['id']] = $user['name'];
            }
        }

        $links = array_filter($data['posts_authors'] ?? [], 'is_array');
        usort($links, static fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $byPost = [];
        foreach ($links as $link) {
            $byPost[(string) $link['post_id']] ??= $names[(string) ($link['author_id'] ?? '')] ?? '';
        }

        return $byPost;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    private function metaByPost(array $data): array
    {
        $byPost = [];
        foreach ($data['posts_meta'] ?? [] as $meta) {
            if (is_array($meta) && isset($meta['post_id'])) {
                $byPost[(string) $meta['post_id']] = $meta;
            }
        }

        return $byPost;
    }
}
