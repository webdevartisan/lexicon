<?php

declare(strict_types=1);

namespace App\Models;

/**
 * One vote per user per comment, with YouTube-style toggle behaviour.
 *
 * Pressing the direction you already picked clears the vote, pressing the
 * other one flips it. Running totals live on the comment row so a thread can
 * be rendered without an aggregate per comment.
 */
class CommentVoteModel extends AppModel
{
    public const UP = 1;

    public const DOWN = -1;

    protected ?string $table = 'comment_votes';

    /**
     * Cast, flip, or clear the viewer's vote and return the fresh totals.
     *
     * @param  int  $userId  Voter
     * @param  int  $commentId  Comment being voted on
     * @param  int  $value  self::UP or self::DOWN
     * @return array{up: int, down: int, mine: int} Totals plus the viewer's resulting vote (0 = none)
     */
    public function apply(int $userId, int $commentId, int $value): array
    {
        return $this->transaction(function () use ($userId, $commentId, $value): array {
            $current = $this->userVote($userId, $commentId);

            if ($current === $value) {
                $this->database->execute(
                    "DELETE FROM {$this->getTable()} WHERE comment_id = ? AND user_id = ?",
                    [$commentId, $userId]
                );
                $mine = 0;
            } else {
                // A unique key on (comment_id, user_id) turns the flip into an
                // update, so a double-click cannot leave two rows behind.
                $this->database->execute(
                    "INSERT INTO {$this->getTable()} (comment_id, user_id, value) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    [$commentId, $userId, $value]
                );
                $mine = $value;
            }

            $totals = $this->syncCounters($commentId);
            $totals['mine'] = $mine;

            return $totals;
        });
    }

    /**
     * The viewer's current vote on a comment.
     *
     * @return int self::UP, self::DOWN, or 0 when they have not voted
     */
    public function userVote(int $userId, int $commentId): int
    {
        $row = $this->database->query(
            "SELECT value FROM {$this->getTable()} WHERE user_id = ? AND comment_id = ? LIMIT 1",
            [$userId, $commentId]
        )->fetchColumn();

        return $row === false ? 0 : (int) $row;
    }

    /**
     * Every vote the viewer has cast on one post's comments.
     *
     * One query for the whole thread keeps the per-comment lookup out of the
     * render loop.
     *
     * @return array<int, int> Comment id => vote value
     */
    public function votesForPost(int $userId, int $postId): array
    {
        $sql = "SELECT v.comment_id, v.value
                FROM {$this->getTable()} v
                INNER JOIN comments c ON c.id = v.comment_id
                WHERE v.user_id = ? AND c.post_id = ?";

        $rows = $this->database->query($sql, [$userId, $postId])->fetchAll(\PDO::FETCH_ASSOC);

        $votes = [];
        foreach ($rows as $row) {
            $votes[(int) $row['comment_id']] = (int) $row['value'];
        }

        return $votes;
    }

    /**
     * Recount a comment's votes and write the totals back onto its row.
     *
     * Recounting rather than incrementing keeps the denormalised columns exact
     * when two people vote at the same moment.
     *
     * @return array{up: int, down: int} The stored totals
     */
    private function syncCounters(int $commentId): array
    {
        $this->database->execute(
            "UPDATE comments c SET
                c.upvotes = (SELECT COUNT(*) FROM {$this->getTable()} WHERE comment_id = c.id AND value = 1),
                c.downvotes = (SELECT COUNT(*) FROM {$this->getTable()} WHERE comment_id = c.id AND value = -1)
             WHERE c.id = ?",
            [$commentId]
        );

        $row = $this->database->query(
            'SELECT upvotes, downvotes FROM comments WHERE id = ? LIMIT 1',
            [$commentId]
        )->fetch(\PDO::FETCH_ASSOC);

        return [
            'up' => (int) ($row['upvotes'] ?? 0),
            'down' => (int) ($row['downvotes'] ?? 0),
        ];
    }
}
