<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\PostReviewerModel;
use App\Models\ReviewModel;
use App\Models\UserModel;
use App\Resources\BlogResource;
use App\Resources\PostResource;
use App\Services\NotificationService;
use App\Services\WorkflowService;

/**
 * Unit tests for WorkflowService state machine.
 *
 * All dependencies are mocked — tests verify orchestration only.
 */
afterEach(fn () => Mockery::close());

// ============================================================================
// Helpers
// ============================================================================

function makeWorkflowService(
    ?PostModel $post = null,
    ?PostReviewerModel $postReviewer = null,
    ?ReviewModel $review = null,
    ?UserModel $users = null,
    ?NotificationService $notifications = null,
    ?BlogModel $blogs = null,
    ?BlogSettingsModel $blogSettings = null,
): WorkflowService {
    if ($users === null) {
        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->andReturn(['id' => 0, 'handle' => 'tester'])->byDefault();
    }

    if ($blogs === null) {
        $blogs = Mockery::mock(BlogModel::class);
        $blogs->shouldReceive('getActiveUsersWithPermission')->andReturn([])->byDefault();
    }

    if ($blogSettings === null) {
        $blogSettings = Mockery::mock(BlogSettingsModel::class);
        $blogSettings->shouldReceive('findByBlogId')->andReturn([])->byDefault();
    }

    return new WorkflowService(
        $post ?? Mockery::mock(PostModel::class),
        $postReviewer ?? Mockery::mock(PostReviewerModel::class),
        $review ?? Mockery::mock(ReviewModel::class),
        $users,
        $notifications ?? Mockery::mock(NotificationService::class),
        $blogs,
        $blogSettings,
    );
}

function mockPostResource(int $authorId, string $workflowState = 'draft'): PostResource
{
    $blog = Mockery::mock(BlogResource::class);
    $blog->shouldReceive('ownerId')->andReturn(1)->byDefault();
    $blog->shouldReceive('id')->andReturn(42)->byDefault();
    $blog->shouldReceive('name')->andReturn('Test Blog')->byDefault();
    $blog->shouldReceive('slug')->andReturn('test-blog')->byDefault();

    $post = Mockery::mock(PostResource::class);
    $post->shouldReceive('id')->andReturn(10)->byDefault();
    $post->shouldReceive('authorId')->andReturn($authorId)->byDefault();
    $post->shouldReceive('workflowState')->andReturn($workflowState)->byDefault();
    $post->shouldReceive('title')->andReturn('Test Post')->byDefault();
    $post->shouldReceive('slug')->andReturn('test-post')->byDefault();
    $post->shouldReceive('blog')->andReturn($blog)->byDefault();

    return $post;
}

// ============================================================================
// submitForReview
// ============================================================================

describe('WorkflowService::submitForReview', function () {

    test('unassigned submission notifies reviewer-capable members but never the author', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->with(10, 5)->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(5));

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([]);

        // Blog 42 has the owner (1) and the author (5) holding review_posts
        $blogs = Mockery::mock(BlogModel::class);
        $blogs->shouldReceive('getActiveUsersWithPermission')
            ->with(42, 'review_posts')
            ->andReturn([['user_id' => 1], ['user_id' => 5]]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(1, 'post.submitted_unassigned', Mockery::any())->once();
        $notifications->shouldReceive('dispatch')
            ->with(5, Mockery::any(), Mockery::any())->never();

        $service = makeWorkflowService($postModel, $postReviewer, null, null, $notifications, $blogs);
        $service->submitForReview(10, 5);

        // Mockery verifies ->once() expectations on afterEach close()
        expect(true)->toBeTrue();
    });

    test('assigned submission notifies the assigned reviewer only', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->with(10, 5)->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(5));

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([['reviewer_id' => 7]]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(7, 'post.submitted', Mockery::any())->once();

        $service = makeWorkflowService($postModel, $postReviewer, null, null, $notifications);
        $service->submitForReview(10, 5);

        expect(true)->toBeTrue();
    });

    test('throws when the post is not a draft or awaiting changes', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->andReturn(false);

        $service = makeWorkflowService($postModel);

        expect(fn () => $service->submitForReview(10, 5))
            ->toThrow(\RuntimeException::class);
    });
});

// ============================================================================
// submitIfRequested
// ============================================================================

