<?php

declare(strict_types=1);

use App\Models\UserPreferencesModel;
use App\Services\LocaleRegistry;
use App\Services\UserLocalePreference;

/**
 * The signed-in reader's interface language.
 *
 * The stored value is user input that was valid when it was saved, so every read
 * revalidates rather than trusting the column: spec 1 dropped fr while rows
 * referencing it survived, and the same can happen to a saved account preference.
 */
afterEach(function () {
    Mockery::close();
});

function makeLocalePreference(?UserPreferencesModel $prefs = null): UserLocalePreference
{
    return new UserLocalePreference(
        new LocaleRegistry(ROOT_PATH),
        $prefs ?? Mockery::mock(UserPreferencesModel::class)
    );
}

test('a guest has no preference and is never looked up', function () {
    $prefs = Mockery::mock(UserPreferencesModel::class);
    $prefs->shouldNotReceive('getLocale');

    expect(makeLocalePreference($prefs)->resolve(null))->toBeNull();
});

test('a supported stored locale is returned', function () {
    $prefs = Mockery::mock(UserPreferencesModel::class);
    $prefs->shouldReceive('getLocale')->with(7)->andReturn('el');

    expect(makeLocalePreference($prefs)->resolve(7))->toBe('el');
});

test('an unset preference reads as null', function () {
    $prefs = Mockery::mock(UserPreferencesModel::class);
    $prefs->shouldReceive('getLocale')->with(7)->andReturn(null);

    expect(makeLocalePreference($prefs)->resolve(7))->toBeNull();
});

/**
 * Letting an unsupported code through would hand LocaleContext a chrome locale
 * with no strings file behind it, and every interface string would render raw.
 */
test('a locale that is no longer supported is ignored', function () {
    $prefs = Mockery::mock(UserPreferencesModel::class);
    $prefs->shouldReceive('getLocale')->with(7)->andReturn('fr');

    expect(makeLocalePreference($prefs)->resolve(7))->toBeNull();
});

test('a stored value is normalised before it is trusted', function () {
    $prefs = Mockery::mock(UserPreferencesModel::class);
    $prefs->shouldReceive('getLocale')->with(7)->andReturn('  AR ');

    expect(makeLocalePreference($prefs)->resolve(7))->toBe('ar');
});
