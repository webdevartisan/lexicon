<?php

declare(strict_types=1);

use Framework\Modules\InvalidManifestException;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;
use Tests\Helpers\ModuleFixtures;

const FIXTURE_MODULES = ModuleFixtures::PATH;

/**
 * A manifest as JSON, with any field swapped out for the case under test.
 */
function manifestJson(array $overrides = []): string
{
    return json_encode(array_merge([
        'key' => 'sample-extra',
        'name' => 'Sample extra',
        'description' => 'Test module',
        'version' => 1,
        'scope' => 'blog',
        'class' => 'Tests\\Fixtures\\Modules\\SampleExtra\\SampleExtraModule',
        'slots' => [],
        'tables' => [],
        'requires' => [],
    ], $overrides));
}

afterEach(fn () => ModuleFixtures::cleanUp());

test('reads a valid manifest', function () {
    $m = ModuleManifest::fromFile(FIXTURE_MODULES.'/sample-extra/module.json');

    expect($m->key)->toBe('sample-extra')
        ->and($m->scope)->toBe('blog')
        ->and($m->version)->toBe(1)
        ->and($m->slots)->toBe(['post.after-content'])
        ->and($m->tables)->toBe(['sample_extra_notes'])
        ->and($m->path)->toBe(FIXTURE_MODULES.'/sample-extra');
});

test('rejects a key that is not kebab case', function () {
    expect(fn () => ModuleManifest::fromJson(manifestJson(['key' => 'Bad_Key']), '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('rejects a scope other than blog or platform', function () {
    expect(fn () => ModuleManifest::fromJson(manifestJson(['scope' => 'site']), '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('rejects a slot core does not offer', function () {
    expect(fn () => ModuleManifest::fromJson(manifestJson(['slots' => ['footer.anything']]), '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('rejects requires that are not empty in v1', function () {
    expect(fn () => ModuleManifest::fromJson(manifestJson(['requires' => ['other']]), '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('a manifest says whether the module has its own page', function () {
    expect(ModuleManifest::fromJson(manifestJson(), '/x')->hasPage)->toBeFalse()
        ->and(ModuleManifest::fromJson(manifestJson(['page' => true]), '/x')->hasPage)->toBeTrue()
        ->and(ModuleManifest::fromArray(ModuleManifest::fromJson(manifestJson(['page' => true]), '/x')->toArray(), '/x')
            ->hasPage)->toBeTrue();
});

test('rejects a page flag that is not true or false', function () {
    expect(fn () => ModuleManifest::fromJson(manifestJson(['page' => 'yes']), '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('a manifest says whether the module has a control panel page', function () {
    $flagged = ModuleManifest::fromJson(manifestJson(['admin_page' => true]), '/x');

    expect(ModuleManifest::fromJson(manifestJson(), '/x')->hasAdminPage)->toBeFalse()
        ->and($flagged->hasAdminPage)->toBeTrue()
        ->and(ModuleManifest::fromArray($flagged->toArray(), '/x')->hasAdminPage)->toBeTrue();
});

test('rejects an admin page flag that is not true or false', function () {
    expect(fn () => ModuleManifest::fromJson(manifestJson(['admin_page' => 1]), '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('rejects text that is not json', function () {
    expect(fn () => ModuleManifest::fromJson('{ not json', '/x'))
        ->toThrow(InvalidManifestException::class);
});

test('skips a broken manifest and keeps the rest', function () {
    $registry = ModuleFixtures::registry([]);

    expect(array_keys($registry->all()))->toBe(['sample-extra', 'sample-platform']);
});

test('running lists only modules whose state is on', function () {
    $installed = ModuleFixtures::registry(['sample-extra' => 'on']);
    $paused = ModuleFixtures::registry(['sample-extra' => 'paused']);

    expect(array_keys($installed->running()))->toBe(['sample-extra'])
        ->and($paused->running())->toBe([]);
});

test('present lists installed and paused modules but not unknown ones', function () {
    $paused = ModuleFixtures::registry(['sample-extra' => 'paused']);
    $none = ModuleFixtures::registry([]);

    expect(array_keys($paused->present()))->toBe(['sample-extra'])
        ->and($none->present())->toBe([]);
});

test('blog extras are the installed modules with blog scope', function () {
    $installed = ModuleFixtures::registry(['sample-extra' => 'on']);
    $paused = ModuleFixtures::registry(['sample-extra' => 'paused']);

    expect(array_keys($installed->blogExtras()))->toBe(['sample-extra'])
        ->and($paused->blogExtras())->toBe([]);
});

test('find returns the manifest or null', function () {
    $registry = ModuleFixtures::registry([]);

    expect($registry->find('sample-extra')?->key)->toBe('sample-extra')
        ->and($registry->find('nope'))->toBeNull();
});

test('a missing cache file is rebuilt on first read', function () {
    $file = ModuleFixtures::cacheFile();
    (new ModuleRegistry(FIXTURE_MODULES, $file, fn () => []))->all();

    expect(is_file($file))->toBeTrue();
});

test('the cache is used without calling the state closure again', function () {
    $file = ModuleFixtures::cacheFile();
    $calls = 0;
    $states = function () use (&$calls) {
        $calls++;

        return ['sample-extra' => 'on'];
    };

    (new ModuleRegistry(FIXTURE_MODULES, $file, $states))->running();
    $second = new ModuleRegistry(FIXTURE_MODULES, $file, $states);

    expect(array_keys($second->running()))->toBe(['sample-extra'])
        ->and($calls)->toBe(1);
});

test('rebuild picks up a state change', function () {
    $file = ModuleFixtures::cacheFile();
    $state = 'on';
    $registry = new ModuleRegistry(FIXTURE_MODULES, $file, function () use (&$state) {
        return ['sample-extra' => $state];
    });
    $registry->running();

    $state = 'paused';
    $registry->rebuild();

    expect($registry->running())->toBe([]);
});

test('running lists only modules that are on, present also keeps off and paused ones', function () {
    $registry = ModuleFixtures::registry(['sample-extra' => 'off']);

    expect($registry->running())->toBe([])
        ->and(array_keys($registry->present()))->toBe(['sample-extra']);
});
