<?php

declare(strict_types=1);

namespace App\Services\Traffic;

/**
 * A public page that a beacon claimed to be on, as the server resolved it:
 * a blog page, or one of the platform's own pages, which has no blog.
 */
final class TrafficPage
{
    /**
     * @param  string  $path  Locale-free path, e.g. /blog/my-blog/archive or /about
     * @param  string  $blogPath  The same path relative to the blog, e.g. /archive; the path itself for platform pages
     * @param  array<string, mixed>  $settings  The blog_settings row; empty for platform pages
     */
    public function __construct(
        public readonly ?int $blogId,
        public readonly ?int $postId,
        public readonly string $pageType,
        public readonly string $path,
        public readonly string $blogPath,
        public readonly string $locale,
        public readonly array $settings,
    ) {}

    /**
     * One of the platform's own pages. Its days are UTC and no blog team or excluded paths apply.
     */
    public static function platform(string $pageType, string $path, string $locale): self
    {
        return new self(null, null, $pageType, $path, $path, $locale, ['traffic_exclude_members' => 0]);
    }

    public function timezone(): string
    {
        $zone = (string) ($this->settings['timezone'] ?? '');

        return $zone !== '' && in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
    }

    /**
     * Whether the blog owner excluded this page, by a blog-relative prefix per line.
     */
    public function isExcluded(): bool
    {
        $lines = preg_split('/\R/', (string) ($this->settings['traffic_excluded_paths'] ?? '')) ?: [];

        foreach ($lines as $line) {
            $prefix = '/'.trim(trim($line), '/');

            if ($prefix === '/') {
                continue;
            }

            if ($this->blogPath === $prefix || str_starts_with($this->blogPath, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function excludesMembers(): bool
    {
        return (bool) ($this->settings['traffic_exclude_members'] ?? true);
    }
}
