<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Models\UserModel;
use App\Policies\PostPolicy;
use App\Resources\PostResource;
use App\Services\NotificationService;

/**
 * WorkflowService owns all editorial pipeline state transitions.
 *
 * PostPolicy enforces WHO can act; WorkflowService enforces HOW state moves.
 * PostController calls both — authorize first, then delegate here.
 */
class WorkflowService
{
    public function __construct(
        private PostModel $post,
        private PostReviewerModel $postReviewer,
        private ReviewModel $review,
        private UserModel $users,
        private NotificationService $notifications,
    ) {}

    /**
     * Resolve a username from a user id, returning '' when the user is missing.
     */
    private function username(int $userId): string
    {
        $row = $this->users->findById($userId);

        return (string) ($row['username'] ?? '');
    }

    /**
     * Submit a post for review (draft|needs_changes → in_review).
     *
     * Notifies assigned reviewers, or a general queue notification if none assigned.
     *
     * @throws \RuntimeException If the state transition fails
     */
    public function submitForReview(int $postId, int $userId): void
    {
        $ok = $this->post->transitionWorkflow($postId, 'in_review', $userId);

        if (!$ok) {
            throw new \RuntimeException("Failed to transition post {$postId} to in_review.");
        }

        $assignments = $this->postReviewer->findByPost($postId);
        $resource    = $this->post->findResource($postId);
        $postTitle   = $resource ? $resource->title() : '';
        $authorName  = $this->username($userId);

        if (empty($assignments)) {
            $this->notifications->dispatch($userId, 'post.submitted_unassigned', [
                'post_id'         => $postId,
                'post_title'      => $postTitle,
                'author_username' => $authorName,
            ]);
            return;
        }

        foreach ($assignments as $a) {
            $this->notifications->dispatch((int) $a['reviewer_id'], 'post.submitted', [
                'post_id'         => $postId,
                'post_title'      => $postTitle,
                'author_username' => $authorName,
            ]);
        }
    }

    /**
     * Approve a post (in_review → approved).
     *
     * Records the review decision and notifies the post author.
     *
     * @throws \RuntimeException If the state transition fails
     */
    public function approve(int $postId, int $reviewerId, string $feedback = ''): void
    {
        $ok = $this->post->transitionWorkflow($postId, 'approved', $reviewerId);

        if (!$ok) {
            throw new \RuntimeException("Failed to transition post {$postId} to approved.");
        }

        $post = $this->post->findResource($postId);
        $this->review->create($postId, $reviewerId, 'approved', $feedback);

        if ($post) {
            $this->notifications->dispatch((int) $post->authorId(), 'post.approved', [
                'post_id'           => $postId,
                'post_title'        => $post->title(),
                'reviewer_username' => $this->username($reviewerId),
            ]);
        }
    }

    /**
     * Send a post back for revision (in_review → needs_changes).
     *
     * Records the review decision (ReviewModel ENUM: needs_revision) and notifies the author.
     *
     * @throws \RuntimeException If the state transition fails
     */
    public function requestChanges(int $postId, int $reviewerId, string $feedback): void
    {
        $ok = $this->post->transitionWorkflow($postId, 'needs_changes', $reviewerId);

        if (!$ok) {
            throw new \RuntimeException("Failed to transition post {$postId} to needs_changes.");
        }

        $post = $this->post->findResource($postId);
        // ReviewModel ENUM uses 'needs_revision'; workflow_state uses 'needs_changes'
        $this->review->create($postId, $reviewerId, 'needs_revision', $feedback);

        if ($post) {
            $this->notifications->dispatch((int) $post->authorId(), 'post.needs_changes', [
                'post_id'           => $postId,
                'post_title'        => $post->title(),
                'reviewer_username' => $this->username($reviewerId),
                'feedback'          => $feedback,
            ]);
        }
    }

    /**
     * Reset a post to draft state (any → draft).
     */
    public function resetToDraft(int $postId, int $actorId): void
    {
        $this->post->transitionWorkflow($postId, 'draft', $actorId);
    }

    /**
     * Assign a reviewer to a post.
     *
     * Clears any existing assignment first (v1: single reviewer per post).
     * Notifies the assigned reviewer.
     *
     * @param  int  $postAuthorId  Included in notification payload for context
     */
    public function assignReviewer(int $postId, int $reviewerId, int $assignedBy, int $postAuthorId): void
    {
        // v1: single-reviewer constraint — clear before re-assigning
        $this->postReviewer->clearByPost($postId);
        $this->postReviewer->assign($postId, $reviewerId, $assignedBy);

        $resource = $this->post->findResource($postId);
        $this->notifications->dispatch($reviewerId, 'post.reviewer_assigned', [
            'post_id'              => $postId,
            'post_title'           => $resource ? $resource->title() : '',
            'assigned_by_username' => $this->username($assignedBy),
        ]);
    }

    /**
     * Lazy stale-reviewer guard.
     *
     * Called when the review page is loaded, not at role-change time.
     * If the assigned reviewer can no longer reviewPost(), clears the assignment
     * and reopens the post to all reviewers, then notifies the owner.
     */
    public function checkStaleReviewer(int $postId, PostResource $post, int $ownerId): void
    {
        $assignments = $this->postReviewer->findByPost($postId);

        if (empty($assignments)) {
            return;
        }

        $policy = new PostPolicy();
        $blog = $post->blog();

        foreach ($assignments as $assignment) {
            $reviewerId = (int) $assignment['reviewer_id'];

            if (!$policy->reviewPost(['id' => $reviewerId], $post)) {
                $this->postReviewer->clearByPost($postId);

                $this->notifications->dispatch($ownerId, 'post.reviewer_stale', [
                    'post_id'                  => $postId,
                    'post_title'               => $post->title(),
                    'former_reviewer_username' => (string) ($assignment['reviewer_username'] ?? ''),
                ]);

                // Only process first assignment (v1: single reviewer)
                break;
            }
        }
    }

    /**
     * Disable the workflow pipeline for a blog.
     *
     * Finds all posts currently in_review or needs_changes and resets them
     * to draft. Notifies each affected author. Called when the owner toggles
     * workflow_enabled from true → false in blog settings.
     */
    public function disableWorkflow(int $blogId, int $actorId): void
    {
        $inFlight = $this->post->findInFlightByBlogId($blogId);

        foreach ($inFlight as $row) {
            $this->post->transitionWorkflow((int) $row['id'], 'draft', $actorId);

            $postRes = $this->post->findResource((int) $row['id']);
            $blog = $postRes ? $postRes->blog() : null;

            $this->notifications->dispatch((int) $row['author_id'], 'post.workflow_disabled', [
                'post_id'    => (int) $row['id'],
                'post_title' => $postRes ? $postRes->title() : '',
                'blog_name'  => $blog ? $blog->name() : '',
            ]);
        }
    }
}