describe('WorkflowService::submitIfRequested', function () {

    function workflowOn(bool $on): BlogSettingsModel
    {
        $settings = Mockery::mock(BlogSettingsModel::class);
        $settings->shouldReceive('findByBlogId')->andReturn(['workflow_enabled' => $on ? 1 : 0]);

        return $settings;
    }

    test('submits a pending save from a draft when the blog reviews posts', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->with(10, 5)->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->andReturn(false);

        $service = makeWorkflowService($postModel, null, null, null, null, null, workflowOn(true));

        expect($service->submitIfRequested(10, 42, 'pending', 'draft', 5))->toBeTrue();
    });

    test('resubmits a post that was sent back for changes', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->with(10, 5)->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->andReturn(false);

        $service = makeWorkflowService($postModel, null, null, null, null, null, workflowOn(true));

        expect($service->submitIfRequested(10, 42, 'pending', 'needs_changes', 5))->toBeTrue();
    });

    test('leaves alone a save that did not ask for review', function (string $status, string $state) {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->never();

        $service = makeWorkflowService($postModel, null, null, null, null, null, workflowOn(true));

        expect($service->submitIfRequested(10, 42, $status, $state, 5))->toBeFalse();
    })->with([
        'saved as draft' => ['draft', 'draft'],
        'already in review' => ['pending', 'in_review'],
        'already approved' => ['pending', 'approved'],
    ]);

    test('does nothing on a blog without the review pipeline', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->never();

        $service = makeWorkflowService($postModel, null, null, null, null, null, workflowOn(false));

        expect($service->submitIfRequested(10, 42, 'pending', 'draft', 5))->toBeFalse();
    });

    test('reports false when the post moved on before it could be submitted', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('enterReview')->andReturn(false);

        $service = makeWorkflowService($postModel, null, null, null, null, null, workflowOn(true));

        expect($service->submitIfRequested(10, 42, 'pending', 'draft', 5))->toBeFalse();
    });
});

// ============================================================================
// concludeReview
// ============================================================================

describe('WorkflowService::concludeReview', function () {

    function postBefore(string $status, string $state): PostResource
    {
        $post = mockPostResource(authorId: 3, workflowState: $state);
        $post->shouldReceive('status')->andReturn($status);

        return $post;
    }

    test('records an approval when a post is published straight out of review', function (string $state, string $newStatus, string $told) {
        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')->with(10, 5, 'approved', '')->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->with(3, $told, Mockery::any())->once();

        $service = makeWorkflowService(null, null, $review, null, $notifications);
        $service->concludeReview(postBefore('pending', $state), $newStatus, 5);

        expect(true)->toBeTrue();
    })->with([
        'in review, published' => ['in_review', 'published', 'post.published'],
        'in review, scheduled' => ['in_review', 'scheduled', 'post.approved'],
        'awaiting changes, published' => ['needs_changes', 'published', 'post.published'],
    ]);

    test('records nothing when the post already has its approval or was never in review', function (string $status, string $state, string $newStatus) {
        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')->never();

        $service = makeWorkflowService(null, null, $review);
        $service->concludeReview(postBefore($status, $state), $newStatus, 5);

        expect(true)->toBeTrue();
    })->with([
        'already approved' => ['pending', 'approved', 'published'],
        'a plain draft' => ['draft', 'draft', 'published'],
        'withdrawn to draft' => ['pending', 'in_review', 'draft'],
    ]);
});

// ============================================================================
// startReview
// ============================================================================

describe('WorkflowService::startReview', function () {

    function submission(string $status = 'pending', string $state = 'in_review', ?string $date = null): PostResource
    {
        $post = mockPostResource(authorId: 3, workflowState: $state);
        $post->shouldReceive('status')->andReturn($status);
        $post->shouldReceive('publishedAt')->andReturn($date);

        return $post;
    }

    test('claims a submission nobody holds yet and tells its writer', function () {
        $reviewers = Mockery::mock(PostReviewerModel::class);
        $reviewers->shouldReceive('clearByPost')->never();
        $reviewers->shouldReceive('claim')->with(10, 7)->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->with(3, 'post.review_started', Mockery::any())->once();

        expect(makeWorkflowService(null, $reviewers, null, null, $notifications)->startReview(submission(), 7))->toBeTrue();
    });

    test('tells nobody when someone else got there first', function () {
        $reviewers = Mockery::mock(PostReviewerModel::class);
        $reviewers->shouldReceive('claim')->andReturn(false);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->never();

        expect(makeWorkflowService(null, $reviewers, null, null, $notifications)->startReview(submission(), 7))->toBeFalse();
    });

    test('a take-over clears the current reviewer first', function () {
        $reviewers = Mockery::mock(PostReviewerModel::class);
        $reviewers->shouldReceive('clearByPost')->with(10)->once()->ordered();
        $reviewers->shouldReceive('claim')->with(10, 7)->once()->ordered()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->once();

        expect(makeWorkflowService(null, $reviewers, null, null, $notifications)->startReview(submission(), 7, takeOver: true))->toBeTrue();
    });

    test('there is nothing to start once the post has left review', function (string $status, string $state) {
        $reviewers = Mockery::mock(PostReviewerModel::class);
        $reviewers->shouldReceive('claim')->never();

        expect(makeWorkflowService(null, $reviewers)->startReview(submission($status, $state), 7))->toBeFalse();
    })->with([
        'sent back for changes' => ['pending', 'needs_changes'],
        'approved' => ['pending', 'approved'],
        'withdrawn' => ['draft', 'draft'],
    ]);
});

