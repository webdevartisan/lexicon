<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommentModel;

/**
 * The one way a comment leaves a thread, whoever asked for it.
 *
 * Authors remove their own words, blog moderators remove anyone's; both land
 * here so a comment can never disappear through a path that skips the thread
 * repair below. The rule is the one Reddit and Disqus settled on:
 *
 * - a comment nothing replies to is deleted outright, because "delete" should
 *   mean gone when nothing depends on it;
 * - a comment with replies becomes a tombstone, for two reasons: deleting it
 *   for real cascades down parent_comment_id and takes conversations other
 *   people wrote with it, and a reply held at the depth cap is a sibling
 *   rather than a child, so it survives that cascade still naming the deleted
 *   row in reply_to_comment_id and reads as an answer to nobody;
 * - a tombstone whose last reply later goes away is collected, so threads do
 *   not accumulate "[removed]" markers with nothing under them.
 */
class CommentRemovalService
{
    public const BY_AUTHOR = 'author';

    public const BY_MODERATOR = 'moderator';

    public function __construct(
        private CommentModel $comments,
    ) {}

    /**
     * Whether this viewer may remove this comment.
     *
     * Moderator authority is resolved by the caller and passed in rather than
     * looked up here: a rendered thread asks this question once per comment,
     * and the answer is the same blog-level Gate check every time.
     *
     * @param  array<string, mixed>  $comment  Comment row
     * @param  int|null  $viewerId  Authenticated viewer id, null for guests
     * @param  bool  $viewerModerates  Whether the viewer moderates the owning blog
     */
    public function canRemove(array $comment, ?int $viewerId, bool $viewerModerates): bool
    {
        if ($viewerId === null || !empty($comment['deleted_at'])) {
            return false;
        }

        if (!empty($comment['user_id']) && (int) $comment['user_id'] === $viewerId) {
            return true;
        }

        return $viewerModerates;
    }

    /**
     * Remove a comment, repairing the thread around it.
     *
     * @param  int  $commentId  Comment to remove
     * @param  string  $by  self::BY_AUTHOR or self::BY_MODERATOR
     * @return bool True when the row was hard deleted, false when it became a tombstone
     */
    public function remove(int $commentId, string $by): bool
    {
        $comment = $this->comments->findById($commentId);

        if ($comment === null) {
            return false;
        }

        if ($this->comments->hasReplies($commentId)) {
            $this->comments->softDelete($commentId, $by);

            return false;
        }

        $this->comments->deleteById($commentId);
        $this->collectAbove($comment);

        return true;
    }

    /**
     * Collect any tombstone the comment just removed was holding up.
     *
     * Two comments can be waiting on one reply: the parent it hung under, and
     * the one it actually answered where the depth cap made those differ. Both
     * are checked, and the walk continues upward, so a chain of tombstones
     * kept alive for a single thread goes when that thread does.
     *
     * @param  array<string, mixed>  $comment  The row as it was before deletion
     */
    private function collectAbove(array $comment): void
    {
        $above = array_unique(array_filter([
            (int) ($comment['parent_comment_id'] ?? 0),
            (int) ($comment['reply_to_comment_id'] ?? 0),
        ]));

        foreach ($above as $id) {
            $this->collectEmptyTombstone($id);
        }
    }

    /**
     * Delete a tombstone that has outlived every reply it was preserving.
     */
    private function collectEmptyTombstone(int $commentId): void
    {
        $parent = $this->comments->findById($commentId);

        if ($parent === null || empty($parent['deleted_at'])) {
            return;
        }

        if ($this->comments->hasReplies($commentId)) {
            return;
        }

        $this->comments->deleteById($commentId);
        $this->collectAbove($parent);
    }
}
