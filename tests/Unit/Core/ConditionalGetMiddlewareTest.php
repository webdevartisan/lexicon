<?php

declare(strict_types=1);

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Http\Middleware\ConditionalGetMiddleware;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * Public pages make browsers check back before reusing a copy. These pin that
 * an unchanged page answers that check with an empty 304, and that a changed,
 * private or failed page never does.
 */
beforeEach(function () {
    $this->middleware = new ConditionalGetMiddleware();

    $this->handlerFor = function (string $cacheControl, string $body = '<p>Post</p>', int $status = 200): RequestHandlerInterface {
        return new class($cacheControl, $body, $status) implements RequestHandlerInterface
        {
            public function __construct(private string $cacheControl, private string $body, private int $status) {}

            public function handle(Request $request): Response
            {
                $response = new Response();
                $response->setStatusCode($this->status);
                $response->addHeader('Cache-Control', $this->cacheControl);
                $response->setBody($this->body);

                return $response;
            }
        };
    };

    $this->get = fn (?string $ifNoneMatch = null): Request => new Request(
        '/blog/notes/a-post',
        'GET',
        [],
        [],
        [],
        [],
        [],
        $ifNoneMatch === null ? [] : ['if-none-match' => $ifNoneMatch]
    );
});

test('an unchanged page gets an empty 304 with its tag', function () {
    $handler = ($this->handlerFor)('public, no-cache');
    $etag = $this->middleware->process(($this->get)(), $handler)->getHeader('ETag');

    $again = $this->middleware->process(($this->get)($etag), $handler);

    expect($etag)->toMatch('/^"[0-9a-f]{32}"$/')
        ->and($again->getStatusCode())->toBe(304)
        ->and($again->getBody())->toBe('')
        ->and($again->getHeader('ETag'))->toBe($etag)
        ->and($again->getHeader('Cache-Control'))->toBe('public, no-cache');
});

test('a weak tag or one of several tags still counts as a match', function () {
    $handler = ($this->handlerFor)('public, no-cache');
    $etag = $this->middleware->process(($this->get)(), $handler)->getHeader('ETag');

    expect($this->middleware->process(($this->get)('W/'.$etag), $handler)->getStatusCode())->toBe(304)
        ->and($this->middleware->process(($this->get)('"older", '.$etag), $handler)->getStatusCode())->toBe(304);
});

test('a changed page is sent in full', function () {
    $etag = $this->middleware->process(($this->get)(), ($this->handlerFor)('public, no-cache', '<p>Before</p>'))
        ->getHeader('ETag');

    $after = $this->middleware->process(($this->get)($etag), ($this->handlerFor)('public, no-cache', '<p>After</p>'));

    expect($after->getStatusCode())->toBe(200)
        ->and($after->getBody())->toBe('<p>After</p>')
        ->and($after->getHeader('ETag'))->not->toBe($etag);
});

test('private, no-store and lifetime-cached pages get no tag', function (string $cacheControl) {
    $response = $this->middleware->process(($this->get)(), ($this->handlerFor)($cacheControl));

    expect($response->getHeader('ETag'))->toBeNull()
        ->and($response->getStatusCode())->toBe(200);
})->with([
    'signed in' => 'private, no-cache, must-revalidate',
    'no-store' => 'no-store, no-cache, must-revalidate',
    'feed lifetime' => 'public, max-age=180, stale-while-revalidate=60',
]);

test('a page that did not load is never answered with a 304', function () {
    $response = $this->middleware->process(($this->get)('"anything"'), ($this->handlerFor)('public, no-cache', 'Not found', 404));

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getHeader('ETag'))->toBeNull();
});