// ============================================================================
// publish
// ============================================================================

describe('WorkflowService::publish', function () {

    test('publishes now and records the approval when the post has no future date', function (?string $date) {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('publishNow')->with(10)->once()->andReturn(true);
        $postModel->shouldReceive('updateStatus')->never();

        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')->with(10, 5, 'approved', '')->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch');

        $service = makeWorkflowService($postModel, null, $review, null, $notifications);

        expect($service->publish(submission(date: $date), 5))->toBe('published');
    })->with([
        'no date' => [null],
        'a past date' => ['2026-01-02 03:04:05'],
    ]);

    test('schedules a post that carries a future date', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('updateStatus')->with(10, 'scheduled')->once()->andReturn(true);
        $postModel->shouldReceive('publishNow')->never();

        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch');

        $service = makeWorkflowService($postModel, null, $review, null, $notifications);

        expect($service->publish(submission(date: gmdate('Y-m-d H:i:s', time() + 86400)), 5))->toBe('scheduled');
    });

    test('an already approved post goes out without a second approval', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('publishNow')->once()->andReturn(true);

        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')->never();

        $service = makeWorkflowService($postModel, null, $review);

        expect($service->publish(submission(state: 'approved'), 5))->toBe('published');
    });
});

// ============================================================================
// approve
// ============================================================================

describe('WorkflowService::approve', function () {

    test('tells everyone who can publish, except the approver and the writer', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(3));

        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')->andReturn(true);

        $blogs = Mockery::mock(BlogModel::class);
        $blogs->shouldReceive('getActiveUsersWithPermission')->with(42, 'publish_blog_posts')
            ->andReturn([['user_id' => 1], ['user_id' => 3], ['user_id' => 5], ['user_id' => 8]]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->with(3, 'post.approved', Mockery::any())->once();
        $notifications->shouldReceive('dispatch')->with(1, 'post.ready_to_publish', Mockery::any())->once();
        $notifications->shouldReceive('dispatch')->with(8, 'post.ready_to_publish', Mockery::any())->once();
        $notifications->shouldReceive('dispatch')->with(5, Mockery::any(), Mockery::any())->never();
        $notifications->shouldReceive('dispatch')->with(3, 'post.ready_to_publish', Mockery::any())->never();

        makeWorkflowService($postModel, null, $review, null, $notifications, $blogs)->approve(10, 5);

        expect(true)->toBeTrue();
    });

    test('transitions post to approved, records review, notifies author', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')
            ->with(10, 'approved', 5, ['in_review'])->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(3));

        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')
            ->with(10, 5, 'approved', 'Looks great')->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(3, 'post.approved', Mockery::any())->once();

        $service = makeWorkflowService($postModel, null, $review, null, $notifications);
        $service->approve(10, 5, 'Looks great');

        expect(true)->toBeTrue();
    });

    test('throws RuntimeException if transition fails', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')->andReturn(false);

        $service = makeWorkflowService($postModel);

        expect(fn () => $service->approve(10, 5))
            ->toThrow(\RuntimeException::class);
    });
});

// ============================================================================
// requestChanges
// ============================================================================

describe('WorkflowService::requestChanges', function () {

    test('transitions post to needs_changes, records needs_revision review, notifies author', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')
            ->with(10, 'needs_changes', 5, ['in_review', 'approved'])->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(3));

        $review = Mockery::mock(ReviewModel::class);
        $review->shouldReceive('create')
            ->with(10, 5, 'needs_revision', 'Please fix the intro')->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(3, 'post.needs_changes', Mockery::any())->once();

        $service = makeWorkflowService($postModel, null, $review, null, $notifications);
        $service->requestChanges(10, 5, 'Please fix the intro');

        expect(true)->toBeTrue();
    });

    test('throws RuntimeException if transition fails', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')->andReturn(false);

        $service = makeWorkflowService($postModel);

        expect(fn () => $service->requestChanges(10, 5, 'feedback'))
            ->toThrow(\RuntimeException::class);
    });
});

