<?php

declare(strict_types=1);

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
): WorkflowService {
    if ($users === null) {
        $users = Mockery::mock(UserModel::class);
        $users->shouldReceive('findById')->andReturn(['id' => 0, 'username' => 'tester'])->byDefault();
    }

    return new WorkflowService(
        $post ?? Mockery::mock(PostModel::class),
        $postReviewer ?? Mockery::mock(PostReviewerModel::class),
        $review ?? Mockery::mock(ReviewModel::class),
        $users,
        $notifications ?? Mockery::mock(NotificationService::class),
    );
}

function mockPostResource(int $authorId, string $workflowState = 'draft'): PostResource
{
    $blog = Mockery::mock(BlogResource::class);
    $blog->shouldReceive('ownerId')->andReturn(1)->byDefault();

    $post = Mockery::mock(PostResource::class);
    $post->shouldReceive('authorId')->andReturn($authorId)->byDefault();
    $post->shouldReceive('workflowState')->andReturn($workflowState)->byDefault();
    $post->shouldReceive('title')->andReturn('Test Post')->byDefault();
    $post->shouldReceive('blog')->andReturn($blog)->byDefault();

    return $post;
}

// ============================================================================
// submitForReview
// ============================================================================

describe('WorkflowService::submitForReview', function () {

    test('transitions draft post to in_review and sends notification', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')
            ->with(10, 'in_review', 5)->once()->andReturn(true);
        $postModel->shouldReceive('findResource')->with(10)->andReturn(false)->byDefault();

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([]);

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')
            ->with(5, 'post.submitted_unassigned', Mockery::any())->once();

        $service = makeWorkflowService($postModel, $postReviewer, null, null, $notifications);
        $service->submitForReview(10, 5);

        // Mockery verifies ->once() expectations on afterEach close()
        expect(true)->toBeTrue();
    });

    test('throws RuntimeException if post transition fails', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')->andReturn(false);

        $service = makeWorkflowService($postModel);

        expect(fn () => $service->submitForReview(10, 5))
            ->toThrow(\RuntimeException::class);
    });
});

// ============================================================================
// approve
// ============================================================================

describe('WorkflowService::approve', function () {

    test('transitions post to approved, records review, notifies author', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')
            ->with(10, 'approved', 5)->once()->andReturn(true);
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
            ->with(10, 'needs_changes', 5)->once()->andReturn(true);
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

    test('calls transitionWorkflow with draft state', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('transitionWorkflow')
            ->with(10, 'draft', 5)->once()->andReturn(true);

        $service = makeWorkflowService($postModel);
        $service->resetToDraft(10, 5);

        expect(true)->toBeTrue();
    });
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
        // Reviewer (id=7) now has 'author' role — lost review rights
        $blog->shouldReceive('roleForUser')->with(7)->andReturn('author');

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([
            ['reviewer_id' => 7, 'reviewer_username' => 'jane'],
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
        $blog->shouldReceive('roleForUser')->with(7)->andReturn('reviewer');

        $postReviewer = Mockery::mock(PostReviewerModel::class);
        $postReviewer->shouldReceive('findByPost')->with(10)->andReturn([
            ['reviewer_id' => 7, 'reviewer_username' => 'jane'],
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

    test('resets all in-flight posts to draft and notifies each author', function () {
        $postModel = Mockery::mock(PostModel::class);
        $postModel->shouldReceive('findInFlightByBlogId')->with(42)->andReturn([
            ['id' => 10, 'author_id' => 3, 'workflow_state' => 'in_review'],
            ['id' => 11, 'author_id' => 4, 'workflow_state' => 'needs_changes'],
        ]);
        $postModel->shouldReceive('transitionWorkflow')->with(10, 'draft', 99)->once()->andReturn(true);
        $postModel->shouldReceive('transitionWorkflow')->with(11, 'draft', 99)->once()->andReturn(true);
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
        $postModel->shouldReceive('transitionWorkflow')->never();

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('dispatch')->never();

        $service = makeWorkflowService($postModel, null, null, null, $notifications);
        $service->disableWorkflow(blogId: 42, actorId: 99);

        expect(true)->toBeTrue();
    });
});
