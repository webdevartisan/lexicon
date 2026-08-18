<?php

declare(strict_types=1);

use App\Models\UserPreferencesModel;
use App\Services\LocaleRegistry;
use App\Services\SessionLocaleSync;
use App\Services\UserLocalePreference;

/**
 * The session locale outlives the guest who created it.
 *
 * Signing in used to leave it pointing at whatever language the visitor had been
 * browsing, so an account stored as Greek got a Greek interface at /en/ URLs and
 * never recovered: the session sits ahead of the preference in the resolution
 * chain, and LocaleMiddleware writes it straight back on every request.
 */
beforeEach(function () {
    $_SESSION['locale'] = 'en';
    $_SERVER['REQUEST_METHOD'] = 'POST';
});

afterEach(function () {
    unset($_SESSION['locale'], $_SERVER['REQUEST_METHOD']);
    Mockery::close();
});

function makeSessionLocaleSync(?string $stored): SessionLocaleSync
{
    $prefs = Mockery::mock(UserPreferencesModel::class);
    $prefs->shouldReceive('getLocale')->with(7)->andReturn($stored);

    return new SessionLocaleSync(
        new UserLocalePreference(new LocaleRegistry(ROOT_PATH), $prefs)
    );
}

test('a stored language replaces the locale the guest was browsing', function () {
    makeSessionLocaleSync('el')->apply(7);

    expect($_SESSION['locale'])->toBe('el');
});

/**
 * The caller builds the post-login redirect from this, so handing the adopted
 * language back saves asking the database the same question twice.
 */
test('apply reports the language it adopted', function () {
    expect(makeSessionLocaleSync('el')->apply(7))->toBe('el');
});

test('apply reports null when the account has no language', function () {
    expect(makeSessionLocaleSync(null)->apply(7))->toBeNull();
});

/**
 * The shared browser again, in reverse: without this the next visitor starts
 * out in the language of the account that just signed out.
 */
test('forget drops the language when the reader signs out', function () {
    makeSessionLocaleSync('el')->forget();

    expect($_SESSION)->not->toHaveKey('locale');
});

/**
 * "auto" is the absence of a preference. Clobbering the session for it would
 * pin the interface to the default instead of letting it follow the URL.
 */
test('an account set to auto leaves the session alone', function () {
    makeSessionLocaleSync(null)->apply(7);

    expect($_SESSION['locale'])->toBe('en');
});

test('a stored language the platform no longer serves is ignored', function () {
    makeSessionLocaleSync('fr')->apply(7);

    expect($_SESSION['locale'])->toBe('en');
});

/**
 * The regression itself. AuthController redirects to a bare '/dashboard' when
 * there is no return_to, and Response::redirect() localizes it from the session,
 * which is the single step that used to hand back the guest's language.
 */
test('the post-login redirect target follows the account, not the guest', function () {
    expect(buildLocalizedUrl('/dashboard', true))->toBe('/en/dashboard');

    makeSessionLocaleSync('el')->apply(7);

    expect(buildLocalizedUrl('/dashboard', true))->toBe('/el/dashboard');
});

/**
 * A reader who signed in from an English post still lands back on that post.
 * The prefix in the URL is an explicit request for that language and outranks
 * the preference, which only ever governs the interface around it.
 */
test('an already prefixed return_to is not rewritten by the sync', function () {
    makeSessionLocaleSync('el')->apply(7);

    expect(buildLocalizedUrl('/en/blog/some-post', true))->toBe('/en/blog/some-post');
});
