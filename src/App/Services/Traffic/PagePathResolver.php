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
 * Works out which public page a beacon came from, using only the path: one of
 * the platform's own pages or a blog page. Anything that isn't a live public
 * page resolves to null, draft previews included.
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
        $split = $this->split($rawPath);
        if ($split === null) {
            return null;
        }

        [$locale, $segments] = $split;
        $platform = PlatformPages::match($segments);
        if ($platform !== null) {
            return TrafficPage::platform($platform[0], $platform[1], $locale ?? $this->locales->default());
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
     * Which part of Lexicon a page is in, without checking that the page exists:
     * a published blog, or the platform with the kind of page, 'other' for the
     * platform pages that aren't counted.
     *
     * @return array{blogId: ?int, pageType: string}
     */
    public function section(string $rawPath): array
    {
        $split = $this->split($rawPath);
        if ($split === null) {
            return ['blogId' => null, 'pageType' => 'other'];
        }

        $blog = $this->publishedBlog($split[1]);
        if ($blog !== null) {
            return ['blogId' => (int) $blog['id'], 'pageType' => 'blog'];
        }

        return ['blogId' => null, 'pageType' => PlatformPages::match($split[1])[0] ?? 'other'];
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
     * @return array{0: ?string, 1: list<string>}|null The locale prefix and the segments after it
     */
    private function split(string $rawPath): ?array
    {
        $segments = $this->segments($rawPath);
        if ($segments === null) {
            return null;
        }

        $locale = null;
        if ($segments !== [] && $this->locales->isSupported($segments[0])) {
            $locale = array_shift($segments);
        }

        return [$locale, $segments];
    }

    /**
     * @return list<string>|null Null when the path can't be a page at all
     */
    private function segments(string $rawPath): ?array
    {
        $path = parse_url($rawPath, PHP_URL_PATH);

        if (!is_string($path) || $path === '' || strlen($path) > 255) {
            return null;
        }

        $parts = array_map('rawurldecode', explode('/', trim($path, '/')));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
