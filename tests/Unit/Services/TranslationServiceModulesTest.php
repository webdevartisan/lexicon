<?php

declare(strict_types=1);

use App\Services\TranslationService;

/**
 * Module text sits in modules/<key>/locales/<locale>.json and is read with <key>::path keys,
 * the same way theme text is.
 */
beforeEach(function () {
    $this->modules = ROOT_PATH.'/tests/Fixtures/modules';
});

test('a module key reads the module locale file', function () {
    expect((new TranslationService('el', '.', $this->modules))->translate('sample-extra::note.title'))
        ->toBe('Μια σημείωση');
});

test('a module string missing in the locale falls back to the module english', function () {
    expect((new TranslationService('el', '.', $this->modules))->translate('sample-extra::note.count', ['count' => 3]))
        ->toBe('3 notes');
});

test('theme keys still read the theme files', function () {
    expect((new TranslationService('en', '.', $this->modules))->translate('offset::nav.home'))->toBe('Home');
});

test('a name with a path in it finds nothing', function () {
    expect((new TranslationService('en', '.', $this->modules))->translate('../sample-extra::note.title'))
        ->toBe('../sample-extra::note.title');
});
