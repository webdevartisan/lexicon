<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Where on Lexicon a reader came from, as stored in referrer_source on views
 * from another part of Lexicon: a platform page type such as 'discover', 'other'
 * for platform pages that aren't counted, or blog:{id} for another blog.
 */
final class LexiconSource
{
    private const BLOG_PREFIX = 'blog:';

    public static function blog(int $blogId): string
    {
        return self::BLOG_PREFIX.$blogId;
    }

    /**
     * The blog a source names, or null for a platform page.
     */
    public static function blogId(string $source): ?int
    {
        if (!str_starts_with($source, self::BLOG_PREFIX)) {
            return null;
        }

        $id = substr($source, strlen(self::BLOG_PREFIX));

        return ctype_digit($id) ? (int) $id : null;
    }
}
