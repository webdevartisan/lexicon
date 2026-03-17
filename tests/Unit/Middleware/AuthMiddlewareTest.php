<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use Framework\Core\Request;
use Framework\Exceptions\UnauthorizedException;
use Tests\Helpers\MiddlewareTestHelper;

// ==================== AUTHENTICATED USER (PASS THROUGH) ====================

it('allows authenticated users to pass through', function () {
    $auth = MiddlewareTestHelper::mockAuth(true);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->word();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $bodyContent = $this->faker->sentence();
    $next = MiddlewareTestHelper::createHandler($bodyContent);

    $result = $middleware->process($request, $next);

    expect($result->getBody())->toBe($bodyContent);
});

it('passes request to next handler when authenticated', function () {
    $auth = MiddlewareTestHelper::mockAuth(true);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $handlerCalled = false;
    $next = MiddlewareTestHelper::createTrackingHandler($handlerCalled);

    $middleware->process($request, $next);

    expect($handlerCalled)->toBeTrue();
});

it('preserves original request when passing to next handler', function () {
    $auth = MiddlewareTestHelper::mockAuth(true);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $postData = ['key' => $this->faker->word()];
    $originalRequest = new Request($url, 'POST', $postData, [], [], [], [], []);

    $capturedRequest = null;
    $next = MiddlewareTestHelper::createCapturingHandler($capturedRequest);

    $middleware->process($originalRequest, $next);

    expect($capturedRequest)->toBe($originalRequest);
});

// ==================== UNAUTHENTICATED - HTML REQUESTS (REDIRECT) ====================

it('redirects unauthenticated users with text/html Accept header to login', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'text/html',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Location'))->toContain('/login')
        ->and($result->getStatusCode())->toBe(302);
});

it('redirects when Accept header is missing', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], []);

    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Location'))->toContain('/login')
        ->and($result->getStatusCode())->toBe(302);
});

it('redirects for complex browser Accept headers', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Location'))->toContain('/login')
        ->and($result->getStatusCode())->toBe(302);
});

it('redirects for XHTML Accept headers', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'application/xhtml+xml',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Location'))->toContain('/login');
});

it('redirects for various HTTP methods', function ($method) {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, $method, [], [], [], [], [], [
        'accept' => 'text/html',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    expect($result->getHeader('Location'))->toContain('/login');
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

// ==================== UNAUTHENTICATED - JSON REQUESTS (EXCEPTION) ====================

it('throws UnauthorizedException for application/json Accept header', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->word();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'application/json',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    expect(fn () => $middleware->process($request, $next))
        ->toThrow(UnauthorizedException::class, 'Authentication required to access this resource.');
});

it('throws UnauthorizedException with 401 status code', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->word();
    $request = new Request($url, 'POST', [], [], [], [], [], [
        'accept' => 'application/json',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    try {
        $middleware->process($request, $next);
        expect(true)->toBeFalse(); // Should not reach here
    } catch (UnauthorizedException $e) {
        expect($e->getStatusCode())->toBe(401)
            ->and($e->getMessage())->toBe('Authentication required to access this resource.');
    }
});

it('throws exception for text/json Accept header', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'text/json',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    expect(fn () => $middleware->process($request, $next))
        ->toThrow(UnauthorizedException::class);
});

it('throws exception for JSON with charset', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->word();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'application/json; charset=utf-8',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    expect(fn () => $middleware->process($request, $next))
        ->toThrow(UnauthorizedException::class);
});

it('detects JSON in complex Accept header with multiple types', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->slug();
    $request = new Request($url, 'DELETE', [], [], [], [], [], [
        'accept' => 'text/html, application/json;q=0.9, */*;q=0.8',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    expect(fn () => $middleware->process($request, $next))
        ->toThrow(UnauthorizedException::class);
});

it('handles case-insensitive Accept header matching', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->word();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'Application/JSON',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    expect(fn () => $middleware->process($request, $next))
        ->toThrow(UnauthorizedException::class);
});

it('throws exception for JSON requests with various HTTP methods', function ($method) {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->slug();
    $request = new Request($url, $method, [], [], [], [], [], [
        'accept' => 'application/json',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    expect(fn () => $middleware->process($request, $next))
        ->toThrow(UnauthorizedException::class);
})->with(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

// ==================== HANDLER NOT CALLED WHEN UNAUTHENTICATED ====================

it('does not call next handler when unauthenticated with HTML request', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'text/html',
    ]);

    $handlerCalled = false;
    $next = MiddlewareTestHelper::createTrackingHandler($handlerCalled);

    $middleware->process($request, $next);

    expect($handlerCalled)->toBeFalse();
});

it('does not call next handler when throwing exception for JSON request', function () {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    $url = '/api/'.$this->faker->slug();
    $request = new Request($url, 'GET', [], [], [], [], [], [
        'accept' => 'application/json',
    ]);

    $handlerCalled = false;
    $next = MiddlewareTestHelper::createTrackingHandler($handlerCalled);

    try {
        $middleware->process($request, $next);
    } catch (UnauthorizedException $e) {
        // Expected
    }

    expect($handlerCalled)->toBeFalse();
});

// ==================== SECURITY EDGE CASES ====================

it('handles XSS attempts in URL paths safely', function ($xssPayload) {
    $auth = MiddlewareTestHelper::mockAuth(false);
    $middleware = new AuthMiddleware($auth);

    // XSS in URL path shouldn't break middleware logic
    $request = new Request($xssPayload, 'GET', [], [], [], [], [], [
        'accept' => 'text/html',
    ]);

    $next = MiddlewareTestHelper::createHandler();

    $result = $middleware->process($request, $next);

    // Should still redirect regardless of URL content
    expect($result->getStatusCode())->toBe(302);
})->with('xss_payloads');
