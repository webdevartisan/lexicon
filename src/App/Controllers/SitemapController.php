<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\PageModel;
use App\Models\PostModel;
use Framework\Core\Response;

/**
 * XML sitemap for search engines: static pages, blog homes and public posts.
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
        private PageModel $pageModel
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

    private function build(): string
    {
        $base = rtrim(base_url(), '/');
        $urls = [];

        foreach (self::STATIC_PATHS as $path) {
            $urls[] = ['loc' => $base.lurl($path, 'en'), 'lastmod' => null];
        }

        // Guides are pages, so their edit date is a meaningful lastmod
        foreach (['start-your-first-blog', 'write-posts-people-read', 'blog-with-your-team'] as $guideSlug) {
            $guide = $this->pageModel->findPublished($guideSlug, 'en');
            if ($guide !== null) {
                $urls[] = [
                    'loc' => $base.lurl('/getting-started/'.$guideSlug, 'en'),
                    'lastmod' => $guide['updated_at'] ?? null,
                ];
            }
        }

        $blogs = $this->blogModel->getDirectoryWithPagination(1, 500);
        foreach ($blogs['data'] as $blog) {
            $urls[] = [
                'loc' => $base.lurl('/blog/'.rawurlencode($blog['blog_slug']), 'en'),
                'lastmod' => $blog['last_post_at'] ?? null,
            ];
        }

        foreach ($this->postModel->findPublicForSitemap() as $post) {
            $urls[] = [
                'loc' => $base.lurl('/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']), 'en'),
                'lastmod' => $post['updated_at'] ?? null,
            ];
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