// ============================================================================
// resetToDraft
// ============================================================================

describe('WorkflowService::resetToDraft', function () {

    test('does nothing when the post was not pending', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('leaveReview')->with(10, 5)->once()->andReturn(false);

        $reviewers = Mockery::mock(PostReviewerModel::class);
        $reviewers->shouldReceive('findByPost')->andReturn([['reviewer_id' => 7]]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->never();

        makeWorkflowService($postModel, $reviewers, null, null, $notifications)->resetToDraft(10, 5);

        expect(true)->toBeTrue();
    });

    test('tells the reviewer holding it, but not someone withdrawing it themselves', function (int $actorId, int $told) {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('leaveReview')->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(3));

        $reviewers = Mockery::mock(PostReviewerModel::class);
        $reviewers->shouldReceive('findByPost')->with(10)->andReturn([['reviewer_id' => 7]]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->with(7, 'post.withdrawn', Mockery::any())->times($told);

        makeWorkflowService($postModel, $reviewers, null, null, $notifications)->resetToDraft(10, $actorId);

        expect(true)->toBeTrue();
    })->with([
        'the writer withdraws' => [3, 1],
        'the reviewer returns it' => [7, 0],
    ]);
});

// ============================================================================
// assignReviewer
// ============================================================================

describe('WorkflowService::assignReviewer', function () {

    test('clears existing assignment, assigns reviewer, sends notification to reviewer', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(mockPostResource(3));

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('clearByPost')->with(10)->once();
        $postReviewer->shouldReceive('assign')->with(10, 7, 5)->once()->andReturn(true);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(7, 'post.reviewer_assigned', Mockery::any())->once();

        $service = makeWorkflowService($postModel, $postReviewer, null, null, $notifications);
        $service->assignReviewer(postId: 10, reviewerId: 7, assignedBy: 5, postAuthorId: 3);

        expect(true)->toBeTrue();
    });
});

// ============================================================================
// checkStaleReviewer
// ============================================================================

describe('WorkflowService::checkStaleReviewer', function () {

    test('clears assignment and notifies owner when reviewer lost review capability', function () {
        $post = mockPostResource(authorId: 3, workflowState: 'in_review');
        $blog = $post->blog();
        // Reviewer (id=7) is now an author on this blog, so they lost review_posts
        $blog->shouldReceive('userCan')->with(7, 'review_posts')->andReturn(false);

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([
            ['reviewer_id' => 7, 'reviewer_handle' => 'jane'],
        ]);
        $postReviewer->shouldReceive('clearByPost')->with(10)->once();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(1, 'post.reviewer_stale', Mockery::any())->once();

        $service = makeWorkflowService(null, $postReviewer, null, null, $notifications);
        $service->checkStaleReviewer(postId: 10, post: $post, ownerId: 1);

        expect(true)->toBeTrue();
    });

    test('does nothing when assigned reviewer still has review capability', function () {
        $post = mockPostResource(authorId: 3, workflowState: 'in_review');
        $blog = $post->blog();
        $blog->shouldReceive('userCan')->with(7, 'review_posts')->andReturn(true);

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([
            ['reviewer_id' => 7, 'reviewer_handle' => 'jane'],
        ]);
        $postReviewer->shouldReceive('clearByPost')->never();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->never();

        $service = makeWorkflowService(null, $postReviewer, null, null, $notifications);
        $service->checkStaleReviewer(postId: 10, post: $post, ownerId: 1);

        expect(true)->toBeTrue();
    });

    test('does nothing when no reviewer is assigned', function () {
        $post = mockPostResource(authorId: 3, workflowState: 'in_review');

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([]);
        $postReviewer->shouldReceive('clearByPost')->never();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->never();

        $service = makeWorkflowService(null, $postReviewer, null, null, $notifications);
        $service->checkStaleReviewer(postId: 10, post: $post, ownerId: 1);

        expect(true)->toBeTrue();
    });
});

// ============================================================================
// disableWorkflow
// ============================================================================

