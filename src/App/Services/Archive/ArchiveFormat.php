<?php

declare(strict_types=1);

namespace App\Services\Archive;

/**
 * The blog archive format: its name, version, entry names and hard limits.
 *
 * Version 1 readers ignore top-level keys and entries they do not know, so later
 * additions (comments, settings, Markdown) can ship without a version bump. The
 * version only changes when a version 1 reader would misread the archive.
 */
final class ArchiveFormat
{
    public const NAME = 'lexicon-blog-archive';

    public const VERSION = 1;

    public const MANIFEST = 'manifest.json';

    public const POSTS = 'posts.json';

    public const MEDIA = 'media.json';

    /** Archive-relative media paths: media/{hash}.{ext}, nothing else. */
    public const MEDIA_PATH_PATTERN = '#^media/[A-Za-z0-9_-]{1,64}\.[a-z0-9]{1,5}$#';

    /** Image types the upload pipeline stores. Anything else in an archive is skipped. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_ARCHIVE_BYTES = 100 * 1024 * 1024;

    public const MAX_ENTRIES = 5000;

    public const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    public const MAX_JSON_BYTES = 50 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 300 * 1024 * 1024;

    public const MAX_POSTS = 2000;

    /** Compression ratio past which an entry is treated as a zip bomb. */
    public const MAX_RATIO = 100;

    /** Entries smaller than this are not ratio-checked: short repetitive JSON compresses far past 100:1. */
    public const RATIO_FLOOR_BYTES = 1024 * 1024;

    /** Post fields carried in the archive. Anything else in posts.json is dropped. */
    public const POST_FIELDS = [
        'title', 'slug', 'content', 'excerpt', 'featured_image', 'status', 'visibility',
        'published_at', 'timezone', 'comments_enabled',
        'focus_keyword', 'meta_title', 'meta_description', 'canonical_url', 'meta_noindex', 'meta_nofollow',
        'og_title', 'og_description', 'og_image', 'og_image_alt',
        'twitter_card_type', 'twitter_title', 'twitter_description', 'twitter_image',
    ];

    /** Fields that may hold an image reference, besides the body. */
    public const IMAGE_FIELDS = ['featured_image', 'og_image', 'twitter_image'];

    /**
     * File name offered for a blog's archive.
     */
    public static function fileName(string $blogSlug, ?\DateTimeInterface $at = null): string
    {
        $date = ($at ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($blogSlug)) ?: 'blog';

        return "lexicon-{$slug}-{$date}.zip";
    }
}
