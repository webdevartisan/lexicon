<?php

declare(strict_types=1);

use App\Services\LocaleState;
use App\ValueObjects\LocaleContext;

/**
 * Chrome language and content language were one value for years, which is how a
 * locale prefix could put dir="rtl" and lang="el" on a post written in English.
 * These pin them as two separate readings.
 */
afterEach(function () {
    LocaleState::reset();
});

test('the context returns both dimensions independently', function () {
    LocaleState::set(new LocaleContext('en', 'el'));

    expect(LocaleState::get()->contentLocale)->toBe('en')
        ->and(LocaleState::get()->chromeLocale)->toBe('el');
});

test('a guest context has chrome following content', function () {
    LocaleState::set(LocaleContext::forGuest('el'));

    expect(LocaleState::get()->contentLocale)->toBe('el')
        ->and(LocaleState::get()->chromeLocale)->toBe('el');
});

/**
 * Console commands, queued jobs and tests never run pre-routing, so reading
 * before anything is set has to give a usable answer rather than blow up.
 */
test('reading before anything is set falls back to the registry default', function () {
    expect(LocaleState::get()->contentLocale)->toBe('en')
        ->and(LocaleState::get()->chromeLocale)->toBe('en');
});

test('reset clears the context between requests', function () {
    LocaleState::set(new LocaleContext('el', 'ar'));
    LocaleState::reset();

    expect(LocaleState::get()->contentLocale)->toBe('en');
});

test('a locale set can be recorded and read back', function () {
    LocaleState::setLocaleSet(['EN', 'el', 'en']);

    expect(LocaleState::localeSet())->toBe(['en', 'el']);
});

/**
 * An empty set means "no page-specific knowledge", which downstream reads as
 * every supported locale. It must not survive into the next request.
 */
test('reset clears the locale set too', function () {
    LocaleState::setLocaleSet(['en', 'el']);
    LocaleState::reset();

    expect(LocaleState::localeSet())->toBe([]);
});
