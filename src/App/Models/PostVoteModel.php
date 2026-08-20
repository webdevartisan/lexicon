<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\ListsEngagedPosts;

/**
 * One vote per user per post, with the same toggle behaviour as comments.
 *
 * Pressing the direction you already picked clears the vote, pressing the
 * other one flips it. Only the up count is published; the down count exists so
 * the signal is there, not so readers can watch a pile-on accumulate.
 */
class PostVoteModel extends AppModel
{
    use ListsEngagedPosts;

    public const UP = 1;

    public const DOWN = -1;

    protected ?string $table = 'post_votes';

    /**
     * The viewer's current vote on a post.
     *
     * @return int self::UP, self::DOWN, or 0 when they have not voted
     */
    public function userVote(int $userId, int $postId): int
    {
        $row = $this->database->query(
            "SELECT value FROM {$this->getTable()} WHERE user_id = ? AND post_id = ? LIMIT 1",
            [$userId, $postId]
        )->fetchColumn();

        return $row === false ? 0 : (int) $row;
    }

    /**
     * Cast, flip, or clear the viewer's vote and return the fresh totals.
     *
     * @param  int  $userId  Voter
     * @param  int  $postId  Post being voted on
     * @param  int  $value  self::UP or self::DOWN
     * @return array{up: int, down: int, mine: int} Totals plus the viewer's resulting vote (0 = none)
     */
    public function apply(int $userId, int $postId, int $value): array
    {
        return $this->transaction(function () use ($userId, $postId, $value): array {
            if ($this->userVote($userId, $postId) === $value) {
                $this->remove($userId, $postId);
                $mine = 0;
            } else {
                // A unique key on (post_id, user_id) turns the flip into an
                // update, so a double-click cannot leave two rows behind.
                $this->database->execute(
                    "INSERT INTO {$this->getTable()} (post_id, user_id, value) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    [$postId, $userId, $value]
                );
                $mine = $value;
            }

            return [
                'up' => $this->countByPost($postId),
                'down' => $this->countByPost($postId, self::DOWN),
                'mine' => $mine,
            ];
        });
    }

    /**
     * Delete the user's vote on a post, whichever direction it was.
     *
     * Unconditional, unlike apply(): the reader's Liked page means "remove
     * this", and a toggle would re-cast a vote another tab already cleared.
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
     * One page of the reader's liked posts, newest like first.
     *
     * Up votes only. A down vote is a signal for the blog, never something the
     * reader is handed back as a list of things they disliked.
     *
     * @param  int  $userId  Owner of the list
     * @param  int  $page  1-based page index
     * @param  int  $perPage  Rows per page
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function pageOfLikesForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->pageOfEngagedPosts($userId, $page, $perPage, 'e.value = ?', [self::UP]);
    }


    /**
     * Votes of one direction on a post.
     *
     * @param  int  $direction  self::UP or self::DOWN
     */
    public function countByPost(int $postId, int $direction = self::UP): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTable()} WHERE post_id = ? AND value = ?";

        return (int) $this->database->query($sql, [$postId, $direction])->fetchColumn();
    }
}
