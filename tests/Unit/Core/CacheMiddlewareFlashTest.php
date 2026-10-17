<?php

declare(strict_types=1);

use Framework\Cache\CacheKey;
use Framework\Cache\CacheMiddleware;
use Framework\Cache\CacheService;
use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\AuthInterface;
use Framework\Interfaces\RequestHandlerInterface;
use Framework\Session;

/**
 * The layout consumes flash messages while rendering, so a page rendered for a
 * guest with a message pending must never be stored for the next guest.
 */
beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir().'/lexicon-cache-'.bin2hex(random_bytes(4));
    mkdir($this->cacheDir, 0777, true);

    $auth = new class() implements AuthInterface
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

    $this->middleware = new CacheMiddleware(
        new CacheService($this->cacheDir, true),
        new CacheKey(),
        $auth,
        ['/*' => 600],
        false,
        new Session()
    );

    // Counts handler runs, so a second request proves whether the cache answered.
    $this->handler = new class() implements RequestHandlerInterface
    {
        public int $runs = 0;

        public function handle(Request $request): Response
        {
            $this->runs++;
            // The real layout consumes pending messages while rendering.
            unset($_SESSION['_flash'], $_SESSION['_errors']);
            $response = new Response();
            $response->addHeader('Content-Type', 'text/html; charset=utf-8');
            $response->setBody('<html>page</html>');

            return $response;
        }
    };
});

afterEach(function () {
    unset($_SESSION['_flash'], $_SESSION['_errors']);

    foreach (glob($this->cacheDir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->cacheDir);
});

test('a page rendered with a pending flash is not stored', function () {
    $request = new Request('/', 'GET', [], [], [], [], [], []);
    $_SESSION['_flash'] = ['success' => ['You have been unsubscribed.']];

    $this->middleware->process($request, $this->handler);
    $this->middleware->process($request, $this->handler);

    expect($this->handler->runs)->toBe(2);
});

test('a page rendered with pending validation errors is not stored', function () {
    $request = new Request('/', 'GET', [], [], [], [], [], []);
    $_SESSION['_errors'] = ['email' => ['Invalid email']];

    $this->middleware->process($request, $this->handler);
    $this->middleware->process($request, $this->handler);

    expect($this->handler->runs)->toBe(2);
});

test('a guest with a pending flash is not served a cached page', function () {
    $request = new Request('/', 'GET', [], [], [], [], [], []);

    $this->middleware->process($request, $this->handler);
    $_SESSION['_flash'] = ['success' => ['Saved.']];
    $this->middleware->process($request, $this->handler);

    expect($this->handler->runs)->toBe(2);
});

test('a response that showed a flash is private to the browser', function () {
    $request = new Request('/', 'GET', [], [], [], [], [], []);
    $_SESSION['_flash'] = ['success' => ['Saved.']];

    $response = $this->middleware->process($request, $this->handler);

    expect($response->getHeader('Cache-Control'))->toContain('private')
        ->and($response->getHeader('Cache-Control'))->not->toContain('public');
});

test('a clean guest request is still cached', function () {
    $request = new Request('/', 'GET', [], [], [], [], [], []);

    $this->middleware->process($request, $this->handler);
    $this->middleware->process($request, $this->handler);

    expect($this->handler->runs)->toBe(1);
});
