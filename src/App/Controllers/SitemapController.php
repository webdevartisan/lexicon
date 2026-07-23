<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\PageModel;
use App\Models\PostModel;
use App\Services\LocaleRegistry;
use Framework\Core\Response;

/**
 * Crawler endpoints: the XML sitemap of static pages, blog homes and public
 * posts, plus robots.txt.
 *
 * Both live outside the locale-prefixed URL space, which WellKnownPathBypass
 * enforces on the way in.
 */
class SitemapController extends AppController
{
    /**
     * Static paths always present in the sitemap.
     */
    private const STATIC_PATHS = ['/', '/blogs', '/getting-started', '/about', '/contact', '/privacy', '/terms', '/cookies'];

    public function __construct(
        private PostModel $postModel,
        private BlogModel $blogModel,
        private PageModel $pageModel,
        private LocaleRegistry $registry
    ) {}

    public function index(): Response
    {
        // Regenerating on every crawl would hammer the database for nothing
        $xml = cache()->get('sitemap:xml');

        if ($xml === null) {
            $xml = $this->build();
            cache()->set('sitemap:xml', $xml, 3600);
        }

        $this->response->addHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->response->setBody($xml);

        return $this->response;
    }

    /**
     * robots.txt, served by the app rather than as a static file under public/.
     *
     * The Sitemap directive is only valid as an absolute URL, and the host
     * differs between development, staging and production, so it has to be
     * built from APP_URL at request time.
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            'Disallow: /dashboard/',
            'Disallow: /admin/',
            'Disallow: /login',
            'Disallow: /register',
            '',
            'Sitemap: '.rtrim(base_url(), '/').'/sitemap.xml',
            '',
        ];

        $this->response->addHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->response->setBody(implode("\n", $lines));

        return $this->response;
    }

    /**
     * One entry per locale a page is genuinely reachable at.
     *
     * The sitemap used to list only /en/ URLs while the pages themselves
     * advertised alternates for every configured locale. The two disagreed, and
     * the sitemap was the one telling the truth.
     *
     * @param  string[]  $localeSet
     * @return array<int, array{loc: string, lastmod: string|null}>
     */
    private function entriesFor(string $base, string $path, array $localeSet, ?string $lastmod): array
    {
        $entries = [];

        foreach ($localeSet as $locale) {
            if (!$this->registry->isSupported($locale)) {
                continue;
            }

            $entries[] = ['loc' => $base.lurl($path, $locale), 'lastmod' => $lastmod];
        }

        return $entries;
    }

    /**
     * Locale set for a blog or post row from the sitemap queries.
     *
     * Mirrors the rule the redirect guard enforces: the base language always,
     * plus translated locales once the blog opted into translations.
     *
     * @param  array<string, mixed>  $row
     * @return string[]
     */
    private function localeSetFor(array $row): array
    {
        $default = (string) ($row['default_locale'] ?? 'en');

        if (empty($row['translations_enabled']) || empty($row['translated_locales'])) {
            return [$default];
        }

        return array_values(array_unique(array_merge(
            [$default],
            explode(',', (string) $row['translated_locales'])
        )));
    }

    private function build(): string
    {
        $base = rtrim(base_url(), '/');
        $urls = [];

        // Platform pages carry only chrome, so they exist in every locale that
        // has a strings file behind it.
        $platformLocales = $this->registry->supported();

        foreach (self::STATIC_PATHS as $path) {
            $urls = array_merge($urls, $this->entriesFor($base, $path, $platformLocales, null));
        }

        // Guides are pages, so their edit date is a meaningful lastmod
        foreach (['start-your-first-blog', 'write-posts-people-read', 'blog-with-your-team'] as $guideSlug) {
            $guide = $this->pageModel->findPublished($guideSlug, $this->registry->default());
            if ($guide === null) {
                continue;
            }

            $urls = array_merge($urls, $this->entriesFor(
                $base,
                '/getting-started/'.$guideSlug,
                $this->pageModel->localesForSlug($guideSlug),
                $guide['updated_at'] ?? null
            ));
        }

        foreach ($this->blogModel->findPublicForSitemap() as $blog) {
            $urls = array_merge($urls, $this->entriesFor(
                $base,
                '/blog/'.rawurlencode($blog['blog_slug']),
                $this->localeSetFor($blog),
                $blog['last_post_at'] ?? null
            ));
        }

        foreach ($this->postModel->findPublicForSitemap() as $post) {
            $urls = array_merge($urls, $this->entriesFor(
                $base,
                '/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']),
                $this->localeSetFor($post),
                $post['updated_at'] ?? null
            ));
        }

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.e($url['loc']).'</loc>';
            if (!empty($url['lastmod'])) {
                $lines[] = '    <lastmod>'.date('Y-m-d', strtotime((string) $url['lastmod'])).'</lastmod>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
