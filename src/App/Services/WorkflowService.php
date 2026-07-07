<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Models\UserModel;
use App\Policies\PostPolicy;
use App\Resources\PostResource;

/**
 * WorkflowService owns all editorial pipeline state transitions.
 *
 * PostPolicy defines WHO is allowed to act, and WorkflowService defines HOW a post moves through its states.
 * Controllers use both: they authorize first, then hand things off here.
 * Notifications for state changes are also sent from this layer, so every entry point, including manual actions,
 * automatic status change submissions, and bulk operations, triggers the same consistent notification flow.
 */
class WorkflowService
{
    public function __construct(
        private PostModel $post,
        private PostReviewerModel $postReviewer,
        private ReviewModel $review,
        private UserModel $users,
        private NotificationService $notifications,
        private BlogModel $blogs,
        private BlogSettingsModel $blogSettings,
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
     * Common notification payload: enough post + blog context for the
     * notification item to render a title and build links.
     *
     * @return array<string, mixed>
     */
    private function postPayload(PostResource $post): array
    {
        $blog = $post->blog();

        return [
            'post_id' => $post->id(),
            'post_title' => $post->title(),
            'post_slug' => $post->slug(),
            'blog_id' => $blog->id(),
            'blog_name' => $blog->name(),
            'blog_slug' => $blog->slug(),
        ];
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

        // Notification fan-out must never undo a successful transition.
        try {
            $resource = $this->post->findResource($postId);
            if ($resource === false) {
                return;
            }

            $payload = $this->postPayload($resource);
            $payload['author_username'] = $this->username($userId);

            $assignments = $this->postReviewer->findByPost($postId);
            if (!empty($assignments)) {
                foreach ($assignments as $a) {
                    $rid = (int) ($a['reviewer_id'] ?? 0);
                    if ($rid > 0) {
                        $this->notifications->dispatch($rid, 'post.submitted', $payload);
                    }
                }

                return;
            }

            // No reviewer has claimed this yet, so notify everyone who is eligible to pick it up.
            $recipients = $this->blogs->getActiveUsersWithRoles(
                (int) $resource->blog()->id(),
                ['owner', 'editor', 'reviewer']
            );
            $authorId = $resource->authorId();
            foreach ($recipients as $r) {
                $uid = (int) $r['user_id'];
                if ($uid === $authorId) {
                    continue; // don't notify the author about their own submission
                }
                $this->notifications->dispatch($uid, 'post.submitted_unassigned', $payload);
            }
        } catch (\Throwable $e) {
            error_log('Notify on submit-for-review failed: '.$e->getMessage());
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
            $this->notifyReviewDecision($post, 'post.approved', $reviewerId, $feedback);
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
            $this->notifyReviewDecision($post, 'post.needs_changes', $reviewerId, $feedback);
        }
    }

    /**
     * Notify the author after a reviewer makes a decision, whether it is an approval or a
     * request for changes. Any failures are logged quietly and never interrupt the workflow,
     * because the state transition has already succeeded.
     */
    private function notifyReviewDecision(PostResource $post, string $type, int $reviewerId, string $feedback): void
    {
        try {
            $authorId = (int) $post->authorId();
            if ($authorId <= 0) {
                return;
            }

            $payload = $this->postPayload($post);
            $payload['reviewer_username'] = $this->username($reviewerId);
            $payload['feedback'] = $feedback;

            $this->notifications->dispatch($authorId, $type, $payload);
        } catch (\Throwable $e) {
            error_log('Notify on review decision failed: '.$e->getMessage());
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
     * Clears any existing assignment first, ensuring only one reviewer is attached.
     * Sends a notification to the newly assigned reviewer.
     *
     * @param  int  $postAuthorId  Included in the notification payload for context
     */
    public function assignReviewer(int $postId, int $reviewerId, int $assignedBy, int $postAuthorId): void
    {
        $this->postReviewer->clearByPost($postId);
        $this->postReviewer->assign($postId, $reviewerId, $assignedBy);

        $resource = $this->post->findResource($postId);
        $this->notifications->dispatch($reviewerId, 'post.reviewer_assigned', [
            'post_id' => $postId,
            'post_title' => $resource ? $resource->title() : '',
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
                    'post_id' => $postId,
                    'post_title' => $post->title(),
                    'former_reviewer_username' => (string) ($assignment['reviewer_username'] ?? ''),
                ]);

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
                'post_id' => (int) $row['id'],
                'post_title' => $postRes ? $postRes->title() : '',
                'blog_name' => $blog ? $blog->name() : '',
            ]);
        }
    }

    /**
     * Clamp a requested post status to what the user's blog role may set.
     *
     * Publishing (and archiving, which is the same authority) belongs to
     * owners and editors. Authors keep it only while the blog runs without
     * the review pipeline, publish_own is meaningless once every post has
     * to pass review. Contributors never publish; their ceiling is handing
     * a draft to the pipeline.
     *
     * @param  string  $requested  Status coming from the form
     * @param  string|null  $role  Effective blog role of the acting user
     * @param  int  $blogId  Blog the post belongs to
     * @param  string|null  $current  Current status when updating, null on create
     * @return string The status the role is actually allowed to persist
     */
    public function constrainStatusForRole(string $requested, ?string $role, int $blogId, ?string $current = null): string
    {
        if (in_array($role, ['owner', 'editor'], true)) {
            return $requested;
        }

        $settings = $this->blogSettings->findByBlogId($blogId);
        $workflowOn = !empty($settings['workflow_enabled']);

        $canPublish = $role === 'author' && !$workflowOn;
        if ($canPublish) {
            return $requested;
        }

        // No change to a state the post already holds. an editor put it
        // there, saving content edits shouldn't undo that decision.
        if ($requested === $current) {
            return $requested;
        }

        if (in_array($requested, ['published', 'archived'], true)) {
            return $workflowOn ? 'pending' : 'draft';
        }

        // The reverse move is an unpublish; same authority as publishing.
        if (in_array($current, ['published', 'archived'], true)) {
            return $current;
        }

        return $requested;
    }
}
