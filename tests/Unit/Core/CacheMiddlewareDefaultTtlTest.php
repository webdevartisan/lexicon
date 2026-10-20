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
 * resolveTtl fell back to a hard-coded 600, so GET routes with no rule were
 * cached although config default_ttl is 0.
 */
beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir().'/lexicon-cache-'.bin2hex(random_bytes(4));
    mkdir($this->cacheDir, 0777, true);

    $this->cache = new CacheService($this->cacheDir, true);

    $this->auth = new class() implements AuthInterface
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

    // Counts controller runs: a second run on an identical request means the cache did not answer.
    $this->handler = new class() implements RequestHandlerInterface
    {
        public int $calls = 0;

        public function handle(Request $request): Response
        {
            $this->calls++;

            $response = new Response();
            $response->addHeader('Content-Type', 'text/html; charset=utf-8');
            $response->setBody('<html>page</html>');

            return $response;
        }
    };

    $this->build = fn (array $rules, int $defaultTtl = 0) => new CacheMiddleware(
        $this->cache,
        new CacheKey(),
        $this->auth,
        $rules,
        false,
        null,
        $defaultTtl
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

test('a route with no rule is not cached when the default ttl is zero', function () {
    $middleware = ($this->build)(['/about' => 3600]);
    $request = new Request('/report-reasons', 'GET', [], [], [], [], [], []);

    $middleware->process($request, $this->handler);
    $middleware->process($request, $this->handler);

    expect($this->handler->calls)->toBe(2);
});

test('a route with a rule is cached', function () {
    $middleware = ($this->build)(['/about' => 3600]);
    $request = new Request('/about', 'GET', [], [], [], [], [], []);

    $middleware->process($request, $this->handler);
    $middleware->process($request, $this->handler);

    expect($this->handler->calls)->toBe(1);
});

test('a positive default ttl still caches unlisted routes', function () {
    $middleware = ($this->build)([], 300);
    $request = new Request('/anything', 'GET', [], [], [], [], [], []);

    $middleware->process($request, $this->handler);
    $middleware->process($request, $this->handler);

    expect($this->handler->calls)->toBe(1);
});

test('the shipped config does not cache the routes the audit listed', function () {
    $config = require ROOT_PATH.'/config/cache.php';
    $middleware = ($this->build)($config['ttl_rules'], (int) $config['default_ttl']);

    foreach (['/report-reasons', '/auth/nav', '/robots.txt'] as $uri) {
        $request = new Request($uri, 'GET', [], [], [], [], [], []);
        $middleware->process($request, $this->handler);
        $middleware->process($request, $this->handler);
    }

    expect($this->handler->calls)->toBe(6);
});

test('a modal fetch neither reads nor fills the page cache', function () {
    $middleware = ($this->build)(['/about' => 3600]);
    $page = new Request('/about', 'GET', [], [], [], [], [], []);
    $panel = new Request('/about', 'GET', [], [], [], [], [], ['x-panel' => '1']);

    $middleware->process($panel, $this->handler);
    $middleware->process($page, $this->handler);
    $middleware->process($panel, $this->handler);

    expect($this->handler->calls)->toBe(3);
});
