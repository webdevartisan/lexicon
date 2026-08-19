<?php

declare(strict_types=1);

namespace App\Models;

/**
 * One vote per user per post, with the same toggle behaviour as comments.
 *
 * Pressing the direction you already picked clears the vote, pressing the
 * other one flips it. Only the up count is published; the down count exists so
 * the signal is there, not so readers can watch a pile-on accumulate.
 */
class PostVoteModel extends AppModel
{
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
                $this->database->execute(
                    "DELETE FROM {$this->getTable()} WHERE post_id = ? AND user_id = ?",
                    [$postId, $userId]
                );
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
     * Published public posts the user has voted up, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function likedPosts(int $userId): array
    {
        $sql = "SELECT p.id, p.title, p.slug, p.excerpt, b.blog_slug, b.blog_name, l.created_at AS liked_at
                FROM {$this->getTable()} l
                INNER JOIN posts p ON p.id = l.post_id
                INNER JOIN blogs b ON b.id = p.blog_id
                WHERE l.user_id = ? AND l.value = 1 AND p.status = 'published' AND p.visibility = 'public'
                ORDER BY l.created_at DESC";

        return $this->database->query($sql, [$userId])->fetchAll(\PDO::FETCH_ASSOC);
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
