<?php

declare(strict_types=1);

/**
 * The cutover: the old dashboard settings routes and the two dead routes are
 * gone, the orphaned controllers are removed, and the account menu points at
 * the front. Old URLs are deleted outright, not redirected — the app is
 * pre-release and carries no external links worth preserving.
 */
$routes = file_get_contents(ROOT_PATH.'/config/routes.php');
$nav = file_get_contents(ROOT_PATH.'/views/partials/_auth_nav.lex.php');

test('the dead export and id-update routes are gone', function () use ($routes) {
    expect($routes)->not->toContain("'controller' => 'DataExport'");
    expect($routes)->not->toContain('/account/{id:\d+}/update');
    expect($routes)->not->toContain('/profile/{id:\d+}/update');
});

test('the old dashboard settings routes are gone', function () use ($routes) {
    expect($routes)->not->toContain("'/profile', ['controller' => 'ProfileController'");
    expect($routes)->not->toContain("'/account', ['controller' => 'AccountController'");
    expect($routes)->not->toContain("'/account/security', ['controller' => 'SecurityController'");
    expect($routes)->not->toContain("'/delete-account'");
});

test('the old dashboard settings controllers are deleted', function () {
    expect(file_exists(ROOT_PATH.'/src/App/Controllers/Dashboard/ProfileController.php'))->toBeFalse();
    expect(file_exists(ROOT_PATH.'/src/App/Controllers/Dashboard/AccountController.php'))->toBeFalse();
    expect(file_exists(ROOT_PATH.'/src/App/Controllers/Dashboard/SecurityController.php'))->toBeFalse();
    expect(file_exists(ROOT_PATH.'/src/App/Controllers/Dashboard/AccountDeletionController.php'))->toBeFalse();
});

test('the account menu points at the front', function () use ($nav) {
    expect($nav)->toContain("lurl('/account/profile')");
    expect($nav)->not->toContain("lurl('/dashboard/profile')");
    expect($nav)->toContain('navigation.account');
});
