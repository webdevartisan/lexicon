<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Manages reviewer assignments for posts.
 *
 * v1 uses a single reviewer per post; the table structurally supports multiple,
 * with the single-reviewer constraint enforced by the workflow layer (Plan 2).
 */
class PostReviewerModel extends AppModel
{
    protected ?string $table = 'post_reviewers';

    /**
     * Assign a reviewer to a post (idempotent via the unique post+reviewer key).
     *
     * @param  int  $postId  Post under review
     * @param  int  $reviewerId  Assigned reviewer
     * @param  int  $assignedBy  User performing the assignment (owner/editor, or reviewer self-assign)
     * @return bool True on success
     */
    public function assign(int $postId, int $reviewerId, int $assignedBy): bool
    {
        $sql = 'INSERT INTO post_reviewers (post_id, reviewer_id, assigned_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = UTC_TIMESTAMP()';

        return $this->database->execute($sql, [$postId, $reviewerId, $assignedBy]) > 0;
    }

    /**
     * Get all reviewer assignments for a post, with reviewer usernames.
     *
     * @param  int  $postId  Post ID
     * @return array<int, array<string, mixed>> List of assignment rows
     */
    public function findByPost(int $postId): array
    {
        $sql = 'SELECT pr.id, pr.post_id, pr.reviewer_id, pr.assigned_by, pr.assigned_at, pr.review_status,
                       u.username AS reviewer_username
                FROM post_reviewers pr
                LEFT JOIN users u ON u.id = pr.reviewer_id
                WHERE pr.post_id = ?';

        return $this->database->query($sql, [$postId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all posts awaiting this reviewer's action.
     *
     * Returns two sets per the spec:
     * 1. Posts explicitly assigned to this reviewer (priority — shown first).
     * 2. Unassigned in-review posts on blogs where this user is an active reviewer.
     *
     * Only includes blogs with workflow enabled.
     *
     * @param  int  $reviewerId  The reviewer's user ID
     * @return array<int, array<string, mixed>> Posts with title, id, workflow_state, blog name, is_assigned flag
     */
    public function findPendingForReviewer(int $reviewerId): array
    {
        $sql = 'SELECT p.id, p.title, p.slug, p.workflow_state, p.updated_at,
                       b.blog_name, u.username AS author_username,
                       (pr.reviewer_id IS NOT NULL) AS is_assigned
                FROM posts p
                INNER JOIN blogs b ON b.id = p.blog_id
                INNER JOIN blog_settings bs ON bs.blog_id = p.blog_id
                INNER JOIN users u ON u.id = p.author_id
                LEFT JOIN post_reviewers pr ON pr.post_id = p.id AND pr.reviewer_id = ?
                WHERE p.workflow_state IN (\'in_review\', \'needs_changes\')
                  AND bs.workflow_enabled = 1
                  AND (
                    pr.reviewer_id = ?
                    OR (
                      NOT EXISTS (SELECT 1 FROM post_reviewers pr2 WHERE pr2.post_id = p.id)
                      AND EXISTS (
                        SELECT 1 FROM blog_users bu
                        WHERE bu.blog_id = p.blog_id
                          AND bu.user_id = ?
                          AND bu.is_active = 1
                          AND bu.role IN (\'reviewer\', \'editor\', \'owner\')
                      )
                    )
                  )
                ORDER BY is_assigned DESC, p.updated_at DESC';

        return $this->database->query($sql, [$reviewerId, $reviewerId, $reviewerId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all posts currently locked under review, visible to a supervisor.
     *
     * Returns posts where workflow_state = in_review on blogs where the given
     * user is owner or editor (or all blogs for site admins). Used to surface
     * the "In review now" panel on the dashboard home for editorial supervisors.
     *
     * @param  int  $userId  The supervisor's user ID
     * @param  bool  $isAdmin  When true, returns posts across all blogs
     * @param  int|null  $blogId  Optional: scope to a single blog
     * @param  int  $limit  Max rows to return
     * @return array<int, array<string, mixed>> Posts with reviewer info and blog name
     */
    public function findInReviewForSupervisor(int $userId, bool $isAdmin = false, ?int $blogId = null, int $limit = 5): array
    {
        $params = [];
        $scopeClause = '';

        if (!$isAdmin) {
            // Non-admins see in-flight reviews only on blogs they own or edit.
            $scopeClause = ' AND (
                b.owner_id = ?
                OR EXISTS (
                    SELECT 1 FROM blog_users bu
                    WHERE bu.blog_id = p.blog_id
                      AND bu.user_id = ?
                      AND bu.is_active = 1
                      AND bu.role IN (\'editor\', \'owner\')
                )
            )';
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($blogId !== null) {
            $scopeClause .= ' AND p.blog_id = ?';
            $params[] = $blogId;
        }

        $params[] = $limit;

        $sql = "SELECT p.id, p.title, p.slug, p.workflow_state, p.status,
                       b.id AS blog_id, b.blog_name,
                       au.username AS author_username,
                       ru.username AS reviewer_username,
                       pr.reviewer_id, pr.assigned_at
                FROM posts p
                INNER JOIN post_reviewers pr ON pr.post_id = p.id
                INNER JOIN blogs b ON b.id = p.blog_id
                INNER JOIN users au ON au.id = p.author_id
                INNER JOIN users ru ON ru.id = pr.reviewer_id
                WHERE p.workflow_state = 'in_review'
                  {$scopeClause}
                ORDER BY pr.assigned_at DESC
                LIMIT ?";

        return $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Remove all reviewer assignments for a post.
     *
     * Used when re-opening a post to all reviewers (e.g. the assigned reviewer
     * lost reviewer capability — see the stale-assignment guard in Plan 2).
     *
     * @param  int  $postId  Post ID
     * @return bool True if at least one assignment was removed
     */
    public function clearByPost(int $postId): bool
    {
        $sql = 'DELETE FROM post_reviewers WHERE post_id = ?';

        return $this->database->execute($sql, [$postId]) > 0;
    }

    /**
     * Unassign a single reviewer from a post (targeted removal).
     *
     * Used by both reviewer self-release and editor/owner-driven reassignment.
     */
    public function unassign(int $postId, int $reviewerId): bool
    {
        $sql = 'DELETE FROM post_reviewers WHERE post_id = ? AND reviewer_id = ?';

        return $this->database->execute($sql, [$postId, $reviewerId]) > 0;
    }
}
