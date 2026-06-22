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
     * @param  string  $slug  Tag URL slug
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
     * @param  int  $tagId  Tag identifier
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
     * @param  int  $postId  Post identifier
     * @param  int  $tagId  Tag identifier
     * @return bool True if attached (or already exists), false on error
     */
    public function attachToPost(int $postId, int $tagId): bool
    {
        $sql = 'INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)';
        $affected = $this->database->execute($sql, [
            ':post_id' => $postId,
            ':tag_id' => $tagId,
        ]);

        // 0 = already exists, 1 = newly inserted, both are success
        return $affected >= 0;
    }

    /**
     * Detach a tag from a post.
     *
     * Removes the many-to-many relationship from the post_tags pivot table.
     *
     * @param  int  $postId  Post identifier
     * @param  int  $tagId  Tag identifier
     * @return bool True if detached, false if didn't exist or error
     */
    public function detachFromPost(int $postId, int $tagId): bool
    {
        $sql = 'DELETE FROM post_tags WHERE post_id = :post_id AND tag_id = :tag_id';
        $affected = $this->database->execute($sql, [
            ':post_id' => $postId,
            ':tag_id' => $tagId,
        ]);

        return $affected > 0; // Only true if actually removed
    }

    /**
     * Tags belonging to a blog, with how many posts use each.
     */
    public function getByBlogId(int $blogId): array
    {
        $sql = 'SELECT t.*, (SELECT COUNT(*) FROM post_tags WHERE tag_id = t.id) AS post_count
                FROM tags t
                WHERE t.blog_id = ?
                ORDER BY t.name ASC';
        $stmt = $this->database->query($sql, [$blogId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Look up a tag by slug within a blog.
     */
    public function findBySlugInBlog(int $blogId, string $slug): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE blog_id = ? AND slug = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$blogId, $slug]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function findForBlog(int $id, int $blogId): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? AND blog_id = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$id, $blogId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Find a tag by name in a blog, creating it if it doesn't exist.
     */
    public function findOrCreateForBlog(int $blogId, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $slug = slugify($name) ?: 'tag';

        $existing = $this->findBySlugInBlog($blogId, $slug);
        if ($existing) {
            return (int) $existing['id'];
        }

        $this->insert(['blog_id' => $blogId, 'name' => $name, 'slug' => $slug]);

        return $this->getInsertID();
    }

    /**
     * Replace a post's tag set with the given names (find-or-create per blog).
     *
     * @param  string[]  $names  Tag names typed by the author
     * @return bool True if the post's tag set actually changed
     */
    public function syncForPost(int $postId, int $blogId, array $names): bool
    {
        // Resolve names to ids, de-duped, ignoring blanks.
        $wanted = [];
        foreach ($names as $name) {
            $id = $this->findOrCreateForBlog($blogId, (string) $name);
            if ($id !== null) {
                $wanted[$id] = true;
            }
        }
        $wantedIds = array_keys($wanted);

        $current = array_map(
            static fn (array $row): int => (int) $row['tag_id'],
            $this->database->query('SELECT tag_id FROM post_tags WHERE post_id = ?', [$postId])->fetchAll(\PDO::FETCH_ASSOC)
        );

        $toAdd = array_diff($wantedIds, $current);
        $toRemove = array_diff($current, $wantedIds);

        foreach ($toAdd as $tagId) {
            $this->attachToPost($postId, (int) $tagId);
        }
        foreach ($toRemove as $tagId) {
            $this->detachFromPost($postId, (int) $tagId);
        }

        return $toAdd !== [] || $toRemove !== [];
    }

    /**
     * Rename a tag (and re-slug it) within its blog.
     */
    public function renameForBlog(int $id, int $blogId, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $slug = slugify($name) ?: 'tag';

        $clash = $this->findBySlugInBlog($blogId, $slug);
        if ($clash && (int) $clash['id'] !== $id) {
            return false;
        }

        return $this->update($id, ['name' => $name, 'slug' => $slug]);
    }
}
