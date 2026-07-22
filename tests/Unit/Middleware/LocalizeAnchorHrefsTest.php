<?php

declare(strict_types=1);

use App\Middleware\LocalizeAnchorHrefs;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * LocalizeAnchorHrefs Unit Test Suite
 *
 * This middleware rewrites every root-relative anchor in every HTML response,
 * so a regex mistake here is invisible until a whole section of the site stops
 * being localized. The fixtures below pin the cases that actually differ.
 */
beforeEach(function () {
    $_SESSION['locale'] = 'en';

    $this->middleware = new LocalizeAnchorHrefs();

    $this->run = function (string $html): string {
        $response = new Response();
        $response->addHeader('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        $handler = new class($response) implements RequestHandlerInterface
        {
            public function __construct(private Response $response) {}

            public function handle(Request $request): Response
            {
                return $this->response;
            }
        };

        return $this->middleware
            ->process(new Request('/', 'GET', [], [], [], [], [], []), $handler)
            ->getBody();
    };
});

afterEach(function () {
    unset($_SESSION['locale']);
});

test('a root-relative href gains the current locale', function () {
    expect(($this->run)('<a href="/dashboard">Go</a>'))
        ->toContain('href="/en/dashboard"');
});

test('an href that already carries a locale is left alone', function () {
    $html = ($this->run)('<a href="/en/dashboard">Go</a>');

    expect($html)->toContain('href="/en/dashboard"')
        ->and($html)->not->toContain('/en/en/');
});

test('every configured locale is recognised as already-localized', function (string $locale) {
    expect(($this->run)('<a href="/'.$locale.'/posts">Go</a>'))
        ->not->toContain('/en/'.$locale.'/');
})->with(['en', 'fr', 'de', 'el', 'ar']);

/**
 * The bug this middleware shipped with: the guard was a generic [a-z]{2}, so
 * ANY two-letter first segment looked like a locale and was skipped.
 */
test('a two-letter path segment that is not a locale still gets localized', function (string $path) {
    expect(($this->run)('<a href="/'.$path.'/thing">Go</a>'))
        ->toContain('href="/en/'.$path.'/thing"');
})->with(['go', 'my', 'ui', 'qa']);

test('protocol-relative hrefs are never rewritten', function () {
    $html = ($this->run)('<a href="//evil.example.com/x">Go</a>');

    expect($html)->toContain('href="//evil.example.com/x"')
        ->and($html)->not->toContain('/en//evil');
});

test('absolute and non-http schemes are untouched', function (string $href) {
    expect(($this->run)('<a href="'.$href.'">Go</a>'))->toContain('href="'.$href.'"');
})->with([
    'https://example.com/page',
    'mailto:someone@example.com',
    '#anchor',
    'tel:+15551234',
]);

test('single-quoted hrefs are handled', function () {
    expect(($this->run)("<a href='/dashboard'>Go</a>"))
        ->toContain("href='/en/dashboard'");
});

test('non-HTML responses are passed through untouched', function () {
    $response = new Response();
    $response->addHeader('Content-Type', 'application/json');
    $response->setBody('{"href":"/dashboard"}');

    $handler = new class($response) implements RequestHandlerInterface
    {
        public function __construct(private Response $response) {}

        public function handle(Request $request): Response
        {
            return $this->response;
        }
    };

    $out = $this->middleware->process(new Request('/', 'GET', [], [], [], [], [], []), $handler);

    expect($out->getBody())->toBe('{"href":"/dashboard"}');
});
