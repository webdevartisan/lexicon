<?php

declare(strict_types=1);

use App\Services\LocaleState;
use App\ValueObjects\LocaleContext;

/**
 * site_content() used to read locale(), the content locale from the URL. That
 * put it out of step with $t(), which reads the chrome locale, even though
 * both fill the same role: the platform's own interface and marketing copy,
 * not authored content a URL points at. A signed-in reader whose account
 * language differed from the URL they landed on saw the nav in one language
 * and the homepage hero/FAQ copy site_content() renders in another.
 */
afterEach(function () {
    LocaleState::reset();
});

test('site_content follows the chrome locale, not the URL locale', function () {
    LocaleState::set(new LocaleContext('el', 'en'));

    expect(site_content('banner.title'))->toBe('Your words deserve a good home.');
});

test('a guest, whose two locales are equal, still gets the right language', function () {
    LocaleState::set(LocaleContext::forGuest('el'));

    expect(site_content('banner.title'))->toBe('Τα λόγια σου αξίζουν ένα όμορφο σπίτι.');
});
