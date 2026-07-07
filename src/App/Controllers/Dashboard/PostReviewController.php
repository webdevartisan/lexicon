<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Resources\BlogResource;
use App\Resources\PostResource;
use App\Services\WorkflowService;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Editorial review pipeline for dashboard posts.
 *
 * Owns the review screen, the per-blog review queue, and every workflow
 * transition (request review, approve, needs changes, reset) plus reviewer
 * assignment. Post CRUD lives in PostController; state transitions and
 * their notifications are delegated to WorkflowService.
 */
final class PostReviewController extends AppController
{
    public function __construct(
        private PostModel $model,
        private BlogModel $blogModel,
        private WorkflowService $workflowService,
        private PostReviewerModel $postReviewerModel,
        private ReviewModel $reviewModel,
        private BlogSettingsModel $blogSettingsModel,
    ) {}

    /**
     * Show post review screen with workflow permissions.
     *
     * @param  string  $id  Post ID
     */
    public function review(string $id): Response
    {
        $user = auth()->user();
        $post = $this->getPost((int) $id);
        $blog = $post->blog();

        Gate::authorize('reviewPost', $post, $user);

        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $workflowState = $post->workflowState();
        $status = $post->status();
        $currentUserId = (int) $user['id'];

        // Lazy stale-reviewer guard: evaluated on page load, not at role-change time.
        $this->workflowService->checkStaleReviewer(
            postId: (int) $id,
            post: $post,
            ownerId: (int) $blog->ownerId()
        );

        $reviewers = $this->postReviewerModel->findByPost((int) $id);

        // Auto-claim / lock: first reviewer to open an unassigned pending post claims it.
        // Owners/editors don't auto-claim, they can supervise without locking the post.
        $blogSettings = $this->blogSettingsModel->findByBlogId((int) $blog->id());
        $workflowEnabled = !empty($blogSettings['workflow_enabled']);
        $isReviewerRole = $blogRole === 'reviewer';
        $postNeedsReview = $status === 'pending' || in_array($workflowState, ['in_review', 'needs_changes'], true);

        if ($workflowEnabled
            && $isReviewerRole
            && $postNeedsReview
            && empty($reviewers)
            && $currentUserId !== (int) $post->authorId()) {

            $this->workflowService->assignReviewer(
                postId: (int) $id,
                reviewerId: $currentUserId,
                assignedBy: $currentUserId,
                postAuthorId: (int) $post->authorId()
            );

            if ($workflowState !== 'in_review') {
                try {
                    $this->workflowService->submitForReview((int) $id, $currentUserId);
                    $workflowState = 'in_review';
                } catch (\RuntimeException $e) {
                    error_log('Auto submitForReview on claim failed: '.$e->getMessage());
                }
            }

            $reviewers = $this->postReviewerModel->findByPost((int) $id);
            $post = $this->getPost((int) $id); // re-fetch updated state
        }

        $reviews = $this->reviewModel->findByPost((int) $id);

        // Lock detection: is this post claimed by someone *other* than the current user?
        $currentReviewerIds = array_map('intval', array_column($reviewers, 'reviewer_id'));
        $isSelfAssigned = in_array($currentUserId, $currentReviewerIds, true);
        $lockedByOther = !empty($reviewers) && !$isSelfAssigned;
        $lockedByReviewer = $lockedByOther ? ($reviewers[0]['reviewer_username'] ?? 'another reviewer') : null;
        $lockedAt = $lockedByOther ? ($reviewers[0]['assigned_at'] ?? null) : null;

        // Reviewers see a read-only lock screen when someone else has claimed it.
        // Owners/editors can still take action (supervisory override).
        $reviewerLocked = $lockedByOther && $isReviewerRole;

        $canRequestReview = false; // Auto-triggered by status=pending now; no manual button.

        $canMarkNeedsChanges = !$reviewerLocked
            && in_array($blogRole, ['reviewer', 'editor', 'owner'], true)
            && in_array($workflowState, ['in_review', 'approved'], true);

        $canApprove = !$reviewerLocked
            && in_array($blogRole, ['reviewer', 'editor', 'owner'], true)
            && in_array($workflowState, ['in_review', 'needs_changes'], true);

        $canPublish = !$reviewerLocked
            && in_array($blogRole, ['editor', 'owner'], true)
            && in_array($workflowState, ['approved'], true);

        $canUnpublish = !$reviewerLocked
            && in_array($blogRole, ['editor', 'owner'], true)
            && $status === 'published';

        $canResetToDraft = !$reviewerLocked
            && in_array($blogRole, ['editor', 'owner'], true)
            && in_array($workflowState, ['approved'], true);

        // Self-assign button no longer needed: auto-claim handles it on page load.
        $canSelfAssign = false;
        $canAssignReviewer = !$reviewerLocked && in_array($blogRole, ['editor', 'owner'], true);

        // Dropdown candidates for editor/owner: reviewer-capable users not yet assigned, not the author
        $availableReviewers = [];
        if ($canAssignReviewer) {
            $blogUsers = $this->blogModel->getBlogUsers((int) $blog->id());
            foreach ($blogUsers as $blogUser) {
                if ((int) $blogUser['user_id'] !== $post->authorId()
                    && in_array($blogUser['role'], ['reviewer', 'editor', 'owner'], true)
                    && !in_array((int) $blogUser['user_id'], $currentReviewerIds, true)) {
                    $availableReviewers[] = $blogUser;
                }
            }
        }

        breadcrumbs()->set([
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            $blogRole === 'owner'
                ? ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs']
                : ['label' => 'Shared', 'url' => '/dashboard/shared', 'key' => 'breadcrumbs.shared'],
            ['label' => 'Review Queue', 'url' => '/dashboard/blog/'.$blog->id().'/review-queue', 'key' => 'breadcrumbs.reviewQueue'],
            ['label' => 'Review Post', 'url' => null, 'key' => 'breadcrumbs.reviewPost'],
        ], true);

        return $this->view('post.review', [
            'post' => $post->toArray(),
            'blog' => $blog->toArray(),
            'workflowState' => $workflowState,
            'status' => $status,
            'blogRole' => $blogRole,
            'isAdmin' => auth()->hasRole('administrator'),
            'currentUserId' => $currentUserId,
            'canRequestReview' => $canRequestReview,
            'canMarkNeedsChanges' => $canMarkNeedsChanges,
            'canApprove' => $canApprove,
            'canPublish' => $canPublish,
            'canUnpublish' => $canUnpublish,
            'canResetToDraft' => $canResetToDraft,
            'canAssignReviewer' => $canAssignReviewer,
            'canSelfAssign' => $canSelfAssign,
            'reviewerLocked' => $reviewerLocked,
            'lockedByReviewer' => $lockedByReviewer,
            'lockedAt' => $lockedAt,
            'reviewers' => $reviewers,
            'reviews' => $reviews,
            'availableReviewers' => $availableReviewers,
        ]);
    }

