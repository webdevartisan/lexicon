<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Database;

/**
 * Reads and writes journey_stops: one place per post.
 *
 * Readers only ever see stops on posts that are published and public, so every
 * reader facing list goes through published().
 */
class StopModel
{
    private const EXPORT_COLUMNS = 'post_id, place_name, lat, lng, place_precision, stop_number';

    public function __construct(private Database $db)
    {
    }

    /**
     * The stop of one post, published or not.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $postId): ?array
    {
        $row = $this->db->query('SELECT * FROM journey_stops WHERE post_id = ?', [$postId])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Give a post its place, replacing any earlier one.
     */
    public function save(int $blogId, int $postId, Stop $stop): void
    {
        $row = array_values($stop->toRow());

        $this->db->execute(
            'INSERT INTO journey_stops (post_id, blog_id, place_name, lat, lng, place_precision, stop_number)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE blog_id = VALUES(blog_id), place_name = VALUES(place_name),
                 lat = VALUES(lat), lng = VALUES(lng), place_precision = VALUES(place_precision),
                 stop_number = VALUES(stop_number)',
            [$postId, $blogId, ...$row]
        );
    }

    /**
     * Take a post's place away.
     */
    public function remove(int $postId): void
    {
        $this->db->execute('DELETE FROM journey_stops WHERE post_id = ?', [$postId]);
    }

    /**
     * Whether the post belongs to the blog, so one blog can never write stops onto another's posts.
     */
    public function postInBlog(int $postId, int $blogId): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM posts WHERE id = ? AND blog_id = ?', [$postId, $blogId])
            ->fetchColumn();
    }

    /**
     * Every stop readers may see, in journey order: by stop number, then unnumbered stops by publish date.
     *
     * @return list<array<string, mixed>>
     */
    public function published(int $blogId): array
    {
        return $this->db->query(
            "SELECT s.post_id, p.title, p.slug, p.excerpt, p.featured_image,
                    s.place_name, s.lat, s.lng, s.place_precision, s.stop_number
             FROM journey_stops s
             JOIN posts p ON p.id = s.post_id
             WHERE s.blog_id = ? AND p.blog_id = s.blog_id
               AND p.status = 'published' AND p.visibility = 'public'
             ORDER BY s.stop_number IS NULL, s.stop_number, p.published_at, p.id",
            [$blogId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Every stop of a blog with its post's status, for the people who run the blog.
     * Same order as the journey, with posts that have no publish date at the end.
     *
     * @return list<array<string, mixed>>
     */
    public function forBlog(int $blogId): array
    {
        return $this->db->query(
            "SELECT s.post_id, p.title, p.status, p.visibility,
                    s.place_name, s.place_precision, s.stop_number
             FROM journey_stops s
             JOIN posts p ON p.id = s.post_id
             WHERE s.blog_id = ? AND p.blog_id = s.blog_id
             ORDER BY s.stop_number IS NULL, s.stop_number, p.published_at IS NULL, p.published_at, p.id",
            [$blogId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Each blog with the module on and its stops, for the admin's usage table. Stops carry no creation
     * time, so the last change stands in for the last stop added.
     *
     * @return list<array{blog_id: int, blog_name: string, stops: int, public_stops: int, last_added: ?string}>
     */
    public function usage(): array
    {
        $rows = $this->db->query(
            "SELECT b.id AS blog_id, b.blog_name, COUNT(s.post_id) AS stops,
                    COALESCE(SUM(p.status = 'published' AND p.visibility = 'public'), 0) AS public_stops,
                    MAX(s.updated_at) AS last_added
             FROM blog_modules bm
             JOIN blogs b ON b.id = bm.blog_id
             LEFT JOIN journey_stops s ON s.blog_id = b.id
             LEFT JOIN posts p ON p.id = s.post_id AND p.blog_id = s.blog_id
             WHERE bm.module_key = ?
             GROUP BY b.id, b.blog_name
             ORDER BY stops DESC, b.blog_name",
            ['journey-map']
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => [
            'blog_id' => (int) $row['blog_id'],
            'blog_name' => (string) $row['blog_name'],
            'stops' => (int) $row['stops'],
            'public_stops' => (int) $row['public_stops'],
            'last_added' => $row['last_added'] === null ? null : (string) $row['last_added'],
        ], $rows ?: []);
    }

    /**
     * Stops of blogs with the module on that lie outside a box (west, south, east, north), so the admin
     * knows which places a limited map shows only in the overview.
     *
     * @param list<float> $bbox
     * @return list<array{post_id: int, title: string, blog_name: string, place_name: string}>
     */
    public function outside(array $bbox): array
    {
        [$west, $south, $east, $north] = $bbox;
        $rows = $this->db->query(
            'SELECT s.post_id, p.title, b.blog_name, s.place_name
             FROM journey_stops s
             JOIN posts p ON p.id = s.post_id
             JOIN blogs b ON b.id = s.blog_id
             JOIN blog_modules bm ON bm.blog_id = s.blog_id AND bm.module_key = ?
             WHERE s.lng < ? OR s.lng > ? OR s.lat < ? OR s.lat > ?
             ORDER BY b.blog_name, p.title
             LIMIT 200',
            ['journey-map', $west, $east, $south, $north]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => [
            'post_id' => (int) $row['post_id'],
            'title' => (string) $row['title'],
            'blog_name' => (string) $row['blog_name'],
            'place_name' => (string) $row['place_name'],
        ], $rows ?: []);
    }

    /**
     * Whether readers would see at least one stop, checked on every page of the blog for its menu link.
     */
    public function hasPublished(int $blogId): bool
    {
        return (bool) $this->db->query(
            "SELECT 1 FROM journey_stops s
             JOIN posts p ON p.id = s.post_id
             WHERE s.blog_id = ? AND p.blog_id = s.blog_id
               AND p.status = 'published' AND p.visibility = 'public'
             LIMIT 1",
            [$blogId]
        )->fetchColumn();
    }

    /**
     * The public stop that follows this post on the journey, or null for the last one.
     *
     * @return array<string, mixed>|null
     */
    public function nextAfter(int $blogId, int $postId): ?array
    {
        $stops = $this->published($blogId);
        $ids = array_map('intval', array_column($stops, 'post_id'));
        $position = array_search($postId, $ids, true);

        return $position === false ? null : ($stops[$position + 1] ?? null);
    }

    /**
     * Whether the blog has any stop, public or not.
     */
    public function hasBlogData(int $blogId): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM journey_stops WHERE blog_id = ? LIMIT 1', [$blogId])
            ->fetchColumn();
    }

    /**
     * Remove every stop of the blog.
     */
    public function eraseBlog(int $blogId): void
    {
        $this->db->execute('DELETE FROM journey_stops WHERE blog_id = ?', [$blogId]);
    }

    /**
     * The blog's stops for a backup, keyed by the post ids the backup carries.
     *
     * @return list<array<string, mixed>>
     */
    public function exportBlog(int $blogId): array
    {
        return $this->db->query(
            'SELECT '.self::EXPORT_COLUMNS.' FROM journey_stops WHERE blog_id = ? ORDER BY post_id',
            [$blogId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }
}
