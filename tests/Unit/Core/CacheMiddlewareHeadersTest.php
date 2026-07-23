<?php

declare(strict_types=1);

use Framework\Cache\CacheKey;
use Framework\Cache\CacheMiddleware;
use Framework\Cache\CacheService;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\AuthInterface;
use Framework\Interfaces\RequestHandlerInterface;

/**
 * The page cache stored only the body, so a cache hit rebuilt a bare Response
 * and PHP's default text/html won. Any non-HTML response served itself
 * correctly once and then lied on every later request. Proven with robots.txt:
 * the miss returned text/plain, the hit returned text/html, and nosniff meant
 * crawlers would refuse to parse it.
 */
beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir().'/lexicon-cache-'.bin2hex(random_bytes(4));
    mkdir($this->cacheDir, 0777, true);

    $this->cache = new CacheService($this->cacheDir, true);

    $this->auth = new class implements AuthInterface
    {
        public function check(): bool
        {
            return false;
        }

        public function user(): ?array
        {
            return null;
        }

        public function hasRole(string $role): bool
        {
            return false;
        }

        public function hasPermission(string $permission): bool
        {
            return false;
        }
    };

    // One handler run per request, so a second identical call proves the cache
    // answered rather than the controller.
    $this->handlerFor = function (string $contentType, string $body): RequestHandlerInterface {
        return new class($contentType, $body) implements RequestHandlerInterface
        {
            public function __construct(private string $contentType, private string $body) {}

            public function handle(Request $request): Response
            {
                $response = new Response();
                $response->addHeader('Content-Type', $this->contentType);
                $response->setBody($this->body);

                return $response;
            }
        };
    };

    $this->middleware = new CacheMiddleware(
        $this->cache,
        new CacheKey(),
        $this->auth,
        ['/*' => 600],
        false
    );
});

afterEach(function () {
    unset($_SESSION['locale']);

    if (is_dir($this->cacheDir)) {
        foreach (glob($this->cacheDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->cacheDir);
    }
});

test('a cached non-html response keeps its content type on the second request', function () {
    $request = new Request('/robots.txt', 'GET', [], [], [], [], [], []);
    $handler = ($this->handlerFor)('text/plain; charset=utf-8', 'User-agent: *');

    $miss = $this->middleware->process($request, $handler);
    $hit = $this->middleware->process($request, $handler);

    expect($miss->getHeader('Content-Type'))->toBe('text/plain; charset=utf-8')
        ->and($hit->getHeader('Content-Type'))->toBe('text/plain; charset=utf-8');
});

test('the cached body itself still comes back intact', function () {
    $request = new Request('/sitemap.xml', 'GET', [], [], [], [], [], []);
    $handler = ($this->handlerFor)('application/xml; charset=utf-8', '<urlset/>');

    $this->middleware->process($request, $handler);
    $hit = $this->middleware->process($request, $handler);

    expect($hit->getBody())->toBe('<urlset/>')
        ->and($hit->getHeader('Content-Type'))->toBe('application/xml; charset=utf-8');
});

/**
 * Replaying every stored header would hand one visitor's Set-Cookie to the next.
 * Only the content type is restored.
 */
test('a set-cookie header is never replayed from cache', function () {
    $request = new Request('/page', 'GET', [], [], [], [], [], []);

    $handler = new class implements RequestHandlerInterface
    {
        public function handle(Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Content-Type', 'text/html; charset=utf-8');
            $response->addHeader('Set-Cookie', 'session=secret-value');
            $response->setBody('<html></html>');

            return $response;
        }
    };

    $this->middleware->process($request, $handler);
    $hit = $this->middleware->process($request, $handler);

    expect($hit->getHeader('Set-Cookie'))->toBeNull();
});
