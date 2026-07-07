<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Stores reviewer feedback and decisions for posts under review.
 *
 * NOTE: the `decision` ENUM uses `needs_revision`, while the post pipeline uses
 * the `workflow_state` value `needs_changes`. The WorkflowService (Plan 2) maps
 * between them: decision `needs_revision` -> workflow_state `needs_changes`,
 * decision `approved` -> workflow_state `approved`.
 */
class ReviewModel extends AppModel
{
    protected ?string $table = 'reviews';

    /**
     * Valid decision values, matching the reviews.decision ENUM.
     */
    public const DECISIONS = ['pending', 'approved', 'rejected', 'needs_revision'];

    /**
     * Record a review decision with optional feedback.
     *
     * @param  int  $postId  Reviewed post
     * @param  int  $reviewerId  Reviewer
     * @param  string  $decision  One of DECISIONS
     * @param  string  $feedback  Reviewer notes to the author
     * @return bool True on success
     *
     * @throws \InvalidArgumentException If decision is not a valid ENUM value
     */
    public function create(int $postId, int $reviewerId, string $decision, string $feedback = ''): bool
    {
        if (!in_array($decision, self::DECISIONS, true)) {
            throw new \InvalidArgumentException("Invalid review decision: {$decision}");
        }

        $sql = 'INSERT INTO reviews (post_id, reviewer_id, decision, feedback)
                VALUES (?, ?, ?, ?)';

        return $this->database->execute($sql, [$postId, $reviewerId, $decision, $feedback]) > 0;
    }

    /**
     * Get all review rounds for a post (newest first).
     *
     * @param  int  $postId  Post ID
     * @return array List of review rows
     */
    public function findByPost(int $postId): array
    {
        $sql = 'SELECT r.id, r.reviewer_id, r.decision, r.feedback, r.reviewed_at,
                       u.username AS reviewer_username
                FROM reviews r
                LEFT JOIN users u ON u.id = r.reviewer_id
                WHERE r.post_id = ?
                ORDER BY r.reviewed_at DESC';

        return $this->database->query($sql, [$postId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the single most recent review for a post.
     *
     * Used to surface reviewer feedback on the author's edit page without
     * requiring them to navigate to the review screen.
     *
     * @param  int  $postId  Post ID
     * @return array|null Most recent review row, or null if none exist
     */
    public function findLatestByPost(int $postId): ?array
    {
        $sql = 'SELECT r.id, r.reviewer_id, r.decision, r.feedback, r.reviewed_at,
                       u.username AS reviewer_username
                FROM reviews r
                LEFT JOIN users u ON u.id = r.reviewer_id
                WHERE r.post_id = ?
                ORDER BY r.reviewed_at DESC
                LIMIT 1';

        $row = $this->database->query($sql, [$postId])->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
