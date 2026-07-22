<?php

declare(strict_types=1);

use App\Gate;
use App\Resources\SystemResource;
use Framework\Exceptions\UnauthorizedException;

/**
 * Admin Area Authorization Invariant Suite
 *
 * Every admin controller is gated by a single line in
 * AppController::beforeAction(): if $areaAbility is set, Gate::authorize() runs
 * before the action does. That makes $areaAbility the whole admin authorization
 * story, and an unset one silently opens the area to anyone who can reach the
 * URL. These tests exist so adding a new admin controller without an ability
 * fails the suite rather than shipping.
 */

/**
 * @return list<array{string, string}> [short class name, declared ability]
 */
function adminControllerAbilities(): array
{
    $out = [];

    foreach (glob(ROOT_PATH.'/src/App/Controllers/Admin/*Controller.php') ?: [] as $file) {
        $short = basename($file, '.php');
        $class = 'App\\Controllers\\Admin\\'.$short;

        if (!class_exists($class)) {
            continue;
        }

        $property = (new ReflectionClass($class))->getProperty('areaAbility');
        $property->setAccessible(true);

        $out[] = [$short, $property->getDefaultValue()];
    }

    return $out;
}

test('every admin controller declares an area ability', function () {
    $controllers = adminControllerAbilities();

    expect($controllers)->not->toBeEmpty();

    $undeclared = array_values(array_map(
        static fn (array $row): string => $row[0],
        array_filter($controllers, static fn (array $row): bool => $row[1] === null)
    ));

    expect($undeclared)->toBe([], 'Admin controllers with no $areaAbility: '.implode(', ', $undeclared));
});

test('no admin ability is reachable by an unauthenticated visitor', function () {
    // beforeAction() passes `auth()->user() ?? []`, so a guest arrives as [].
    foreach (adminControllerAbilities() as [$controller, $ability]) {
        if ($ability === null) {
            continue;
        }

        expect(Gate::allows($ability, SystemResource::class, []))
            ->toBeFalse("Guest was allowed '{$ability}' (via {$controller})");
    }
});

test('Gate::authorize throws UnauthorizedException for a guest', function () {
    $abilities = array_values(array_filter(array_map(
        static fn (array $row) => $row[1],
        adminControllerAbilities()
    )));

    expect($abilities)->not->toBeEmpty();

    foreach ($abilities as $ability) {
        expect(fn () => Gate::authorize($ability, SystemResource::class, []))
            ->toThrow(UnauthorizedException::class);
    }
});

test('every declared ability is actually implemented by the system policy', function () {
    // Gate::allows() throws a bare Exception for an unknown action, which would
    // surface as a 500 rather than a 403 -- a typo'd ability must not do that.
    foreach (adminControllerAbilities() as [$controller, $ability]) {
        if ($ability === null) {
            continue;
        }

        expect(fn () => Gate::allows($ability, SystemResource::class, []))
            ->not->toThrow(Exception::class, "Policy does not support action: {$ability}");
    }
});
