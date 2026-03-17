<?php

declare(strict_types=1);

namespace App\Models;

/**
 * TagModel
 *
 * Manages content tags and their associations with posts through the
 * post_tags pivot table. Tags enable content discovery and organization
 * across multiple posts.
 */
class TagModel extends AppModel
{
    protected ?string $table = 'tags';

    /**
     * Find a tag by slug.
     *
     * @param string $slug Tag URL slug
     * @return array|null Tag data or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE slug = :slug LIMIT 1";
        $stmt = $this->database->query($sql, [':slug' => $slug]);

        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all published posts associated with a tag.
     *
     * Join through post_tags pivot table to fetch posts tagged
     * with the specified tag.
     *
     * @param int $tagId Tag identifier
     * @return array List of published posts, newest first
     */
    public function posts(int $tagId): array
    {
        $sql = "SELECT p.* 
                FROM posts p
                INNER JOIN post_tags pt ON p.id = pt.post_id
                WHERE pt.tag_id = :tag_id
                AND p.status = 'published'
                ORDER BY p.created_at DESC";
        $stmt = $this->database->query($sql, [':tag_id' => $tagId]);

        return $stmt->fetchAll();
    }

    /**
     * Attach a tag to a post.
     *
     * Creates a many-to-many relationship in the post_tags pivot table.
     * Uses INSERT IGNORE to prevent duplicate associations.
     *
     * @param int $postId Post identifier
     * @param int $tagId Tag identifier
     * @return bool True if attached (or already exists), false on error
     */
    public function attachToPost(int $postId, int $tagId): bool
    {
        $sql = 'INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)';
        $affected = $this->database->execute($sql, [
            ':post_id' => $postId,
            ':tag_id' => $tagId
        ]);
        // 0 = already exists, 1 = newly inserted, both are success
        return $affected >= 0;
    }

    /**
     * Detach a tag from a post.
     *
     * Removes the many-to-many relationship from the post_tags pivot table.
     *
     * @param int $postId Post identifier
     * @param int $tagId Tag identifier
     * @return bool True if detached, false if didn't exist or error
     */
    public function detachFromPost(int $postId, int $tagId): bool
    {
        $sql = 'DELETE FROM post_tags WHERE post_id = :post_id AND tag_id = :tag_id';
        $affected = $this->database->execute($sql, [
            ':post_id' => $postId,
            ':tag_id' => $tagId
        ]);
        return $affected > 0; // Only true if actually removed
    }
}
