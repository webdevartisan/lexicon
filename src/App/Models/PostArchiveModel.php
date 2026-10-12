<?php

declare(strict_types=1);

namespace App\Models;

/**
 * The queries the blog archive needs on posts, kept apart from PostModel.
 *
 * Writes here go through the base insert and update, which do not purge any
 * cache. An import touches hundreds of posts and purges the blog's caches once
 * at the end; PostModel::update() would purge them once per post.
 */
final class PostArchiveModel extends AppModel
{
    protected ?string $table = 'posts';

    /**
     * Columns read for an export, besides the joins.
     */
    private const EXPORT_COLUMNS = 'p.id, p.title, p.slug, p.content, p.excerpt, p.featured_image, p.status,
        p.visibility, p.published_at, p.timezone, p.comments_enabled, p.focus_keyword, p.meta_title,
        p.meta_description, p.canonical_url, p.meta_noindex, p.meta_nofollow, p.og_title, p.og_description,
        p.og_image, p.og_image_alt, p.twitter_card_type, p.twitter_title, p.twitter_description, p.twitter_image';

    /**
     * The next page of a blog's exportable posts, oldest id first, with category and author name.
     * Moderated posts are never exported.
     *
     * @param  int  $afterId  Last id of the previous page, 0 for the first
     * @param  bool  $includeDrafts  Whether posts that are not live (draft, pending, scheduled, archived) are included
     * @return list<array<string, mixed>>
     */
    public function exportPage(int $blogId, int $afterId, int $limit, bool $includeDrafts): array
    {
        $statuses = $includeDrafts ? "('draft','pending','scheduled','published','archived')" : "('published')";

        $sql = 'SELECT '.self::EXPORT_COLUMNS.",
                       c.name AS category_name, c.slug AS category_slug,
                       COALESCE(NULLIF(u.display_name_cached, ''), u.handle) AS author_name
                  FROM posts p
                  LEFT JOIN categories c ON c.id = p.category_id AND c.blog_id = p.blog_id
                  LEFT JOIN users u ON u.id = p.author_id
                 WHERE p.blog_id = ? AND p.id > ? AND p.status IN {$statuses}
                 ORDER BY p.id
                 LIMIT ".max(1, $limit);

        return $this->database->query($sql, [$blogId, $afterId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tags of the given posts, grouped by post id.
     *
     * @param  int[]  $postIds
     * @return array<int, list<array{name: string, slug: string}>>
     */
    public function tagsForPosts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $sql = "SELECT pt.post_id, t.name, t.slug
                  FROM post_tags pt
                  JOIN tags t ON t.id = pt.tag_id
                 WHERE pt.post_id IN ({$placeholders})
                 ORDER BY t.name";

        $grouped = [];
        foreach ($this->database->query($sql, array_map('intval', $postIds))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $grouped[(int) $row['post_id']][] = ['name' => (string) $row['name'], 'slug' => (string) $row['slug']];
        }

        return $grouped;
    }

    /**
     * Posts held back by moderation, which an export lists but never includes.
     *
     * @return list<array{title: string, slug: string}>
     */
    public function moderatedInBlog(int $blogId): array
    {
        $sql = "SELECT title, slug FROM posts WHERE blog_id = ? AND status = 'moderated' ORDER BY id";

        return $this->database->query($sql, [$blogId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Which of the given slugs a blog already uses, with the post holding each.
     *
     * @param  string[]  $slugs
     * @return array<string, array{id: int, status: string, title: string}>
     */
    public function existingSlugs(int $blogId, array $slugs): array
    {
        $found = [];

        foreach (array_chunk(array_values(array_unique($slugs)), 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT id, slug, status, title FROM posts WHERE blog_id = ? AND slug IN ({$placeholders})";

            foreach ($this->database->query($sql, array_merge([$blogId], $chunk))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $found[(string) $row['slug']] = [
                    'id' => (int) $row['id'],
                    'status' => (string) $row['status'],
                    'title' => (string) $row['title'],
                ];
            }
        }

        return $found;
    }

    /**
     * Whether a post is in the subscriber log already, so an overwrite never clears it.
     */
    public function subscribersNotified(int $postId): bool
    {
        $sql = 'SELECT subscribers_notified_at IS NOT NULL FROM posts WHERE id = ?';

        return (bool) $this->database->query($sql, [$postId])->fetchColumn();
    }
}
