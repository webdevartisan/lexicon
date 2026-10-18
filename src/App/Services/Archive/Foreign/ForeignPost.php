<?php

declare(strict_types=1);

namespace App\Services\Archive\Foreign;

use App\ValueObjects\PostFieldRules;

/**
 * Builds archive posts from the loose values a foreign export gives.
 *
 * The archive validator is the judge of what can be imported. This only puts
 * values in the shapes it expects: UTC dates, clean slugs, and optional fields
 * left empty when they would not fit the editor's limits.
 */
final class ForeignPost
{
    /** Under the editor's 300, because the cut adds an ellipsis. */
    private const EXCERPT_FIT = 290;

    private const SEO_LIMITS = [
        'meta_title' => 70,
        'meta_description' => 200,
        'og_title' => 70,
        'og_description' => 100,
        'twitter_title' => 70,
        'twitter_description' => 200,
        'focus_keyword' => 100,
    ];

    /**
     * @param  array<string, mixed>  $fields  title, content, status and the rest of the archive post fields
     * @return array<string, mixed>
     */
    public static function make(array $fields, string $fallbackSlug): array
    {
        $title = trim((string) ($fields['title'] ?? ''));
        $post = [
            'title' => $title !== '' ? $title : 'Untitled',
            'slug' => self::slug((string) ($fields['slug'] ?? ''), $title, $fallbackSlug),
            'content' => (string) ($fields['content'] ?? ''),
            'excerpt' => self::summary($fields['excerpt'] ?? null),
            'featured_image' => self::address($fields['featured_image'] ?? null),
            'status' => $fields['status'] ?? 'draft',
            'visibility' => $fields['visibility'] ?? 'public',
            'published_at' => self::utc($fields['published_at'] ?? null),
            'comments_enabled' => $fields['comments_enabled'] ?? true,
            'canonical_url' => self::address($fields['canonical_url'] ?? null),
            'og_image' => self::address($fields['og_image'] ?? null),
            'twitter_image' => self::address($fields['twitter_image'] ?? null),
            'category' => $fields['category'] ?? null,
            'tags' => $fields['tags'] ?? [],
            'author_name' => (string) ($fields['author_name'] ?? ''),
        ];

        foreach (self::SEO_LIMITS as $field => $max) {
            $post[$field] = self::fit($fields[$field] ?? null, $max);
        }

        return $post;
    }

    /**
     * A term as the archive stores it, or null when no usable slug can be made.
     *
     * @return array{name: string, slug: string}|null
     */
    public static function term(string $name, string $slug = ''): ?array
    {
        $name = trim($name);
        $clean = self::cleanSlug($slug) ?: slugify($name);

        if ($name === '' || mb_strlen($name) > 100 || $clean === '' || strlen($clean) > 120) {
            return null;
        }

        return ['name' => $name, 'slug' => $clean];
    }

    /**
     * Any date format a platform writes, as the archive's UTC form, or null.
     */
    public static function utc(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Text trimmed, or null when empty or longer than the editor allows.
     */
    public static function fit(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && mb_strlen($value) <= $max ? $value : null;
    }

    /**
     * A plain-text excerpt cut at a word, within the editor's limit.
     */
    private static function summary(mixed $value): ?string
    {
        $text = post_excerpt(['excerpt' => (string) $value], self::EXCERPT_FIT);

        return $text !== '' ? $text : null;
    }

    private static function address(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && mb_strlen($value) <= 255 && filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
    }

    private static function slug(string $slug, string $title, string $fallback): string
    {
        $clean = self::cleanSlug($slug) ?: slugify($title);
        $clean = trim(substr($clean, 0, PostFieldRules::SLUG_MAX), '-');

        return strlen($clean) >= 2 ? $clean : $fallback;
    }

    private static function cleanSlug(string $slug): string
    {
        return slugify(rawurldecode($slug));
    }
}
