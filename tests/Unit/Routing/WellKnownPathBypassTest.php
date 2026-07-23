<?php

declare(strict_types=1);

use App\Http\PreRouting\WellKnownPathBypass;

/**
 * Crawlers and user agents fetch these paths at the origin root by
 * specification. Prefixing them meant /robots.txt redirected to
 * /en/robots.txt, which had no route behind it, so the site advertised no
 * robots.txt at all.
 */
test('protocol endpoints are recognised', function (string $path) {
    expect(WellKnownPathBypass::isWellKnown($path))->toBeTrue();
})->with([
    '/robots.txt',
    '/sitemap.xml',
    '/favicon.ico',
    '/.well-known/security.txt',
    '/.well-known/acme-challenge/token123',
]);

test('matching is case insensitive', function () {
    expect(WellKnownPathBypass::isWellKnown('/Robots.TXT'))->toBeTrue();
});

/**
 * The prefix check must be anchored. A path that merely contains one of these
 * names is an ordinary page and still belongs in the locale space.
 */
test('ordinary pages are not treated as protocol endpoints', function (string $path) {
    expect(WellKnownPathBypass::isWellKnown($path))->toBeFalse();
})->with([
    '/',
    '/blogs',
    '/en/robots.txt',
    '/blog/robots.txt',
    '/robots.txt.php',
    '/well-known/thing',
    '/blog/.well-known/x',
]);
