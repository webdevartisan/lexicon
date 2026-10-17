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
use App\Resources\BlogResource;
use App\Resources\PostResource;
use Framework\Security\ClientIp;
use RuntimeException;

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
    /** Review states a post must be in for each decision to apply. */
    private const DECISION_FROM = [
        'approved' => ['in_review'],
        'needs_changes' => ['in_review', 'approved'],
    ];

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
     * Resolve the handle of the user who triggered an event.
     *
     * @throws RuntimeException If the user is gone, since every payload built
     *                          here names a person the recipient will see
     */
    private function userHandle(int $userId): string
    {
        $row = $this->users->findById($userId);

        if ($row === null) {
            throw new RuntimeException("Cannot name the actor of a workflow event: user {$userId} not found.");
        }

        return (string) $row['handle'];
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
     * Submit a post for review (draft|needs_changes → in_review, status pending).
     *
     * Notifies assigned reviewers, or a general queue notification if none assigned.
     *
     * @throws \RuntimeException If the post is not a draft or awaiting changes
     */
    public function submitForReview(int $postId, int $userId): void
    {
        if (!$this->post->enterReview($postId, $userId)) {
            throw new \RuntimeException("Post {$postId} is not a draft or awaiting changes, so it cannot be submitted.");
        }

        $this->auditTransition($postId, 'in_review', $userId);

        // Notification fan-out must never undo a successful transition.
        try {
            $resource = $this->post->findResource($postId);
            if ($resource === false) {
                return;
            }

            $payload = $this->postPayload($resource);
            $payload['author_handle'] = $this->userHandle($userId);

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
            $recipients = $this->blogs->getActiveUsersWithPermission((int) $resource->blog()->id(), 'review_posts');
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
     * Submit the post if this save asked for review and the blog runs the pipeline.
     *
     * Every save path (new post, edit, bulk) goes through here, so a post can
     * no longer sit as pending without ever reaching the review queue.
     *
     * @param  int  $postId  Post just saved
     * @param  int  $blogId  Blog the post belongs to
     * @param  string  $savedStatus  Status the save left the post in
     * @param  string  $priorState  Review state before the save
     * @param  int  $userId  User who saved it
     * @return bool True when the post entered review
     */
    public function submitIfRequested(int $postId, int $blogId, string $savedStatus, string $priorState, int $userId): bool
    {
        if ($savedStatus !== 'pending' || !in_array($priorState, ['draft', 'needs_changes'], true)) {
            return false;
        }

        $settings = $this->blogSettings->findByBlogId($blogId);
        if (empty($settings['workflow_enabled'])) {
            return false;
        }

        try {
            $this->submitForReview($postId, $userId);

            return true;
        } catch (\RuntimeException $e) {
            error_log('Submit for review failed for post '.$postId.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Record the approval when someone publishes or schedules a post straight out of review.
     *
     * A publisher may skip the separate Approve step; the review history should
     * still show who let the post through. Call it with the post as it was
     * before the status change.
     *
     * @param  PostResource  $before  Post before the save
     * @param  string  $newStatus  Status the post is moving to
     * @param  int  $userId  User publishing it
     */
    public function concludeReview(PostResource $before, string $newStatus, int $userId): void
    {
        $leavesReview = $before->status() === 'pending'
            && in_array($newStatus, ['published', 'scheduled'], true)
            && in_array($before->workflowState(), ['in_review', 'needs_changes'], true);

        if (!$leavesReview) {
            return;
        }

        $postId = (int) $before->id();
        $this->auditTransition($postId, 'approved', $userId);
        $this->review->create($postId, $userId, 'approved', '');
        $this->notifyReviewDecision($before, 'post.approved', $userId, '');
    }

    /**
     * Approve a post (in_review → approved).
     *
     * Records the review decision and notifies the post author.
     *
     * @throws \RuntimeException If the post is no longer in review
     */
    public function approve(int $postId, int $reviewerId, string $feedback = ''): void
    {
        $ok = $this->post->transitionWorkflow($postId, 'approved', $reviewerId, self::DECISION_FROM['approved']);

        if (!$ok) {
            throw new \RuntimeException("Post {$postId} is no longer in review, so it cannot be approved.");
        }

        $this->auditTransition($postId, 'approved', $reviewerId);

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
     * @throws \RuntimeException If the post is no longer in review or approved
     */
    public function requestChanges(int $postId, int $reviewerId, string $feedback): void
    {
        $ok = $this->post->transitionWorkflow($postId, 'needs_changes', $reviewerId, self::DECISION_FROM['needs_changes']);

        if (!$ok) {
            throw new \RuntimeException("Post {$postId} is no longer in review, so changes cannot be requested.");
        }

        $this->auditTransition($postId, 'needs_changes', $reviewerId);

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
            $payload['reviewer_handle'] = $this->userHandle($reviewerId);
            $payload['feedback'] = $feedback;

            $this->notifications->dispatch($authorId, $type, $payload);
        } catch (\Throwable $e) {
            error_log('Notify on review decision failed: '.$e->getMessage());
        }
    }

    /**
     * Take a pending post out of review and back to draft, by its author or an editor.
     */
    public function resetToDraft(int $postId, int $actorId): void
    {
        if ($this->post->leaveReview($postId, $actorId)) {
            $this->auditTransition($postId, 'draft', $actorId);
        }
    }

    /**
     * Record a workflow transition in the audit log.
     *
     * Lives here rather than in PostModel: the model is also driven from CLI
     * and tests, where an audit row carrying HTTP context is misleading.
     */
    private function auditTransition(int $postId, string $newState, int $actorId): void
    {
        audit()->log(
            $actorId,
            'transition_workflow',
            'post',
            $postId,
            ["Changed workflow_state to {$newState}"],
            ClientIp::fromEnvironment($_SERVER)
        );
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
            'actor_handle' => $this->userHandle($assignedBy),
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
                    'former_reviewer_handle' => (string) $assignment['reviewer_handle'],
                ]);

                break;
            }
        }
    }

    /**
     * Disable the workflow pipeline for a blog.
     *
     * Returns every pending post to draft, since without the pipeline nobody
     * would ever pick them up. Notifies each affected author. Called when the
     * owner toggles workflow_enabled from true → false in blog settings.
     */
    public function disableWorkflow(int $blogId, int $actorId): void
    {
        $inFlight = $this->post->findInFlightByBlogId($blogId);

        foreach ($inFlight as $row) {
            if ($this->post->leaveReview((int) $row['id'], $actorId)) {
                $this->auditTransition((int) $row['id'], 'draft', $actorId);
            }

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
     * Whether a user may publish (or schedule, or archive) posts on a blog.
     *
     * The rule lives on BlogResource so PostPolicy can apply it too.
     */
    public function canPublish(BlogResource $blog, int $userId): bool
    {
        return $blog->canPublish($userId);
    }

    /**
     * Clamp a requested post status to what the user may set.
     *
     * Scheduling counts as publishing: it is publishing with a delay, so it
     * needs identical authority or it becomes a way to sidestep review.
     * Without that authority the ceiling is handing a draft to the pipeline.
     *
     * @param  string  $requested  Status coming from the form
     * @param  bool  $canPublish  Result of canPublish() for the acting user
     * @param  int  $blogId  Blog the post belongs to
     * @param  string|null  $current  Current status when updating, null on create
     * @return string The status the user is actually allowed to persist
     */
    public function constrainStatus(string $requested, bool $canPublish, int $blogId, ?string $current = null): string
    {
        if ($canPublish) {
            return $requested;
        }

        $settings = $this->blogSettings->findByBlogId($blogId);
        $workflowOn = !empty($settings['workflow_enabled']);

        // No change to a state the post already holds. an editor put it
        // there, saving content edits shouldn't undo that decision.
        if ($requested === $current) {
            return $requested;
        }

        if (in_array($requested, ['published', 'scheduled', 'archived'], true)) {
            return $workflowOn ? 'pending' : 'draft';
        }

        // The reverse move is an unpublish; same authority as publishing.
        if (in_array($current, ['published', 'scheduled', 'archived'], true)) {
            return $current;
        }

        return $requested;
    }
}
