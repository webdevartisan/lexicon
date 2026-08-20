<?php

declare(strict_types=1);

/**
 * Feature tests for the comment throttle as CommentController applies it.
 *
 * The limiter's own behaviour is covered in the unit tests. What is pinned
 * here is the HTTP contract around it: a refused submission comes back as a
 * flash and a redirect because that route is a plain form post, while a
 * refused vote or report comes back as a 429 carrying Retry-After, which is
 * the shape public/assets/js/comments.js already knows how to display.
 *
 * Every collaborator except the throttle is a double, so no database is
 * touched and the throttle is the only thing deciding these outcomes.
 */

use App\Controllers\CommentController;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\CommentReportModel;
use App\Models\CommentVoteModel;
use App\Services\CommentRemovalService;
use App\Services\CommentService;
use Framework\Core\Response;
use Tests\Helpers\ThrottleTestHelper;

/**
 * A controller whose only real collaborator is the throttle.
 *
 * @return array{0: CommentController, 1: App\Services\CommentRateLimiter, 2: Mockery\MockInterface}
 */
function makeThrottledCommentController(): array
{
    [$throttle] = ThrottleTestHelper::commentThrottle();

    $comments = Mockery::mock(CommentService::class);

    $controller = new CommentController(
        $comments,
        Mockery::mock(CommentModel::class),
        Mockery::mock(CommentVoteModel::class),
        Mockery::mock(CommentReportModel::class),
        Mockery::mock(CommentRemovalService::class),
        Mockery::mock(BlogModel::class),
        $throttle,
    );

    return [$controller, $throttle, $comments];
}

/**
 * The flash messages queued during a request.
 *
 * @return array<string, array<int, string>>
 */
function flashedMessages(): array
{
    return \Framework\Core\App::container()->get(\Framework\Session::class)->get('_flash', []);
}

beforeEach(function () {
    $_SESSION = [];

    [$this->controller, $this->throttle, $this->comments] = makeThrottledCommentController();

    $this->viewer = Mockery::mock(\Framework\Interfaces\TemplateViewerInterface::class);
});

afterEach(function () {
    $_SESSION = [];
    Mockery::close();
});

// ============================================================================
// create()
// ============================================================================

it('lets an unthrottled submission through to the comment service', function () {
    $this->comments->shouldReceive('create')
        ->once()
        ->andReturn(['ok' => true, 'message' => 'Comment posted.', 'id' => 42]);

    $request = makeRequest('/comments/create', 'POST', [
        '_token' => csrf()->getToken(),
        'post_id' => '7',
        'content' => 'A perfectly ordinary comment.',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->create();

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('#comment-42');
});

it('refuses a submission over the limit with a flash instead of a comment', function () {
    // The route is a plain form post, so the refusal has to be a redirect the
    // reader can see, not JSON. The service must never be reached.
    $this->comments->shouldReceive('create')->never();

    $ip = '127.0.0.1';
    for ($i = 0; $i < 6; $i++) {
        $this->throttle->hitSubmission($ip);
    }

    $request = makeRequest('/comments/create', 'POST', [
        '_token' => csrf()->getToken(),
        'post_id' => '7',
        'content' => 'One comment too many.',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->create();

    expect($response->getStatusCode())->toBe(302)
        ->and(flashedMessages()['error'][0] ?? '')->toContain('going a little fast');
});

it('throttles the guest capture path too, before anything is stashed in the session', function () {
    // A guest replying is bounced to login with the reply held in the session.
    // That path writes session state and issues a redirect per request, so it
    // has to sit behind the throttle as well.
    $this->comments->shouldReceive('capturePending')->never();
    $this->comments->shouldReceive('contentLengthError')->never();

    $ip = '127.0.0.1';
    for ($i = 0; $i < 6; $i++) {
        $this->throttle->hitSubmission($ip);
    }

    $request = makeRequest('/comments/create', 'POST', [
        '_token' => csrf()->getToken(),
        'post_id' => '7',
        'parent_comment_id' => '3',
        'content' => 'A guest reply that never gets stashed.',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->create();

    expect($response->getStatusCode())->toBe(302)
        ->and(flashedMessages()['error'][0] ?? '')->toContain('going a little fast');
});

it('counts a submission that the service rejects', function () {
    // Otherwise a flood of invalid posts costs nothing and the throttle only
    // sees the ones that happened to be well formed.
    $this->comments->shouldReceive('create')
        ->once()
        ->andReturn(['ok' => false, 'message' => 'Comment content is required.', 'id' => null]);

    $request = makeRequest('/comments/create', 'POST', [
        '_token' => csrf()->getToken(),
        'post_id' => '7',
        'content' => '',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $this->controller->create();

    expect($this->throttle->submissionAvailableIn('127.0.0.1'))->toBe(0)
        ->and($this->throttle->hitSubmission('127.0.0.1'))->toBeFalse();

    // Five accounted for now (one from the request, one from the line above),
    // so the sixth is the one that trips.
    for ($i = 0; $i < 3; $i++) {
        $this->throttle->hitSubmission('127.0.0.1');
    }

    expect($this->throttle->hitSubmission('127.0.0.1'))->toBeTrue();
});

// ============================================================================
// vote() and report()
// ============================================================================

it('answers an over-limit vote with 429 and a Retry-After header', function () {
    $ip = '127.0.0.1';
    for ($i = 0; $i < 60; $i++) {
        $this->throttle->hitInteraction($ip);
    }

    $request = makeRequest('/comments/5/vote', 'POST', [
        '_token' => csrf()->getToken(),
        'direction' => 'up',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->vote('5');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(429)
        ->and((int) $response->getHeader('Retry-After'))->toBeGreaterThan(0);

    // comments.js reads json.error out of any non-ok response, so the reader
    // sees this string rather than a silent no-op.
    $body = json_decode($response->getBody(), true);

    expect($body['success'])->toBeFalse()
        ->and($body['error'])->toContain('going a little fast');
});

it('answers an over-limit report with 429 as well', function () {
    $ip = '127.0.0.1';
    for ($i = 0; $i < 60; $i++) {
        $this->throttle->hitInteraction($ip);
    }

    $request = makeRequest('/comments/5/report', 'POST', [
        '_token' => csrf()->getToken(),
        'reason' => 'spam',
    ]);
    setupController($this->controller, $request, $this->viewer);

    $response = $this->controller->report('5');

    expect($response->getStatusCode())->toBe(429)
        ->and(json_decode($response->getBody(), true)['error'])->toContain('going a little fast');
});

it('does not throttle a vote just because the reader is blocked from posting', function () {
    $ip = '127.0.0.1';
    for ($i = 0; $i < 6; $i++) {
        $this->throttle->hitSubmission($ip);
    }

    $request = makeRequest('/comments/5/vote', 'POST', [
        '_token' => csrf()->getToken(),
        'direction' => 'sideways',
    ]);
    setupController($this->controller, $request, $this->viewer);

    // An invalid direction rather than a 429 proves the request got past the
    // throttle and into the action's own validation.
    $response = $this->controller->vote('5');

    expect($response->getStatusCode())->toBe(422);
});
