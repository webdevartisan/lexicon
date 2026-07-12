<?php

declare(strict_types=1);

namespace App\Models;

/**
 * One like per user per post, toggled on and off.
 */
class PostLikeModel extends AppModel
{
    protected ?string $table = 'post_likes';

    /**
     * Whether the user already likes the post.
     */
    public function userLikes(int $userId, int $postId): bool
    {
        $sql = "SELECT 1 FROM {$this->getTable()} WHERE user_id = ? AND post_id = ? LIMIT 1";

        return (bool) $this->database->query($sql, [$userId, $postId])->fetchColumn();
    }

    /**
     * Add the like if missing, remove it if present.
     *
     * @return bool True when the post is now liked, false when unliked
     */
    public function toggle(int $userId, int $postId): bool
    {
        if ($this->userLikes($userId, $postId)) {
            $this->database->query(
                "DELETE FROM {$this->getTable()} WHERE post_id = ? AND user_id = ?",
                [$postId, $userId]
            );

            return false;
        }

        try {
            $this->database->query(
                "INSERT INTO {$this->getTable()} (post_id, user_id) VALUES (?, ?)",
                [$postId, $userId]
            );
        } catch (\PDOException $e) {
            // Unique key means a parallel request already liked it; treat as liked
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Published public posts the user has liked, newest like first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function likedPosts(int $userId): array
    {
        $sql = "SELECT p.id, p.title, p.slug, p.excerpt, b.blog_slug, b.blog_name, l.created_at AS liked_at
                FROM {$this->getTable()} l
                INNER JOIN posts p ON p.id = l.post_id
                INNER JOIN blogs b ON b.id = p.blog_id
                WHERE l.user_id = ? AND p.status = 'published' AND p.visibility = 'public'
                ORDER BY l.created_at DESC";

        return $this->database->query($sql, [$userId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Total likes on a post.
     */
    public function countByPost(int $postId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE post_id = ?";

        return (int) $this->database->query($sql, [$postId])->fetchColumn();
    }
}
