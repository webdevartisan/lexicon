<?php

declare(strict_types=1);

use Modules\JourneyMap\MapAssets;
use Modules\JourneyMap\MapJobFailed;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * Label fonts and icons come from the basemaps-assets archive; only the folders the map uses are kept.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->root = TempFolder::make('lexicon-jm-assets');
    $this->public = $this->root.'/public';
    mkdir($this->public, 0775, true);

    $this->assets = function (array $entries): MapAssets {
        $zip = new ZipArchive();
        $zip->open($this->root.'/source.zip', ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString('basemaps-assets-main/'.$name, $content);
        }
        $zip->close();

        $download = fn (string $url, string $path): bool => copy($this->root.'/source.zip', $path);

        return new MapAssets($this->public.'/map-data', $download);
    };
});

afterEach(function () {
    TempFolder::remove($this->root);
});

test('only the three fonts and the v4 icons are kept', function () {
    $assets = ($this->assets)([
        'fonts/Noto Sans Regular/0-255.pbf' => 'regular',
        'fonts/Noto Sans Italic/0-255.pbf' => 'italic',
        'fonts/Noto Sans Medium/0-255.pbf' => 'medium',
        'fonts/Noto Sans Bold/0-255.pbf' => 'bold',
        'sprites/v4/light.json' => '{}',
        'sprites/v3/light.json' => '{}',
        'README.md' => 'hi',
    ]);
    expect($assets->missing())->toBeTrue();

    $assets->install();
    $kept = $this->public.'/map-data';

    expect($assets->missing())->toBeFalse()
        ->and(file_get_contents($kept.'/fonts/Noto Sans Medium/0-255.pbf'))->toBe('medium')
        ->and(is_file($kept.'/sprites/v4/light.json'))->toBeTrue()
        ->and(is_dir($kept.'/fonts/Noto Sans Bold'))->toBeFalse()
        ->and(is_dir($kept.'/sprites/v3'))->toBeFalse()
        ->and(is_file($kept.'/README.md'))->toBeFalse()
        ->and(glob($kept.'/*.part'))->toBe([]);
});

test('an entry that climbs out of the folder stops the install', function () {
    $assets = ($this->assets)([
        'fonts/Noto Sans Regular/0-255.pbf' => 'regular',
        'fonts/Noto Sans Regular/../../../outside.txt' => 'nope',
        'sprites/v4/light.json' => '{}',
    ]);

    expect(fn () => $assets->install())->toThrow(MapJobFailed::class)
        ->and($assets->missing())->toBeTrue()
        ->and(is_file($this->root.'/outside.txt'))->toBeFalse()
        ->and(glob($this->public.'/map-data/*'))->toBe([]);
});

test('a failed download installs nothing', function () {
    $assets = new MapAssets($this->public.'/map-data', fn (): bool => false);

    expect(fn () => $assets->install())->toThrow(MapJobFailed::class)
        ->and($assets->missing())->toBeTrue();
});
