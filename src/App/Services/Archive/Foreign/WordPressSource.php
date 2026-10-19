<?php

declare(strict_types=1);

namespace App\Services\Archive\Foreign;

use App\Services\Archive\ArchiveException;

/**
 * Reads a WordPress export (Tools, Export, All content) into archive posts.
 *
 * The file is read item by item, so a large export does not have to fit in memory.
 * Pages, menus and comments are left behind; only posts are brought over.
 */
final class WordPressSource implements ForeignSource
{
    private const BLOCK_TAGS = '#<(?:p|div|h[1-6]|ul|ol|blockquote|figure|pre|table)\b#i';

    public function name(): string
    {
        return 'WordPress';
    }

    public function extension(): string
    {
        return 'xml';
    }

    public function needsSiteUrl(): bool
    {
        return false;
    }

    public function posts(string $path, string $siteUrl): array
    {
        $reader = new \XMLReader();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$reader->open($path, null, LIBXML_NONET | LIBXML_NOCDATA)) {
                throw $this->notAnExport();
            }

            return $this->read($reader);
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(\XMLReader $reader): array
    {
        $posts = [];
        $attachments = [];
        $seenRoot = false;

        while (@$reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->depth === 0) {
                $seenRoot = $reader->name === 'rss';
                if (!$seenRoot) {
                    throw $this->notAnExport();
                }
            }
            if ($reader->name !== 'item') {
                continue;
            }

            $document = new \DOMDocument();
            $node = $reader->expand($document);
            if ($node === false) {
                continue;
            }
            $item = simplexml_import_dom($document->importNode($node, true));
            $wp = $item->children('wp', true);

            if ((string) $wp->post_type === 'attachment') {
                $attachments[(string) $wp->post_id] = (string) $wp->attachment_url;
            } elseif ((string) $wp->post_type === 'post') {
                $post = $this->post($item, $wp);
                if ($post !== null) {
                    $posts[] = $post;
                }
            }
            $reader->next();
        }

        if (!$seenRoot) {
            throw $this->notAnExport();
        }

        return array_map(fn (array $post): array => $this->withImage($post, $attachments), $posts);
    }

    /**
     * @return array<string, mixed>|null Null for trashed and automatic drafts
     */
    private function post(\SimpleXMLElement $item, \SimpleXMLElement $wp): ?array
    {
        $state = $this->state((string) $wp->status, (string) $wp->post_password);
        if ($state === null) {
            return null;
        }

        $meta = $this->meta($wp);
        $id = (string) $wp->post_id;
        $date = (string) $wp->post_date_gmt;

        $fields = [
            'title' => (string) $item->title,
            'slug' => (string) $wp->post_name,
            'content' => $this->html((string) $item->children('content', true)->encoded),
            'excerpt' => strip_tags((string) $item->children('excerpt', true)->encoded),
            'status' => $state[0],
            'visibility' => $state[1],
            'published_at' => $date !== '' && !str_starts_with($date, '0000') ? $date : (string) $wp->post_date,
            'comments_enabled' => (string) $wp->comment_status === 'open',
            'author_name' => (string) $item->children('dc', true)->creator,
            'meta_title' => $this->yoastTitle($meta['_yoast_wpseo_title'] ?? ''),
            'meta_description' => $this->yoastTitle($meta['_yoast_wpseo_metadesc'] ?? ''),
            'focus_keyword' => $meta['_yoast_wpseo_focuskw'] ?? '',
            'tags' => [],
        ];

        foreach ($item->category as $category) {
            $term = ForeignPost::term((string) $category, rawurldecode((string) $category['nicename']));
            if ($term === null) {
                continue;
            }
            if ((string) $category['domain'] === 'post_tag') {
                $fields['tags'][] = $term;
            } elseif ((string) $category['domain'] === 'category' && !isset($fields['category']) && $term['slug'] !== 'uncategorized') {
                $fields['category'] = $term;
            }
        }

        $post = ForeignPost::make($fields, 'post-'.$id);
        $post['_thumbnail'] = $meta['_thumbnail_id'] ?? '';

        return $post;
    }

    /**
     * Status and visibility Lexicon uses for a WordPress status, or null to leave the post out.
     *
     * @return array{0: string, 1: string}|null
     */
    private function state(string $status, string $password): ?array
    {
        return match ($status) {
            'publish' => ['published', $password !== '' ? 'private' : 'public'],
            'future' => ['scheduled', 'public'],
            'private' => ['published', 'private'],
            'draft', 'pending' => ['draft', 'public'],
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function meta(\SimpleXMLElement $wp): array
    {
        $meta = [];
        foreach ($wp->postmeta as $row) {
            $meta[(string) $row->meta_key] = (string) $row->meta_value;
        }

        return $meta;
    }

    /**
     * Yoast stores unfilled titles as template codes like %%title%%, which mean nothing outside WordPress.
     */
    private function yoastTitle(string $value): string
    {
        return str_contains($value, '%%') ? '' : $value;
    }

    /**
     * Post HTML as written. WordPress keeps plain paragraphs without tags and adds them when showing the post.
     */
    private function html(string $content): string
    {
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? $content;
        $content = preg_replace('/\[\/?caption\b[^\]]*\]/i', '', $content) ?? $content;
        $content = trim($content);

        if ($content === '' || preg_match(self::BLOCK_TAGS, $content) === 1) {
            return $content;
        }

        $paragraphs = preg_split('/\R{2,}/', $content) ?: [];

        return implode("\n", array_map(static fn (string $p): string => '<p>'.nl2br(trim($p), false).'</p>', $paragraphs));
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, string>  $attachments  Attachment id to file address
     * @return array<string, mixed>
     */
    private function withImage(array $post, array $attachments): array
    {
        $url = $attachments[$post['_thumbnail']] ?? '';
        unset($post['_thumbnail']);

        if ($url !== '' && mb_strlen($url) <= 255 && filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $post['featured_image'] = $url;
        }

        return $post;
    }

    private function notAnExport(): ArchiveException
    {
        return new ArchiveException('That file is not a WordPress export.', 'archive.foreign.notWordpress');
    }
}
