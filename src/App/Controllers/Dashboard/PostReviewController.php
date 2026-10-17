<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\PostListingModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Resources\BlogResource;
use App\Resources\PostResource;
use App\Services\BlogRoleLabels;
use App\Services\SubscriberNotificationService;
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
        private BlogRoleLabels $roleLabels,
        private PostListingModel $postListing,
        private SubscriberNotificationService $subscriberNotifier,
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

        $currentUserId = (int) $user['id'];

        // Lazy stale-reviewer guard: evaluated on page load, not at role-change time.
        $this->workflowService->checkStaleReviewer(
            postId: (int) $id,
            post: $post,
            ownerId: (int) $blog->ownerId()
        );

        $reviewers = $this->postReviewerModel->findByPost((int) $id);
        $actions = $this->reviewActions($post, $currentUserId, $reviewers);

        breadcrumbs()->set([
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            Gate::allows('reviewPosts', $blog, auth()->user())
                ? ['label' => 'Review Queue', 'url' => '/dashboard/blog/'.$blog->id().'/review-queue', 'key' => 'breadcrumbs.reviewQueue']
                : ['label' => 'Posts', 'url' => '/dashboard/post', 'key' => 'breadcrumbs.posts'],
            ['label' => 'Review Post', 'url' => null, 'key' => 'breadcrumbs.reviewPost'],
        ], true);

        return $this->view('post.review', [
            'post' => $post->toArray(),
            'blog' => $blog->toArray(),
            'workflowState' => $post->workflowState(),
            'status' => $post->status(),
            'roleLabel' => $this->roleLabelFor($blog, $currentUserId),
            'canManageReviewers' => $this->managesReviewers($blog, $currentUserId) || auth()->hasRole('administrator'),
            'currentUserId' => $currentUserId,
            'reviewers' => $reviewers,
            'reviews' => $this->reviewModel->findByPost((int) $id),
            'availableReviewers' => $actions['canAssignReviewer'] ? $this->reviewerCandidates($post, $reviewers) : [],
            ...$actions,
        ]);
    }

    /**
     * What the viewer may do on the review screen right now.
     *
     * Someone with only review rights starts the review before deciding, which
     * locks the post for its writer. Whoever manages every post may decide
     * straight away. Someone who can publish publishes in one step; Approve is
     * for those who cannot.
     *
     * @param  array<int, array<string, mixed>>  $reviewers  Current assignments
     * @return array<string, mixed>
     */
    private function reviewActions(PostResource $post, int $userId, array $reviewers): array
    {
        $blog = $post->blog();
        $state = $post->workflowState();
        $pending = $post->status() === 'pending';
        $managesPosts = $blog->userCan($userId, 'edit_blog_posts');
        $canPublish = $blog->canPublish($userId);

        $holderIds = array_map('intval', array_column($reviewers, 'reviewer_id'));
        $isReviewing = in_array($userId, $holderIds, true);
        $lockedByOther = $reviewers !== [] && !$isReviewing;
        $mayDecide = $pending && ($isReviewing || $managesPosts);

        return [
            'isReviewing' => $isReviewing,
            'reviewerLocked' => $lockedByOther && !$managesPosts,
            'lockedByReviewer' => $lockedByOther ? (string) $reviewers[0]['reviewer_handle'] : null,
            'lockedAt' => $lockedByOther ? ($reviewers[0]['assigned_at'] ?? null) : null,
            'canStartReview' => $pending && $state === 'in_review' && $reviewers === []
                && $blog->userCan($userId, 'review_posts') && $post->authorId() !== $userId,
            'canTakeOver' => $pending && $state === 'in_review' && $lockedByOther && $this->managesReviewers($blog, $userId),
            'canMarkNeedsChanges' => $mayDecide && $blog->userCan($userId, 'reject_posts') && in_array($state, ['in_review', 'approved'], true),
            'canApprove' => $mayDecide && !$canPublish && $blog->userCan($userId, 'approve_posts') && $state === 'in_review',
            'publishLabel' => $mayDecide && $canPublish ? $this->publishLabel($post) : null,
            'canUnpublish' => $blog->userCan($userId, 'publish_blog_posts') && $post->status() === 'published',
            'canResetToDraft' => $pending && $managesPosts,
            'canAssignReviewer' => !($lockedByOther && !$managesPosts) && $this->managesReviewers($blog, $userId),
        ];
    }

    /**
     * The one-click publish button's wording: it schedules when the post carries a future date.
     */
    private function publishLabel(PostResource $post): string
    {
        $date = $post->publishedAt();
        if ($date !== null && strtotime($date.' UTC') > time()) {
            return 'Schedule for '.local_datetime($date, 'M j, g:ia');
        }

        return $post->workflowState() === 'approved' ? 'Publish' : 'Approve and publish';
    }

    /**
     * Anyone who can review on the blog, not already assigned, and not the writer.
     *
     * @param  array<int, array<string, mixed>>  $reviewers  Current assignments
     * @return array<int, array<string, mixed>>
     */
    private function reviewerCandidates(PostResource $post, array $reviewers): array
    {
        $assigned = array_map('intval', array_column($reviewers, 'reviewer_id'));

        return array_values(array_filter(
            $this->blogModel->getActiveUsersWithPermission($post->blogId(), 'review_posts'),
            static fn (array $c): bool => (int) $c['user_id'] !== $post->authorId()
                && !in_array((int) $c['user_id'], $assigned, true)
        ));
    }

    /**
     * Per-blog review queue.
     *
     * Lists every post in workflow_state in_review or needs_changes for one
     * blog, with assignment info. Gated by review_posts, so owners, editors,
     * reviewers and any custom role holding it; administrators get no bypass.
     *
     * @param  string  $blogId  Target blog
     */
    public function reviewQueue(string $blogId): Response
    {
        $user = auth()->user();
        $blog = $this->getBlog((int) $blogId);

        Gate::authorize('reviewPosts', $blog, $user);
        $posts = $this->postListing->findReviewQueueForBlog((int) $blog->id());

        breadcrumbs()->set([
            ['label' => 'Overview', 'url' => '/dashboard', 'key' => 'breadcrumbs.overview'],
            ['label' => 'Review Queue', 'url' => null, 'key' => 'breadcrumbs.reviewQueue'],
        ], true);

        return $this->view('post.reviewQueue', [
            'blog' => $blog->toArray(),
            'posts' => $posts,
            'canClaim' => $blog->userCan((int) $user['id'], 'assign_reviewers'),
            'canManageReviewers' => $this->managesReviewers($blog, (int) $user['id']) || auth()->hasRole('administrator'),
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

        try {
            $this->workflowService->submitForReview((int) $id, (int) $user['id']);
        } catch (\RuntimeException) {
            return $this->movedOn();
        }

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
            'feedback' => 'max:5000',
        ]);

        $data = $validator->validated();

        if ($data['decision'] === 'needs_changes' && trim((string) ($data['feedback'] ?? '')) === '') {
            return $this->rejectField('feedback', 'Say what needs changing, so the writer knows what to do.');
        }

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

        try {
            $this->workflowService->approve((int) $id, (int) $user['id'], $feedback);
        } catch (\RuntimeException) {
            return $this->movedOn();
        }

        audit()->log(
            $user['id'],
            'post.approved',
            'post',
            (int) $id,
            ['workflow_state' => 'approved'],
            $this->request->ip()
        );

        $this->flash('success', 'Approved. Someone who can publish will take it from here.');

        return $this->redirect($this->reviewUrl((int) $id));
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

        try {
            $this->workflowService->requestChanges((int) $id, (int) $user['id'], $feedback);
        } catch (\RuntimeException) {
            return $this->movedOn();
        }

        audit()->log(
            $user['id'],
            'post.marked_needs_changes',
            'post',
            (int) $id,
            ['workflow_state' => 'needs_changes'],
            $this->request->ip()
        );

        $this->flash('success', 'Sent back to the writer with your notes.');

        return $this->redirect($this->reviewUrl((int) $id));
    }

    /**
     * Take on a submission for review, or take it over from another reviewer.
     *
     * @param  string  $id  Post ID
     */
    public function startReview(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('reviewPost', $post, $user);

        $takeOver = $this->request->postParam('take_over') === '1' && $this->managesReviewers($post->blog(), (int) $user['id']);

        if ($post->authorId() === (int) $user['id']
            || !$this->workflowService->startReview($post, (int) $user['id'], $takeOver)) {
            return $this->movedOn();
        }

        audit()->log($user['id'], 'post.review_started', 'post', (int) $id, ['take_over' => $takeOver], $this->request->ip());
        $this->flash('success', 'You are reviewing this post. Its writer can no longer change it until you decide.');

        return $this->redirect($this->reviewUrl((int) $id));
    }

    /**
     * Publish a submission, or schedule it when it carries a future date, in one step.
     *
     * @param  string  $id  Post ID
     */
    public function publish(string $id): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        if ($post->status() !== 'pending' || !$post->blog()->canPublish((int) $user['id'])) {
            return $this->movedOn();
        }

        $status = $this->workflowService->publish($post, (int) $user['id']);
        if ($status === 'published') {
            $this->subscriberNotifier->notifyPostPublished((int) $id);
        }

        audit()->log($user['id'], 'post.'.$status, 'post', (int) $id, ['from' => 'review'], $this->request->ip());
        $this->flash('success', $status === 'published' ? 'Published.' : 'Scheduled. It goes live on its date.');

        return $this->redirect($this->reviewUrl((int) $id));
    }

    /**
     * Take a pending post back to draft: the writer withdrawing it, or an editor returning it.
     *
     * @param  string  $id  Post ID
     */
    public function resetWorkflowToDraft(string $id): Response
    {
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $user = auth()->user();
        $post = $this->getPost((int) $id);

        Gate::authorize('withdraw', $post, $user);

        $this->workflowService->resetToDraft((int) $id, $user['id']);

        audit()->log(
            $user['id'],
            'post.workflow_reset',
            'post',
            (int) $id,
            ['workflow_state' => 'draft'],
            $this->request->ip()
        );

        $this->flash('success', 'Back in drafts. It left the review queue, so submit it again when it is ready.');

        return $this->redirect("/dashboard/post/{$id}/edit");
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

        // Reviewers may only self-assign
        if (!$this->managesReviewers($blog, (int) $user['id']) && $reviewerId !== (int) $user['id']) {
            $this->flash('error', 'Reviewers may only assign themselves.');

            return $this->redirectBack();
        }

        if (!$blog->userCan($reviewerId, 'review_posts')) {
            $this->flash('error', 'That person cannot review posts on this blog.');

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

        $isSelfRelease = $reviewerId === (int) $user['id'];

        $canUnassignOthers = auth()->hasRole('administrator') || $this->managesReviewers($post->blog(), (int) $user['id']);
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

    /**
     * Whether a user may hand posts to other reviewers: managing every post
     * plus the assign permission. Reviewers who only hold the assign permission
     * may claim and release for themselves.
     */
    private function managesReviewers(BlogResource $blog, int $userId): bool
    {
        return $blog->userCan($userId, 'edit_blog_posts') && $blog->userCan($userId, 'assign_reviewers');
    }

    /**
     * The viewer's role on the blog as shown on the review screen, custom
     * roles under their own name.
     */
    private function roleLabelFor(BlogResource $blog, int $userId): ?string
    {
        $role = $blog->ownerId() === $userId ? 'owner' : $this->blogModel->storedRoleFor($blog->id(), $userId);

        return $role === null ? null : $this->roleLabels->label($role);
    }

    private function reviewUrl(int $postId): string
    {
        return "/dashboard/post/{$postId}/review";
    }

    /**
     * Someone else moved the post first, or it left review while this page was open.
     */
    private function movedOn(): Response
    {
        $this->flash('error', 'This post has moved on since you opened it. Reload to see where it is now.');

        return $this->redirectBack();
    }
}
