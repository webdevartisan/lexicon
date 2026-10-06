<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\PostModel;
use App\Models\SeoAuditModel;

/**
 * Checks a blog's published posts against what search engines read, using only
 * fields the editor has. Each finding names the post so the page can link to its
 * editor. Posts not offered to search engines are listed apart, as a choice
 * rather than a fault, and skip the other checks.
 */
final class SeoHealth
{
    /** Roughly where search results cut a title off. Google sets no hard limit, so this is a warning. */
    public const TITLE_LENGTH = 60;

    public const CHECKS = ['title_long', 'title_duplicate', 'no_description', 'keyword_missing', 'og_alt_missing', 'image_alt_missing', 'not_in_sitemap'];

    private const POSTS_LIMIT = 2000;

    private const CACHE_TTL = 600;

    public function __construct(private SeoAuditModel $audit) {}

    /**
     * @param  list<int>|null  $postIds  Only these posts, for a writer who sees their own; null for all
     * @return array{published: int, indexable: int, blog: list<string>, checks: array<string, list<array<string, mixed>>>, notOffered: list<array<string, mixed>>}
     */
    public function forBlog(int $blogId, ?array $postIds = null): array
    {
        $report = fragment()->rememberData("seo-health:blog:{$blogId}", fn (): array => $this->build($blogId), self::CACHE_TTL, false);

        return $postIds === null ? $report : self::only($report, $postIds);
    }

    /**
     * The whole site in numbers, for the control panel: posts published, posts
     * offered to search engines, and how many of those the sitemap has no room for.
     *
     * @return array{published: int, indexable: int, beyondSitemap: int, sitemapLimit: int}
     */
    public function forSite(): array
    {
        $counts = $this->audit->siteCounts();

        return $counts + [
            'beyondSitemap' => max(0, $counts['indexable'] - PostModel::SITEMAP_LIMIT),
            'sitemapLimit' => PostModel::SITEMAP_LIMIT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $blogId): array
    {
        $blog = $this->audit->blog($blogId) ?? ['blog_name' => '', 'indexable' => true, 'meta_description' => ''];
        $inSitemap = $this->audit->sitemapPostIds(PostModel::SITEMAP_LIMIT);
        $checks = array_fill_keys(self::CHECKS, []);
        $notOffered = [];
        $counted = [];
        $byTitle = [];

        foreach ($this->audit->postsOfBlog($blogId, self::POSTS_LIMIT) as $post) {
            $row = ['id' => (int) $post['id'], 'title' => (string) $post['title']];
            $counted[$row['id']] = (bool) $post['indexable'];

            if (!$post['indexable']) {
                $notOffered[] = $row + ['detail' => self::whyNotOffered($post, $blog)];

                continue;
            }

            $title = self::searchTitle($post, $blog['blog_name']);
            $byTitle[mb_strtolower($title)][] = $row;

            foreach (self::findings($post, $title) as $check => $detail) {
                $checks[$check][] = $row + ['detail' => $detail];
            }

            if (!isset($inSitemap[$row['id']])) {
                $checks['not_in_sitemap'][] = $row + ['detail' => null];
            }
        }

        foreach ($byTitle as $same) {
            if (count($same) > 1) {
                foreach ($same as $row) {
                    $checks['title_duplicate'][] = $row + ['detail' => count($same)];
                }
            }
        }

        return [
            'counted' => $counted,
            'blog' => array_keys(array_filter([
                'indexing_off' => !$blog['indexable'],
                'no_blog_description' => trim($blog['meta_description']) === '',
            ])),
            'checks' => $checks,
            'notOffered' => $notOffered,
        ] + self::counts($counted);
    }

    /**
     * What one indexable post is missing, check => what to show with it.
     *
     * @param  array<string, mixed>  $post
     * @return array<string, int|string|null>
     */
    private static function findings(array $post, string $title): array
    {
        $found = [];
        $description = trim((string) ($post['meta_description'] ?? '')) !== ''
            ? (string) $post['meta_description']
            : trim(strip_tags((string) ($post['excerpt'] ?? '')));

        if (mb_strlen($title) > self::TITLE_LENGTH) {
            $found['title_long'] = mb_strlen($title);
        }

        if ($description === '') {
            $found['no_description'] = null;
        }

        $missing = self::keywordMissing(mb_strtolower(trim((string) ($post['focus_keyword'] ?? ''))), $title, $description, (string) $post['slug']);
        if ($missing !== []) {
            $found['keyword_missing'] = implode(',', $missing);
        }

        if (trim((string) ($post['og_image'] ?? '')) !== '' && trim((string) ($post['og_image_alt'] ?? '')) === '') {
            $found['og_alt_missing'] = null;
        }

        $withoutAlt = self::imagesWithoutAlt((string) ($post['content'] ?? ''));
        if ($withoutAlt > 0) {
            $found['image_alt_missing'] = $withoutAlt;
        }

        return $found;
    }

    /**
     * Where a focus keyword doesn't appear: title, description, slug.
     *
     * @return list<string>
     */
    private static function keywordMissing(string $keyword, string $title, string $description, string $slug): array
    {
        if ($keyword === '') {
            return [];
        }

        $slugKeyword = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $keyword), '-');

        return array_keys(array_filter([
            'title' => !str_contains(mb_strtolower($title), $keyword),
            'description' => !str_contains(mb_strtolower($description), $keyword),
            'slug' => $slugKeyword !== '' && !str_contains(mb_strtolower($slug), $slugKeyword),
        ]));
    }

    private static function imagesWithoutAlt(string $content): int
    {
        if (!preg_match_all('/<img\b[^>]*>/i', $content, $images)) {
            return 0;
        }

        return count(array_filter(
            $images[0],
            static fn (string $tag): bool => !preg_match('/\balt\s*=\s*("[^"]*\S[^"]*"|\'[^\']*\S[^\']*\')/i', $tag)
        ));
    }

    /**
     * The title a search result shows, the same way the post page builds it.
     *
     * @param  array<string, mixed>  $post
     */
    private static function searchTitle(array $post, string $blogName): string
    {
        $metaTitle = trim((string) ($post['meta_title'] ?? ''));

        return $metaTitle !== '' ? $metaTitle : $post['title'].' - '.$blogName;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array{indexable: bool}  $blog
     */
    private static function whyNotOffered(array $post, array $blog): string
    {
        return match (true) {
            !$blog['indexable'] => 'blog',
            !empty($post['meta_noindex']) => 'noindex',
            $post['visibility'] !== 'public' => (string) $post['visibility'],
            trim((string) ($post['canonical_url'] ?? '')) !== '' => 'canonical',
            default => 'blog_hidden',
        };
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<int>  $postIds
     * @return array<string, mixed>
     */
    private static function only(array $report, array $postIds): array
    {
        $keep = array_fill_keys($postIds, true);
        $mine = static fn (array $rows): array => array_values(array_filter($rows, static fn (array $row): bool => isset($keep[$row['id']])));
        $counted = array_intersect_key($report['counted'], $keep);

        return [
            'counted' => $counted,
            'blog' => [],
            'checks' => array_map($mine, $report['checks']),
            'notOffered' => $mine($report['notOffered']),
        ] + self::counts($counted);
    }

    /**
     * @param  array<int, bool>  $counted
     * @return array{published: int, indexable: int}
     */
    private static function counts(array $counted): array
    {
        return ['published' => count($counted), 'indexable' => count(array_filter($counted))];
    }
}
