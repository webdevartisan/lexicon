<?php

declare(strict_types=1);

use Framework\Core\Request;
use Framework\Exceptions\CsrfTokenException;
use Framework\Security\Csrf;
use Framework\Security\CsrfMiddleware;
use Framework\Session;
use Tests\Helpers\MiddlewareTestHelper;

/**
 * CsrfMiddleware Unit Test Suite
 *
 * This middleware is what turns CSRF protection from "every controller
 * remembered to call assertValid()" into an invariant, so its skip rules and
 * rejection paths are the things worth pinning down.
 */
beforeEach(function () {
    $this->session = app(Session::class);
    $this->csrf = new Csrf($this->session);
    $this->middleware = new CsrfMiddleware($this->csrf, $this->session);

    $this->token = $this->csrf->getToken();

    $this->request = function (string $method, array $post = [], array $headers = []): Request {
        return new Request('/anything', $method, [], $post, [], [], [], $headers);
    };
});

// ==================== SAFE METHODS ====================

test('safe methods pass through without a token', function (string $method) {
    $next = MiddlewareTestHelper::createHandler('reached');

    $result = $this->middleware->process(($this->request)($method), $next);

    expect($result->getBody())->toBe('reached');
})->with(['GET', 'HEAD', 'OPTIONS']);

// ==================== ACCEPTED CREDENTIALS ====================

test('a valid _token field is accepted', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    $result = $this->middleware->process(
        ($this->request)('POST', ['_token' => $this->token]),
        $next
    );

    expect($result->getBody())->toBe('reached');
});

test('a valid X-CSRF-TOKEN header is accepted', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    $result = $this->middleware->process(
        ($this->request)('POST', [], ['x-csrf-token' => $this->token]),
        $next
    );

    expect($result->getBody())->toBe('reached');
});

// ==================== REJECTION ====================

test('a mutating request with no token never reaches the handler', function (string $method) {
    $reached = false;
    $next = MiddlewareTestHelper::createTrackingHandler($reached);

    try {
        $this->middleware->process(($this->request)($method), $next);
    } catch (CsrfTokenException) {
        // No referer to bounce back to, so the 419 page path is expected here.
    }

    expect($reached)->toBeFalse();
})->with(['POST', 'PUT', 'PATCH', 'DELETE']);

test('a forged token is rejected', function () {
    $reached = false;
    $next = MiddlewareTestHelper::createTrackingHandler($reached);

    try {
        $this->middleware->process(
            ($this->request)('POST', ['_token' => 'forged-token-value']),
            $next
        );
    } catch (CsrfTokenException) {
    }

    expect($reached)->toBeFalse();
});

test('rejection without a usable referer throws CsrfTokenException', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    expect(fn () => $this->middleware->process(($this->request)('POST'), $next))
        ->toThrow(CsrfTokenException::class);
});

test('CsrfTokenException reports 419', function () {
    expect((new CsrfTokenException())->getStatusCode())->toBe(419);
});

// ==================== JSON CALLERS ====================

test('an AJAX caller gets a 419 JSON body rather than a redirect', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    $response = $this->middleware->process(
        ($this->request)('POST', [], ['x-requested-with' => 'XMLHttpRequest']),
        $next
    );

    expect($response->getStatusCode())->toBe(419)
        ->and(json_decode($response->getBody(), true))
        ->toMatchArray(['success' => false, 'error' => 'Invalid CSRF token.']);
});

test('an Accept: application/json caller also gets JSON', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    $response = $this->middleware->process(
        ($this->request)('POST', [], ['accept' => 'application/json']),
        $next
    );

    expect($response->getStatusCode())->toBe(419);
});

// ==================== REDIRECT-BACK PATH ====================

test('a same-origin referer sends the user back with a flash instead of an error page', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    $response = $this->middleware->process(
        ($this->request)('POST', [], [
            'referer' => 'http://localhost:8001/en/dashboard/account',
            'host' => 'localhost:8001',
        ]),
        $next
    );

    // Location is asserted loosely: Response::redirect() runs the target through
    // buildLocalizedUrl(), which may rewrite the locale segment.
    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('/dashboard/account');
});

test('an off-site referer is not used as a redirect target', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    expect(fn () => $this->middleware->process(
        ($this->request)('POST', [], [
            'referer' => 'https://evil.example.com/phish',
            'host' => 'localhost:8001',
        ]),
        $next
    ))->toThrow(CsrfTokenException::class);
});

/**
 * Regression guard for a bug found during this work: parse_url() returns a bare
 * host while the Host header carries the port, so comparing them directly made
 * every same-origin request on a non-default port look cross-origin.
 */
test('a same-origin referer still matches when the host carries a port', function () {
    $next = MiddlewareTestHelper::createHandler('reached');

    $response = $this->middleware->process(
        ($this->request)('POST', [], [
            'referer' => 'http://localhost:8001/en/dashboard?tab=general',
            'host' => 'localhost:8001',
        ]),
        $next
    );

    // The query string surviving is the point: without the port fix this never
    // reached a redirect at all.
    expect($response->getStatusCode())->toBe(302)
        ->and($response->getHeader('Location'))->toContain('tab=general');
});
