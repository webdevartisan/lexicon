<?php

declare(strict_types=1);

use App\Services\ContentLocaleResolver;
use App\Services\LocaleRegistry;

/**
 * A page that exists in one language should resolve at exactly one URL. Without
 * this, /el/blog/english-blog/post served byte-identical HTML to the English
 * URL, with a wrong lang attribute and a false set of hreflang alternates.
 */
beforeEach(function () {
    $this->resolver = new ContentLocaleResolver(new LocaleRegistry(ROOT_PATH));
});

test('a requested locale inside the set is served as-is', function () {
    expect($this->resolver->redirectTarget(['en', 'el'], 'el', 'en'))->toBeNull();
});

test('a requested locale outside the set redirects to the page default', function () {
    expect($this->resolver->redirectTarget(['en'], 'el', 'en'))->toBe('en');
});

test('a single-locale set redirects every other locale to it', function (string $requested) {
    expect($this->resolver->redirectTarget(['el'], $requested, 'el'))->toBe('el');
})->with(['en', 'ar']);

/**
 * A blog whose default_locale was never updated after that locale was dropped
 * must still resolve somewhere real rather than redirect into nothing.
 */
test('a default outside its own set falls back to the first member', function () {
    expect($this->resolver->redirectTarget(['el'], 'en', 'fr'))->toBe('el');
});

test('locales the platform does not support are ignored in the set', function () {
    // fr is configured nowhere now, so a blog still claiming it must not become
    // a redirect target.
    expect($this->resolver->redirectTarget(['en', 'fr'], 'fr', 'en'))->toBe('en');
});

/**
 * No knowledge about a page's languages is not the same as knowing it has none.
 * Inventing a redirect from an empty set would break pages that never declare.
 */
test('an empty set serves the request rather than redirecting', function () {
    expect($this->resolver->redirectTarget([], 'el', 'en'))->toBeNull();
});

test('a set of only unsupported locales also serves rather than redirects', function () {
    expect($this->resolver->redirectTarget(['fr', 'de'], 'en', 'fr'))->toBeNull();
});

test('casing and stray whitespace are normalised on every input', function () {
    expect($this->resolver->redirectTarget([' EN ', 'EL'], 'El', 'EN'))->toBeNull();
});

test('duplicate entries in the set do not change the outcome', function () {
    expect($this->resolver->redirectTarget(['en', 'en', 'EN'], 'el', 'en'))->toBe('en');
});
