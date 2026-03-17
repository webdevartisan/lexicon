<?php

declare(strict_types=1);

namespace App\Models;

/**
 * CommentModel
 *
 * Manages user comments on published posts. Supports both authenticated
 * and anonymous commenting based on blog settings. Cascade deletion is
 * handled by database constraints.
 */
class CommentModel extends AppModel
{
    protected ?string $table = 'comments';

    /**
     * Get all comments for a given post.
     *
     * Returns comments with author names, preferring display_name_cached,
     * falling back to full name or username. Anonymous comments show NULL
     * for user_name when user_id is NULL.
     *
     * @param int $postId Post identifier
     * @return array List of comments ordered chronologically
     */
    public function forPost(int $postId): array
    {
        $sql = "
            SELECT
                c.*,
                COALESCE(
                    u.display_name_cached,
                    NULLIF(CONCAT_WS(' ', u.first_name, u.last_name), ' '),
                    u.username
                ) AS user_name
            FROM {$this->getTable()} c
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC
        ";

        $stmt = $this->database->query($sql, [$postId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all comments made by a specific user.
     *
     * Includes post titles to provide context for each comment.
     * Useful for user profile pages or comment management dashboards.
     *
     * @param int $userId User identifier
     * @return array List of user's comments with post titles, newest first
     */
    public function byUser(int $userId): array
    {
        $sql = "SELECT c.*, p.title AS post_title 
                FROM {$this->getTable()} c
                INNER JOIN posts p ON c.post_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC";

        $stmt = $this->database->query($sql, [$userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Find a comment by ID.
     *
     * @param int $id Comment identifier
     * @return array|null Comment data or null if not found
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->getTable()} WHERE id = ? LIMIT 1";
        $stmt = $this->database->query($sql, [$id]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Create a new comment.
     *
     * @param array $data Comment data (post_id, user_id, content)
     * @return int|false Inserted comment ID or false on failure
     */
    public function create(array $data): int|false
    {
        return $this->insert($data);
    }

    /**
     * Count comments for a post.
     *
     * @param int $postId Post identifier
     * @return int Total number of comments on the post
     */
    public function countForPost(int $postId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE post_id = ?";
        $stmt = $this->database->query($sql, [$postId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count all comments across all posts in a blog.
     *
     * Join comments with posts to count only comments belonging
     * to posts within the specified blog.
     *
     * @param int $blogId Blog identifier
     * @return int Total number of comments across all blog posts
     */
    public function countByBlogId(int $blogId): int
    {
        $sql = '
            SELECT COUNT(*) 
            FROM '.$this->getTable().' c
            INNER JOIN posts p ON c.post_id = p.id
            WHERE p.blog_id = ?
        ';
        $stmt = $this->database->query($sql, [$blogId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Delete all comments for a specific post.
     *
     * Database CASCADE constraint handles this automatically on post deletion,
     * but explicit method allows manual cleanup or soft-delete scenarios.
     *
     * @param int $postId Post identifier
     * @return bool True if rows were deleted
     */
    public function deleteByPostId(int $postId): bool
    {
        $sql = "DELETE FROM {$this->getTable()} WHERE post_id = ?";
        
        $rowCount = $this->database->execute($sql, [$postId]);
        return $rowCount > 0;
    }
}
