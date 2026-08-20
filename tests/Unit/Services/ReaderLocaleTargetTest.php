<?php

declare(strict_types=1);

use App\Services\LocaleRegistry;
use App\Services\ReaderLocaleTarget;

/**
 * A locale prefix means two different things depending on the page under it.
 *
 * On a post it is part of the link: someone shared that post in that language
 * and it has to stay pointing there. On the platform's own pages it is only a
 * record of what the last visitor was reading, which on a shared browser is the
 * previous person. These pin the line between the two.
 */
function makeReaderLocaleTarget(): ReaderLocaleTarget
{
    return new ReaderLocaleTarget(new LocaleRegistry(ROOT_PATH));
}

test('a platform page adopts the reader language', function (string $target, string $expected) {
    expect(makeReaderLocaleTarget()->resolve($target, 'en'))->toBe($expected);
})->with([
    ['/el', '/en'],
    ['/el/dashboard', '/en/dashboard'],
    ['/el/dashboard/account', '/en/dashboard/account'],
    ['/el/blogs', '/en/blogs'],
    ['/el/saved', '/en/saved'],
    ['/el/replies/mine', '/en/replies/mine'],
    ['/el/subscriptions', '/en/subscriptions'],
    ['/el/profile/someone', '/en/profile/someone'],
]);

/**
 * The reported bug: a guest browsing Greek leaves the login link carrying
 * return_to=/el, and the next person to sign in inherited it.
 */
test('the site root follows the reader rather than the previous visitor', function () {
    expect(makeReaderLocaleTarget()->resolve('/el', 'en'))->toBe('/en');
});

/**
 * Links are built with lurl(), which uses the locale of the page they are
 * rendered on. So the dashboard link in the masthead of an English post points
 * at /en/dashboard even for a reader whose interface is Greek. Pre-routing
 * sends it here to be corrected.
 */
test('a platform link built on a foreign-language content page is corrected', function () {
    expect(makeReaderLocaleTarget()->resolve('/en/dashboard', 'el'))->toBe('/el/dashboard');
});

test('a blog post keeps the language it was linked in', function () {
    expect(makeReaderLocaleTarget()->resolve('/el/blog/some-blog/a-post', 'en'))
        ->toBe('/el/blog/some-blog/a-post');
});

/**
 * Static pages resolve their row by locale exactly as posts do, so rewriting
 * the prefix would serve a different page or none at all.
 */
test('a static page keeps the language it was linked in', function (string $target) {
    expect(makeReaderLocaleTarget()->resolve($target, 'en'))->toBe($target);
})->with([
    '/el/about',
    '/el/getting-started',
    '/el/getting-started/start-your-first-blog',
]);

/**
 * Anything unrecognised has to keep its URL. A path this class was never taught
 * about is far more likely to be content than not, and a wrong guess there
 * breaks a shared link rather than merely showing the wrong interface language.
 */
test('an unrecognised path is left alone', function () {
    expect(makeReaderLocaleTarget()->resolve('/el/something-new', 'en'))
        ->toBe('/el/something-new');
});

test('an account with no stored language changes nothing', function () {
    expect(makeReaderLocaleTarget()->resolve('/el/dashboard', null))->toBe('/el/dashboard');
});

test('a language the platform no longer serves changes nothing', function () {
    expect(makeReaderLocaleTarget()->resolve('/el/dashboard', 'fr'))->toBe('/el/dashboard');
});

test('an unprefixed platform path gains the reader language', function () {
    expect(makeReaderLocaleTarget()->resolve('/dashboard', 'el'))->toBe('/el/dashboard');
});

/**
 * LocalePrefixIntake strips the query along with the prefix, which has already
 * cost this codebase a pagination bug once.
 */
test('the query string survives the rewrite', function () {
    expect(makeReaderLocaleTarget()->resolve('/el/blogs?page=2&sort=new', 'en'))
        ->toBe('/en/blogs?page=2&sort=new');
});

test('a target already in the reader language is unchanged', function () {
    expect(makeReaderLocaleTarget()->resolve('/en/dashboard', 'en'))->toBe('/en/dashboard');
});
