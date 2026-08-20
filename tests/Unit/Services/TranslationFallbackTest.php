<?php

declare(strict_types=1);

use App\Services\TranslationService;

/**
 * translate() used to return the raw key on a miss, which a Greek reader sees
 * as "account.changePassword" on the page. It now falls back to the English
 * string, and only returns the raw key when English misses too.
 */
test('returns the active-locale string when present', function () {
    $svc = new TranslationService('en');
    // Any key known to exist in en.json.
    expect($svc->translate('header.signIn'))->not->toBe('header.signIn');
});

test('falls back to English when the active locale misses a key', function () {
    $en = json_decode(file_get_contents(ROOT_PATH.'/locales/en.json'), true);
    $expected = $en['header.signIn'] ?? $en['header']['signIn'] ?? null;
    expect($expected)->not->toBeNull();

    $svc = new TranslationService('el');
    $reflect = new ReflectionClass(TranslationService::class);
    $active = $reflect->getProperty('translations');
    $active->setAccessible(true);
    $active->setValue($svc, []); // force every active-locale lookup to miss

    expect($svc->translate('header.signIn'))->toBe($expected);
});

test('an entirely unknown key still returns the raw key', function () {
    $svc = new TranslationService('en');
    expect($svc->translate('this.key.does.not.exist.anywhere'))
        ->toBe('this.key.does.not.exist.anywhere');
});
