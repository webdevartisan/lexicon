<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PublishedPostModel;
use App\Models\UserModel;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Serves each blog's RSS feed of its newest public posts.
 */
class FeedController extends AppController
{
    private const POST_LIMIT = 20;

    public function __construct(
        private BlogModel $blogs,
        private BlogSettingsModel $settings,
        private PublishedPostModel $publishedPosts,
        private UserModel $users
    ) {}

    public function show(string $blogSlug): Response
    {
        $blog = $this->blogs->getBlogBySlug($blogSlug);
        if (!$blog || ($blog['status'] ?? 'published') !== 'published') {
            throw new PageNotFoundException('Blog not found', 404);
        }

        $settings = $this->settings->findByBlogId((int) $blog['id']) ?? [];
        $locale = (string) ($settings['default_locale'] ?? 'en');
        $posts = $this->publishedPosts->findPublishedByBlogIdWithPagination((int) $blog['id'], 1, self::POST_LIMIT)['data'];

        // Browsers download application/rss+xml instead of applying the stylesheet; readers accept either
        $this->response->addHeader('Content-Type', 'application/xml; charset=utf-8');
        if (array_key_exists('indexable', $settings) && !$settings['indexable']) {
            $this->response->addHeader('X-Robots-Tag', 'noindex');
        }
        $this->response->setBody($this->render($blog, $settings, $posts, $locale));

        return $this->response;
    }

    /**
     * @param  array<string, mixed>  $blog
     * @param  array<string, mixed>  $settings
     * @param  array<int, array<string, mixed>>  $posts
     */
    private function render(array $blog, array $settings, array $posts, string $locale): string
    {
        $origin = rtrim(base_url(), '/');
        $slug = rawurlencode((string) $blog['blog_slug']);
        $blogUrl = $origin.lurl('/blog/'.$slug, $locale);
        $feedUrl = $origin.lurl('/blog/'.$slug.'/feed.xml', $locale);
        $description = trim((string) ($settings['meta_description'] ?? $blog['description'] ?? ''));

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<?xml-stylesheet type="text/xsl" href="/assets/feed.xsl"?>';
        $lines[] = '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/">';
        $lines[] = '<channel>';
        $lines[] = '  <title>'.$this->text((string) $blog['blog_name']).'</title>';
        $lines[] = '  <link>'.$this->text($blogUrl).'</link>';
        $lines[] = '  <description>'.$this->text($description !== '' ? $description : (string) $blog['blog_name']).'</description>';
        $lines[] = '  <language>'.$this->text($locale).'</language>';
        $lines[] = '  <atom:link href="'.$this->text($feedUrl).'" rel="self" type="application/rss+xml" />';
        if ($posts !== []) {
            $lines[] = '  <lastBuildDate>'.$this->date($posts[0]['updated_at'] ?? $this->publishedAt($posts[0])).'</lastBuildDate>';
        }

        $names = [];
        foreach ($posts as $post) {
            $link = $origin.lurl('/blog/'.$slug.'/'.rawurlencode((string) $post['slug']), $locale);
            $authorId = (int) $post['author_id'];
            $names[$authorId] ??= $this->authorName($authorId);

            $lines[] = '  <item>';
            $lines[] = '    <title>'.$this->text((string) $post['title']).'</title>';
            $lines[] = '    <link>'.$this->text($link).'</link>';
            $lines[] = '    <guid isPermaLink="true">'.$this->text($link).'</guid>';
            $lines[] = '    <pubDate>'.$this->date($this->publishedAt($post)).'</pubDate>';
            $lines[] = '    <dc:creator>'.$this->text($names[$authorId]).'</dc:creator>';
            $lines[] = '    <description>'.$this->text(post_excerpt($post, 300)).'</description>';
            $lines[] = '    <content:encoded><![CDATA['.$this->cdata($this->absoluteLinks((string) ($post['content'] ?? ''), $origin)).']]></content:encoded>';
            $lines[] = '  </item>';
        }

        $lines[] = '</channel>';
        $lines[] = '</rss>';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $post
     */
    private function publishedAt(array $post): string
    {
        return (string) ($post['published_at'] ?? $post['created_at']);
    }

    private function authorName(int $authorId): string
    {
        $author = $this->users->findById($authorId);

        return (string) ($author['display_name_cached'] ?? $author['handle'] ?? '');
    }

    private function text(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function date(mixed $utc): string
    {
        return gmdate(DATE_RSS, strtotime((string) $utc.' UTC'));
    }

    /**
     * Feed readers have no page to resolve a root-relative image or link against.
     */
    private function absoluteLinks(string $html, string $origin): string
    {
        return (string) preg_replace('#\b(src|href)="/(?!/)#i', '$1="'.$origin.'/', $html);
    }

    /**
     * A CDATA section ends at the first ]]>, so split it wherever the content holds one.
     */
    private function cdata(string $html): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $html);
    }
}
