<?php

declare(strict_types=1);

namespace App\Models;

/**
 * One bookmark per user per post, toggled on and off.
 */
class PostBookmarkModel extends AppModel
{
    protected ?string $table = 'post_bookmarks';

    /**
     * Whether the user has saved the post.
     */
    public function userBookmarks(int $userId, int $postId): bool
    {
        $sql = "SELECT 1 FROM {$this->getTable()} WHERE user_id = ? AND post_id = ? LIMIT 1";

        return (bool) $this->database->query($sql, [$userId, $postId])->fetchColumn();
    }

    /**
     * Add the bookmark if missing, remove it if present.
     *
     * @return bool True when the post is now saved, false when removed
     */
    public function toggle(int $userId, int $postId): bool
    {
        if ($this->userBookmarks($userId, $postId)) {
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
            // Unique key means a parallel request already saved it; treat as saved
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }

        return true;
    }

    /**
     * Total bookmarks on a post.
     */
    public function countByPost(int $postId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE post_id = ?";

        return (int) $this->database->query($sql, [$postId])->fetchColumn();
    }

    /**
     * Posts the user has saved, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bookmarkedPosts(int $userId): array
    {
        $sql = "SELECT p.id, p.title, p.slug, p.excerpt, bl.blog_slug, bl.blog_name, b.created_at AS bookmarked_at
                FROM {$this->getTable()} b
                INNER JOIN posts p ON p.id = b.post_id
                INNER JOIN blogs bl ON bl.id = p.blog_id
                WHERE b.user_id = ? AND p.status = 'published' AND p.visibility = 'public'
                ORDER BY b.created_at DESC";

        return $this->database->query($sql, [$userId])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
