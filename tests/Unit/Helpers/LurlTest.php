<?php

declare(strict_types=1);

use App\Services\LocaleState;
use App\ValueObjects\LocaleContext;

/**
 * Components such as btn, row-actions and the breadcrumbs localize whatever
 * href they are given, so lurl() has to leave an already-localized or
 * absolute URL alone instead of producing /en/en/... or /en/https://...
 */
beforeEach(function () {
    LocaleState::set(LocaleContext::forGuest('el'));
});

afterEach(function () {
    LocaleState::reset();
});

test('prefixes a bare path with the content locale', function () {
    expect(lurl('/admin/posts'))->toBe('/el/admin/posts')
        ->and(lurl('dashboard/post?tag=3'))->toBe('/el/dashboard/post?tag=3')
        ->and(lurl('/'))->toBe('/el/');
});

test('an explicit locale wins over the current one', function () {
    expect(lurl('/about', 'en'))->toBe('/en/about');
});

test('leaves a path that already carries a supported locale unchanged', function () {
    expect(lurl('/en/admin/posts'))->toBe('/en/admin/posts')
        ->and(lurl(lurl('/dashboard')))->toBe('/el/dashboard')
        ->and(lurl('/en?x=1'))->toBe('/en?x=1');
});

test('still prefixes a path whose first segment only looks like a locale', function () {
    expect(lurl('/xx/thing'))->toBe('/el/xx/thing');
});

test('leaves absolute URLs and fragments unchanged', function () {
    expect(lurl('https://example.com/a'))->toBe('https://example.com/a')
        ->and(lurl('//cdn.example.com/a'))->toBe('//cdn.example.com/a')
        ->and(lurl('mailto:a@example.com'))->toBe('mailto:a@example.com')
        ->and(lurl('#comments'))->toBe('#comments');
});
