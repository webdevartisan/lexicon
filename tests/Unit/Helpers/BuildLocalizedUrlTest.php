<?php

declare(strict_types=1);

/**
 * buildLocalizedUrl carried the same bug LocalizeAnchorHrefs was fixed for: a
 * generic [a-z]{2} guard treated any two-letter first segment as a locale, so a
 * route like /go/... silently stopped being localized.
 */
beforeEach(function () {
    $_SESSION['locale'] = 'en';
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    unset($_SESSION['locale'], $_SERVER['REQUEST_METHOD']);
});

test('an unprefixed path gains the current locale', function () {
    expect(buildLocalizedUrl('/dashboard'))->toBe('/en/dashboard');
});

test('a path already carrying a supported locale is left alone', function () {
    expect(buildLocalizedUrl('/el/dashboard'))->toBe('/el/dashboard');
});

test('a two-letter segment that is not a supported locale still gets localized', function (string $path) {
    expect(buildLocalizedUrl('/'.$path.'/thing'))->toBe('/en/'.$path.'/thing');
})->with(['go', 'my', 'ui', 'qa', 'fr', 'de']);

test('absolute urls are returned untouched', function () {
    expect(buildLocalizedUrl('https://example.com/x'))->toBe('https://example.com/x');
});

test('unsafe methods skip localization unless forced', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';

    expect(buildLocalizedUrl('/comments'))->toBe('/comments')
        ->and(buildLocalizedUrl('/comments', true))->toBe('/en/comments');
});
