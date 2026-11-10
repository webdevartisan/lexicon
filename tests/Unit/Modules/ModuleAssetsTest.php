<?php

declare(strict_types=1);

use App\Services\Modules\ModuleAssets;
use Framework\Modules\ModuleManifest;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * A module's public folder: scripts and styles copied from its assets, plus a files/ folder for data
 * the module downloads or makes, which a fresh copy of the scripts must not delete.
 */
beforeEach(function () {
    $this->public = TempFolder::make('lexicon-module-assets');
    $this->assets = new ModuleAssets($this->public);
    $this->manifest = ModuleManifest::fromFile(ModuleFixtures::PATH.'/sample-extra/module.json');
});

afterEach(fn () => TempFolder::remove($this->public));

test('a fresh copy of the scripts keeps the files folder', function () {
    $files = $this->assets->filesFolder('sample-extra');
    mkdir($files.'/fonts', 0775, true);
    file_put_contents($files.'/fonts/0-255.pbf', 'font');
    file_put_contents($this->public.'/sample-extra/old.js', 'gone');

    $this->assets->publish($this->manifest);

    expect(file_get_contents($files.'/fonts/0-255.pbf'))->toBe('font')
        ->and(is_file($this->public.'/sample-extra/note.js'))->toBeTrue()
        ->and(is_file($this->public.'/sample-extra/old.js'))->toBeFalse();
});

test('the files folder has a path and a web address under the module folder', function () {
    expect($this->assets->filesFolder('sample-extra'))->toBe($this->public.'/sample-extra/files')
        ->and($this->assets->filesUrl('sample-extra'))->toEndWith('/modules/sample-extra/files');
});

test('removing the module deletes its files folder too', function () {
    mkdir($this->assets->filesFolder('sample-extra'), 0775, true);

    $this->assets->remove('sample-extra');

    expect(is_dir($this->public.'/sample-extra'))->toBeFalse();
});
