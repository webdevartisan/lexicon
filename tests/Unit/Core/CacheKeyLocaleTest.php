<?php

declare(strict_types=1);

use Framework\Cache\CacheKey;
use Framework\Core\Request;

/**
 * The locale segment in the cache key looks redundant now that every URL carries
 * a locale prefix, and deleting it is the obvious cleanup. It is not safe.
 *
 * LocalePrefixIntake strips the prefix from Request::$uri before routing, so by
 * the time CacheKey sees the URI the language is already gone. Drop the segment
 * and every locale collapses onto one entry, serving Greek readers English.
 */
afterEach(function () {
    unset($_SESSION['locale']);
});

test('two locales of the same stripped path get different keys', function () {
    $request = new Request('/blogs', 'GET', [], [], [], [], [], []);
    $generator = new CacheKey();

    $_SESSION['locale'] = 'en';
    $english = $generator->forRequest($request);

    $_SESSION['locale'] = 'el';
    $greek = $generator->forRequest($request);

    expect($english)->not->toBe($greek);
});

test('the static builder keeps the same guarantee', function () {
    expect(CacheKey::for('/blogs', [], 'en'))
        ->not->toBe(CacheKey::for('/blogs', [], 'el'));
});

/**
 * The stripped URI carries no language at all, which is the whole reason the
 * segment has to stay.
 */
test('the request uri a controller sees carries no locale', function () {
    $request = new Request('/blogs', 'GET', [], [], [], [], [], []);

    expect($request->uri)->not->toContain('/en')
        ->and($request->uri)->not->toContain('/el');
});