describe('WorkflowService::disableWorkflow', function () {

    test('returns every pending post to draft and notifies each author', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('findInFlightByBlogId')->with(42)->andReturn([
            ['id' => 10, 'author_id' => 3, 'workflow_state' => 'in_review'],
            ['id' => 11, 'author_id' => 4, 'workflow_state' => 'needs_changes'],
        ]);
        $postModel->shouldReceive('leaveReview')->with(10, 99)->once()->andReturn(true);
        $postModel->shouldReceive('leaveReview')->with(11, 99)->once()->andReturn(true);
        // findResource returns a minimal PostResource stub per call; blog() needed for blog_name payload
        $postModel->shouldReceive('findResource')->andReturnUsing(function () {
            $blog = Mockery::mock(\App\Resources\BlogResource::class);
            $blog->shouldReceive('name')->andReturn('')->byDefault();
            $r = Mockery::mock(\App\Resources\PostResource::class);
            $r->shouldReceive('title')->andReturn('')->byDefault();
            $r->shouldReceive('blog')->andReturn($blog)->byDefault();

            return $r;
        })->byDefault();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->twice();

        $service = makeWorkflowService($postModel, null, null, null, $notifications);
        $service->disableWorkflow(blogId: 42, actorId: 99);

        expect(true)->toBeTrue();
    });

    test('does nothing when no in-flight posts exist', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('findInFlightByBlogId')->with(42)->andReturn([]);
        $postModel->shouldReceive('leaveReview')->never();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->never();

        $service = makeWorkflowService($postModel, null, null, null, $notifications);
        $service->disableWorkflow(blogId: 42, actorId: 99);

        expect(true)->toBeTrue();
    });
});

// ============================================================================
// constrainStatus / canPublish
// ============================================================================

describe('WorkflowService::constrainStatus', function () {

    function makeConstraintService(bool $workflowEnabled): WorkflowService
    {
        $blogSettings = Mockery::mock(BlogSettingsModel::class);
        $blogSettings->shouldReceive('findByBlogId')
            ->andReturn(['workflow_enabled' => $workflowEnabled ? 1 : 0])->byDefault();

        return makeWorkflowService(null, null, null, null, null, null, $blogSettings);
    }

    test('someone who may publish keeps whatever status they requested', function () {
        $service = makeConstraintService(workflowEnabled: true);

        expect($service->constrainStatus('published', true, 42))->toBe('published');
        expect($service->constrainStatus('archived', true, 42, 'published'))->toBe('archived');
    });

    test('publishing without authority on a workflow blog is clamped to pending', function () {
        $service = makeConstraintService(workflowEnabled: true);

        expect($service->constrainStatus('published', false, 42))->toBe('pending');
    });

    test('publishing without authority and without workflow is clamped to draft', function () {
        $service = makeConstraintService(workflowEnabled: false);

        expect($service->constrainStatus('published', false, 42))->toBe('draft');
    });

    test('resubmitting the current status is always allowed', function () {
        $service = makeConstraintService(workflowEnabled: true);

        expect($service->constrainStatus('published', false, 42, 'published'))->toBe('published');
    });

    test('a post cannot be unpublished without publish authority', function () {
        $service = makeConstraintService(workflowEnabled: true);

        expect($service->constrainStatus('draft', false, 42, 'published'))->toBe('published');
    });
});

describe('WorkflowService::canPublish', function () {

    function blogGranting(array $permissions, bool $reviews): BlogResource
    {
        $model = Mockery::mock(BlogModel::class);
        $model->shouldReceive('blogPermissionsFor')->andReturn($permissions);
        $model->shouldReceive('reviewsPosts')->with(42)->andReturn($reviews);

        return new BlogResource(['id' => 42, 'owner_id' => 1], $model);
    }

    test('publish_blog_posts publishes with or without the pipeline', function () {
        $service = makeWorkflowService();

        expect($service->canPublish(blogGranting(['publish_blog_posts'], reviews: true), 7))->toBeTrue()
            ->and($service->canPublish(blogGranting(['publish_blog_posts'], reviews: false), 7))->toBeTrue();
    });

    test('publish_own_posts publishes only while the pipeline is off', function () {
        $service = makeWorkflowService();

        expect($service->canPublish(blogGranting(['publish_own_posts'], reviews: false), 7))->toBeTrue()
            ->and($service->canPublish(blogGranting(['publish_own_posts'], reviews: true), 7))->toBeFalse();
    });

    test('a role without either permission never publishes, whatever its name', function () {
        expect(makeWorkflowService()->canPublish(blogGranting(['create_posts', 'edit_own_posts'], reviews: false), 7))->toBeFalse();
    });
});
