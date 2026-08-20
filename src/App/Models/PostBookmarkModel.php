<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\ListsEngagedPosts;

/**
 * One bookmark per user per post, toggled on and off.
 */
class PostBookmarkModel extends AppModel
{
    use ListsEngagedPosts;

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
            $this->remove($userId, $postId);

            return false;
        }

        // A parallel request may have saved it between the check above and
        // here. IGNORE settles that in the statement: catching the violation
        // does not work, because Database::query() rewraps PDOException as a
        // RuntimeException and a catch for the former never fires.
        $this->database->execute(
            "INSERT IGNORE INTO {$this->getTable()} (post_id, user_id) VALUES (?, ?)",
            [$postId, $userId]
        );

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
     * Delete the user's bookmark on a post.
     *
     * Unconditional, unlike toggle(): the reader's Saved page means "remove
     * this", and a toggle would re-save a row another tab already removed.
     *
     * @return bool True when a row was actually deleted
     */
    public function remove(int $userId, int $postId): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE post_id = ? AND user_id = ?",
            [$postId, $userId]
        ) > 0;
    }

    /**
     * One page of the reader's saved posts, newest save first.
     *
     * Ordered by the save, not the post: the list is a reading queue in the
     * order things were put on it. The id tiebreak keeps rows from swapping
     * across a page boundary when two saves share a timestamp.
     *
     * @param  int  $userId  Owner of the list
     * @param  int  $page  1-based page index
     * @param  int  $perPage  Rows per page
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function pageForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->pageOfEngagedPosts($userId, $page, $perPage);
    }
}