    /**
     * Per-blog review queue.
     *
     * Lists every post in workflow_state in_review or needs_changes for one
     * blog, with assignment info. Anyone with a reviewer-capable role on the
     * blog (reviewer, editor, owner) or site admin may see this.
     *
     * @param  string  $blogId  Target blog
     */
    public function reviewQueue(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);

        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $isAdmin = auth()->hasRole('administrator');
        if (!$isAdmin && !in_array($blogRole, ['reviewer', 'editor', 'owner'], true)) {
            throw new PageNotFoundException('Review queue not available.');
        }

        $posts = $this->model->findReviewQueueForBlog((int) $blog->id());

        // Trail roots where the user came from: owners browse their blogs,
        // collaborators arrive through Shared.
        breadcrumbs()->set([
            ['label' => 'Dashboard', 'url' => '/dashboard', 'key' => 'breadcrumbs.dashboard'],
            $blogRole === 'owner'
                ? ['label' => 'All Blogs', 'url' => '/dashboard/blog', 'key' => 'breadcrumbs.allBlogs']
                : ['label' => 'Shared', 'url' => '/dashboard/shared', 'key' => 'breadcrumbs.shared'],
            ['label' => 'Review Queue', 'url' => null, 'key' => 'breadcrumbs.reviewQueue'],
        ], true);

