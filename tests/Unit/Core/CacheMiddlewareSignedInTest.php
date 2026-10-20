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
 * The user menu prints the signed-in person's name and email on every page,
 * cached public pages included. A signed-in render must never reach the cache.
 */
beforeEach(function () {
    $this->cacheDir = sys_get_temp_dir().'/lexicon-cache-'.bin2hex(random_bytes(4));
    mkdir($this->cacheDir, 0777, true);

    $this->auth = new class() implements AuthInterface
    {
        public bool $signedIn = true;

        public function check(): bool
        {
            return $this->signedIn;
        }

        public function user(): ?array
        {
            return $this->signedIn ? ['id' => 7, 'email' => 'walker@example.test'] : null;
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

    $auth = $this->auth;
    $this->handler = new class($auth) implements RequestHandlerInterface
    {
        public function __construct(private AuthInterface $auth) {}

        public function handle(Request $request): Response
        {
            $response = new Response();
            $response->addHeader('Content-Type', 'text/html; charset=utf-8');
            $response->setBody($this->auth->check() ? '<html>menu for walker@example.test</html>' : '<html>sign in</html>');

            return $response;
        }
    };

    $this->middleware = new CacheMiddleware(new CacheService($this->cacheDir, true), new CacheKey(), $this->auth, ['/about' => 3600], false, null, 0);
});

afterEach(function () {
    foreach (glob($this->cacheDir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->cacheDir);
});

test('a guest never gets the page a signed-in person was shown', function () {
    $request = new Request('/about', 'GET', [], [], [], [], [], []);

    $this->middleware->process($request, $this->handler);

    $this->auth->signedIn = false;
    $guest = $this->middleware->process($request, $this->handler);

    expect($guest->getBody())->toBe('<html>sign in</html>');
    expect($guest->getBody())->not->toContain('walker@example.test');
});
