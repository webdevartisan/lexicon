<?php

declare(strict_types=1);

namespace App\Services\Traffic;

/**
 * A public blog page that a beacon claimed to be on, as the server resolved it.
 */
final class TrafficPage
{
    /**
     * @param  string  $path  Locale-free path, e.g. /blog/my-blog/archive
     * @param  string  $blogPath  The same path relative to the blog, e.g. /archive
     * @param  array<string, mixed>  $settings  The blog_settings row
     */
    public function __construct(
        public readonly int $blogId,
        public readonly ?int $postId,
        public readonly string $pageType,
        public readonly string $path,
        public readonly string $blogPath,
        public readonly string $locale,
        public readonly array $settings,
    ) {}

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

    public function countingEnabled(): bool
    {
        return (bool) ($this->settings['traffic_enabled'] ?? false);
    }

    public function excludesMembers(): bool
    {
        return (bool) ($this->settings['traffic_exclude_members'] ?? true);
    }
}