        return $this->view('post.reviewQueue', [
            'blog' => $blog->toArray(),
            'posts' => $posts,
            'blogRole' => $blogRole,
            'isAdmin' => $isAdmin,
            'currentUserId' => (int) $user['id'],
        ]);
    }

    /**
     * Request review (author/contributor → reviewer).
     *
     * @param  string  $id  Post ID
     */
    public function requestReview(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $this->workflowService->submitForReview((int) $id, $user['id']);

        audit()->log(
            $user['id'],
            'post.review_requested',
            'post',
            (int) $id,
            ['workflow_state' => 'in_review'],
            $this->request->ip()
        );

        $this->flash('success', 'Post submitted for review.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Review post decision.
     *
     * @param  string  $id  Post ID
     */
    public function reviewDecision(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $validator = $this->validateOrFail([
            'decision' => 'in:needs_changes,approved',
            'feedback' => 'max:300',
        ]);

        $data = $validator->validated();

        if ($data['decision'] === 'approved') {
            return $this->approve($id);
        } elseif ($data['decision'] === 'needs_changes') {
            return $this->markNeedsChanges($id);
        } else {
            $this->flash('error', 'Invalid review decision.');

            return $this->redirectBack();
        }
    }

    /**
     * Approve post (reviewer/editor).
     *
     * @param  string  $id  Post ID
     */
    public function approve(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('approve', $post, $user);

        $feedback = trim((string) ($this->request->postParam('feedback') ?? ''));
        $this->workflowService->approve((int) $id, $user['id'], $feedback);

        audit()->log(
            $user['id'],
            'post.approved',
            'post',
            (int) $id,
            ['workflow_state' => 'approved'],
            $this->request->ip()
        );

        $this->flash('success', 'Post approved.');

        return $this->redirect('/dashboard');
    }

    /**
     * Mark post as needing changes.
     *
     * @param  string  $id  Post ID
     */
    public function markNeedsChanges(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('markAsNeedsChanges', $post, $user);

        $feedback = trim((string) ($this->request->postParam('feedback') ?? ''));
        $this->workflowService->requestChanges((int) $id, $user['id'], $feedback);

        audit()->log(
            $user['id'],
            'post.marked_needs_changes',
            'post',
            (int) $id,
            ['workflow_state' => 'needs_changes'],
            $this->request->ip()
        );

        $this->flash('info', 'Post marked as needing changes.');

        return $this->redirect('/dashboard');
    }

    /**
     * Reset workflow to draft.
     *
     * @param  string  $id  Post ID
     */
    public function resetWorkflowToDraft(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('update', $post, $user);

        $this->workflowService->resetToDraft((int) $id, $user['id']);

        audit()->log(
            $user['id'],
            'post.workflow_reset',
            'post',
            (int) $id,
            ['workflow_state' => 'draft'],
            $this->request->ip()
        );

        $this->flash('success', 'Workflow reset to draft.');

        return $this->redirect("/dashboard/posts/{$id}/edit");
    }

    /**
     * Assign a reviewer to a post.
     *
     * Owner and editor can assign any reviewer. A reviewer may only self-assign.
     *
     * @param  string  $id  Post ID
     */
    public function assignReviewer(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('assignReviewer', $post, $user);

        $reviewerId = (int) ($this->request->postParam('reviewer_id') ?? 0);

        if ($reviewerId <= 0) {
            $this->flash('error', 'Please select a reviewer.');

            return $this->redirectBack();
        }

        $blog = $post->blog();
        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);

        // Reviewers may only self-assign
        if ($blogRole === 'reviewer' && $reviewerId !== (int) $user['id']) {
            $this->flash('error', 'Reviewers may only assign themselves.');

            return $this->redirectBack();
        }

        $this->workflowService->assignReviewer(
            postId: (int) $id,
            reviewerId: $reviewerId,
            assignedBy: (int) $user['id'],
            postAuthorId: (int) $post->authorId(),
        );

        audit()->log(
            $user['id'],
            'post.reviewer_assigned',
            'post',
            (int) $id,
            ['reviewer_id' => $reviewerId],
            $this->request->ip()
        );

        $this->flash('success', 'Reviewer assigned.');

        return $this->redirectBack();
    }

    /**
     * Unassign a reviewer from a post.
     *
     * Editor/owner may unassign anyone. A reviewer may release themselves,
     * same authority axis as assignReviewer's self-assign rule, in reverse.
     *
     * @param  string  $id  Post ID
     */
    public function unassignReviewer(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        $reviewerId = (int) ($this->request->postParam('reviewer_id') ?? 0);
        if ($reviewerId <= 0) {
            $this->flash('error', 'Missing reviewer.');

            return $this->redirectBack();
        }

        $blog = $post->blog();
        $blogRole = $blog->effectiveRoleForUser((int) $user['id']);
        $isAdmin = auth()->hasRole('administrator');
        $isSelfRelease = $reviewerId === (int) $user['id'];

        $canUnassignOthers = $isAdmin || in_array($blogRole, ['owner', 'editor'], true);
        if (!$canUnassignOthers && !$isSelfRelease) {
            $this->flash('error', 'Only the assigned reviewer, an editor, or the owner can unassign.');

            return $this->redirectBack();
        }

        if (!$this->postReviewerModel->unassign((int) $id, $reviewerId)) {
            $this->flash('info', 'That reviewer was already unassigned.');

            return $this->redirectBack();
        }

        audit()->log(
            $user['id'],
            'post.reviewer_unassigned',
            'post',
            (int) $id,
            ['reviewer_id' => $reviewerId, 'self_release' => $isSelfRelease],
            $this->request->ip()
        );

        $this->flash('success', $isSelfRelease ? 'You released this post back to the queue.' : 'Reviewer unassigned.');

        return $this->redirectBack();
    }

    /**
     * Get post resource or throw 404.
     *
     * @param  int  $id  Post ID
     *
     * @throws PageNotFoundException
     */
    private function getPost(int $id): PostResource
    {
        $post = $this->model->findResource($id);

        if ($post === false) {
            throw new PageNotFoundException("Post with ID: '$id' not found.");
        }

        return $post;
    }

    /**
     * Get blog resource or throw 404.
     *
     * @param  int  $id  Blog ID
     *
     * @throws PageNotFoundException
     */
    private function getBlog(int $id): BlogResource
    {
        $blog = $this->blogModel->getBlog($id);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '$id' not found.");
        }

        return $blog;
    }
}
