<?php

declare(strict_types=1);

use App\Services\HeadI18nBuilder;
use App\Services\LocaleRegistry;
use App\Services\LocaleState;
use App\ValueObjects\LocaleContext;

/**
 * These globals decide lang, dir, canonical and hreflang. Deriving them from the
 * URL rather than the content is what put dir="rtl" on English posts under /ar/
 * and advertised five translations of a post that had one.
 */
beforeEach(function () {
    $_SERVER['HTTP_HOST'] = 'lexicon.test';
    $this->builder = new HeadI18nBuilder(new LocaleRegistry(ROOT_PATH));
});

afterEach(function () {
    LocaleState::reset();
});

test('lang comes from the content locale, not the chrome locale', function () {
    LocaleState::set(new LocaleContext('en', 'ar'));

    $globals = $this->builder->build('/blog/some-blog/post');

    expect($globals['currentLang'])->toBe('en')
        ->and($globals['isRtl'])->toBe('');
});

/**
 * The bug this prevents: an English post under /ar/ had its layout flipped.
 */
test('direction follows the content, so english never renders rtl', function () {
    LocaleState::set(new LocaleContext('en', 'ar'));

    expect($this->builder->build('/about')['isRtl'])->toBe('');
});

test('an rtl content locale does set the direction', function () {
    LocaleState::set(LocaleContext::forGuest('ar'));

    expect($this->builder->build('/about')['isRtl'])->toBe('dir="rtl"');
});

test('a single-locale page emits no hreflang alternates', function () {
    LocaleState::set(LocaleContext::forGuest('en'));
    LocaleState::setLocaleSet(['en']);

    expect($this->builder->build('/blog/mono/post')['head']['alternates'])->toBe([]);
});

test('a multi-locale page emits one alternate per real locale', function () {
    LocaleState::set(LocaleContext::forGuest('en'));
    LocaleState::setLocaleSet(['en', 'el']);

    $alternates = $this->builder->build('/blog/multi/post')['head']['alternates'];

    expect($alternates)->toHaveCount(2)
        ->and(array_column($alternates, 'hreflang'))->toBe(['en', 'el']);
});

test('locales outside the page set never appear as alternates', function () {
    LocaleState::set(LocaleContext::forGuest('en'));
    LocaleState::setLocaleSet(['en', 'el']);

    $alternates = $this->builder->build('/blog/multi/post')['head']['alternates'];

    expect(array_column($alternates, 'hreflang'))->not->toContain('ar');
});

/**
 * A page that never declares its languages is chrome-only, such as the home
 * page, and genuinely does exist in every locale that has strings.
 */
test('an undeclared page falls back to every supported locale', function () {
    LocaleState::set(LocaleContext::forGuest('en'));

    $globals = $this->builder->build('/');

    expect(array_column($globals['head']['alternates'], 'hreflang'))
        ->toBe((new LocaleRegistry(ROOT_PATH))->supported());
});

test('canonical points at the current content locale and keeps the query', function () {
    LocaleState::set(LocaleContext::forGuest('el'));
    LocaleState::setLocaleSet(['en', 'el']);

    expect($this->builder->build('/blog/x/archive', 'page=3')['head']['canonicalUrl'])
        ->toBe('http://lexicon.test/el/blog/x/archive?page=3');
});

/**
 * x-default should name the page's own base language, not the platform's, or a
 * Greek-first blog would point search engines at a URL that redirects away.
 */
test('x-default uses the page default when the platform default is absent', function () {
    LocaleState::set(LocaleContext::forGuest('el'));
    LocaleState::setLocaleSet(['el']);

    expect($this->builder->build('/blog/greek-blog')['head']['xDefaultUrl'])
        ->toBe('http://lexicon.test/el/blog/greek-blog');
});

test('the og locale is region qualified and excludes the current one', function () {
    LocaleState::set(LocaleContext::forGuest('en'));
    LocaleState::setLocaleSet(['en', 'el']);

    $head = $this->builder->build('/x')['head'];

    expect($head['ogLocale'])->toBe('en_US')
        ->and($head['ogLocaleAlternates'])->toBe(['el_GR']);
});
