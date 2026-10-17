<?php

declare(strict_types=1);

use Framework\Cache\CacheKey;
use Framework\Core\Request;

/**
 * The whitelist was an exact-path lookup, so the wildcard entries never applied
 * and unlisted paths accepted every parameter, letting ?x=1, ?x=2 and so on mint
 * a cache file each.
 */
beforeEach(function () {
    $_SESSION['locale'] = 'en';

    $this->keyFor = function (CacheKey $generator, string $uri, array $get): string {
        return $generator->forRequest(new Request($uri, 'GET', $get, [], [], [], [], []));
    };
});

afterEach(function () {
    unset($_SESSION['locale']);
});

test('a wildcard whitelist entry applies to matching paths', function () {
    $generator = new CacheKey(['/blog/*' => ['page']]);

    $key = ($this->keyFor)($generator, '/blog/my-blog', ['page' => '2', 'utm_source' => 'x']);

    expect($key)->toBe('en:GET:/blog/my-blog?page=2');
});

test('a path with no whitelist entry is keyed without any query parameters', function () {
    $generator = new CacheKey(['/discover' => ['page']]);

    $plain = ($this->keyFor)($generator, '/about', []);
    $flooded = ($this->keyFor)($generator, '/about', ['x' => '1', 'y' => '2']);

    expect($flooded)->toBe($plain);
});

test('the first matching pattern wins so specific entries must precede broad ones', function () {
    $generator = new CacheKey([
        '/blog/*/archive' => ['page'],
        '/blog/*' => ['page', 'comments'],
    ]);

    $archive = ($this->keyFor)($generator, '/blog/b/archive', ['page' => '2', 'comments' => 'new']);
    $landing = ($this->keyFor)($generator, '/blog/b', ['page' => '2', 'comments' => 'new']);

    expect($archive)->toBe('en:GET:/blog/b/archive?page=2')
        ->and($landing)->toBe('en:GET:/blog/b?comments=new&page=2');
});

test('the shipped config keeps the category filter on the blog landing page', function () {
    $config = require ROOT_PATH.'/config/cache.php';
    $generator = new CacheKey($config['query_whitelist']);

    $all = ($this->keyFor)($generator, '/blog/b', []);
    $filtered = ($this->keyFor)($generator, '/blog/b', ['category' => 'news']);

    expect($filtered)->not->toBe($all);
});
