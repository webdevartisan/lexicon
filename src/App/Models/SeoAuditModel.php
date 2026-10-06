<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The post and blog fields the SEO checks read, and which posts the sitemap
 * lists. Nothing here is written.
 */
class SeoAuditModel extends AppModel
{
    protected ?string $table = 'posts';

    /**
     * A blog's published posts with their search fields, and whether each is
     * offered to search engines under PostModel::INDEXABLE_SQL.
     *
     * @return list<array<string, mixed>>
     */
    public function postsOfBlog(int $blogId, int $limit): array
    {
        return $this->database->query(
            'SELECT p.id, p.title, p.slug, p.content, p.excerpt, p.meta_title, p.meta_description, p.focus_keyword,
                    p.canonical_url, p.meta_noindex, p.visibility, p.og_image, p.og_image_alt, p.author_id,
                    ('.PostModel::INDEXABLE_SQL.') AS indexable
             FROM posts p
             JOIN blogs b ON b.id = p.blog_id
             LEFT JOIN blog_settings s ON s.blog_id = b.id
             WHERE p.blog_id = ? AND p.status = \'published\'
             ORDER BY p.published_at DESC
             LIMIT '.max(1, $limit),
            [$blogId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array{blog_name: string, indexable: bool, meta_description: string}|null
     */
    public function blog(int $blogId): ?array
    {
        $row = $this->database->query(
            "SELECT b.blog_name, COALESCE(s.indexable, 1) AS indexable, COALESCE(s.meta_description, '') AS meta_description
             FROM blogs b LEFT JOIN blog_settings s ON s.blog_id = b.id
             WHERE b.id = ?",
            [$blogId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'blog_name' => (string) $row['blog_name'],
            'indexable' => (bool) $row['indexable'],
            'meta_description' => (string) $row['meta_description'],
        ];
    }

    /**
     * The ids of the posts the sitemap lists: the newest $limit indexable posts.
     *
     * @return array<int, true>
     */
    public function sitemapPostIds(int $limit): array
    {
        $ids = $this->database->query(
            'SELECT p.id FROM posts p
             JOIN blogs b ON b.id = p.blog_id
             LEFT JOIN blog_settings s ON s.blog_id = b.id
             WHERE '.PostModel::INDEXABLE_SQL.'
             ORDER BY p.published_at DESC
             LIMIT '.max(1, $limit)
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_fill_keys(array_map('intval', $ids), true);
    }

    /**
     * Published and indexable posts across the site, for the control panel.
     *
     * @return array{published: int, indexable: int}
     */
    public function siteCounts(): array
    {
        $row = $this->database->query(
            'SELECT COUNT(*) AS published, COALESCE(SUM('.PostModel::INDEXABLE_SQL.'), 0) AS indexable
             FROM posts p
             JOIN blogs b ON b.id = p.blog_id
             LEFT JOIN blog_settings s ON s.blog_id = b.id
             WHERE p.status = \'published\''
        )->fetch(\PDO::FETCH_ASSOC);

        return ['published' => (int) $row['published'], 'indexable' => (int) $row['indexable']];
    }
}
