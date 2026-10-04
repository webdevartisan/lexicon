<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\PostModel;
use App\Models\TagModel;
use App\Services\LocaleRegistry;

/**
 * Works out which blog page a beacon came from, using only the path.
 * Anything that isn't a live public page resolves to null, draft previews included.
 */
class PagePathResolver
{
    public function __construct(
        private BlogModel $blogs,
        private BlogSettingsModel $blogSettings,
        private PostModel $posts,
        private CategoryModel $categories,
        private TagModel $tags,
        private LocaleRegistry $locales,
    ) {}

    public function resolve(string $rawPath): ?TrafficPage
    {
        $segments = $this->segments($rawPath);

        $locale = null;
        if ($segments !== [] && $this->locales->isSupported($segments[0])) {
            $locale = array_shift($segments);
        }

        $rest = array_slice($segments, 2);
        $blog = $this->publishedBlog($segments);
        $page = $blog === null ? null : $this->pageWithin((int) $blog['id'], $rest);

        if ($blog === null || $page === null) {
            return null;
        }

        [$pageType, $postId] = $page;
        $blogId = (int) $blog['id'];
        $settings = $this->blogSettings->findByBlogId($blogId) ?? [];
        $blogPath = '/'.implode('/', $rest);

        return new TrafficPage(
            blogId: $blogId,
            postId: $postId,
            pageType: $pageType,
            path: rtrim('/blog/'.$segments[1].$blogPath, '/'),
            blogPath: $blogPath,
            locale: $locale ?? (string) ($settings['default_locale'] ?? $this->locales->default()),
            settings: $settings,
        );
    }

    /**
     * @param  list<string>  $segments  Path segments after the locale
     * @return array<string, mixed>|null
     */
    private function publishedBlog(array $segments): ?array
    {
        if (count($segments) < 2 || $segments[0] !== 'blog' || !preg_match('/^[A-Za-z0-9_-]+$/', $segments[1])) {
            return null;
        }

        $blog = $this->blogs->getBlogBySlug($segments[1]);

        return $blog !== null && ($blog['status'] ?? '') === 'published' ? $blog : null;
    }

    /**
     * @param  list<string>  $rest  Segments after /blog/{slug}
     * @return array{0: string, 1: ?int}|null Page type and post id
     */
    private function pageWithin(int $blogId, array $rest): ?array
    {
        if ($rest === []) {
            return ['landing', null];
        }

        if ($rest === ['archive']) {
            return ['archive', null];
        }

        if (count($rest) === 2 && $rest[0] === 'category') {
            return $this->categories->findBySlugInBlog($blogId, $rest[1]) ? ['category', null] : null;
        }

        if (count($rest) === 2 && $rest[0] === 'tag') {
            return $this->tags->findBySlugInBlog($blogId, $rest[1]) ? ['tag', null] : null;
        }

        if (count($rest) !== 1) {
            return null;
        }

        $post = $this->posts->findBySlugAndBlogId($rest[0], $blogId);

        if ($post === null || ($post['status'] ?? '') !== 'published') {
            return null;
        }

        return ['post', (int) $post['id']];
    }

    /**
     * @return list<string>
     */
    private function segments(string $rawPath): array
    {
        $path = parse_url($rawPath, PHP_URL_PATH);

        if (!is_string($path) || $path === '' || strlen($path) > 255) {
            return [];
        }

        $parts = array_map('rawurldecode', explode('/', trim($path, '/')));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
