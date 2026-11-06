<?php

declare(strict_types=1);

use Modules\JourneyMap\InvalidMapRequest;
use Modules\JourneyMap\MapRequest;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * What the admin asks for becomes a fixed set of tool arguments, and nothing else gets through.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->regions = TempFolder::make('lexicon-jm-regions');

    $this->errors = function (array $input): array {
        try {
            MapRequest::fromInput($input, $this->regions);
        } catch (InvalidMapRequest $e) {
            return array_keys($e->errors);
        }

        return [];
    };
});

afterEach(function () {
    TempFolder::remove($this->regions);
});

test('zoom outside 8 to 10 is refused', function () {
    expect(($this->errors)(['zoom' => '11', 'area' => 'world']))->toBe(['zoom'])
        ->and(($this->errors)(['zoom' => '7', 'area' => 'world']))->toBe(['zoom'])
        ->and(($this->errors)(['zoom' => '9x', 'area' => 'world']))->toBe(['zoom'])
        ->and(($this->errors)(['zoom' => '9', 'area' => 'world']))->toBe([]);
});

test('an unknown area is refused', function () {
    expect(($this->errors)(['zoom' => '10', 'area' => 'atlantis']))->toBe(['area'])
        ->and(($this->errors)(['zoom' => '10']))->toBe(['area']);
});

test('a region name with a path is refused', function () {
    $name = str_repeat('a', 40).'.geojson';
    file_put_contents($this->regions.'/'.$name, json_encode([
        'type' => 'Feature',
        'properties' => ['name' => 'Portugal'],
        'bbox' => [-9.5, 36.9, -6.2, 42.2],
        'geometry' => [
            'type' => 'Polygon',
            'coordinates' => [[[-9.5, 36.9], [-6.2, 36.9], [-6.2, 42.2], [-9.5, 36.9]]],
        ],
    ]));

    expect(($this->errors)(['zoom' => '10', 'area' => 'place', 'region' => '../'.$name]))->toBe(['region'])
        ->and(($this->errors)(['zoom' => '10', 'area' => 'place', 'region' => str_repeat('b', 40).'.geojson']))
        ->toBe(['region'])
        ->and(($this->errors)(['zoom' => '10', 'area' => 'place', 'region' => $name]))->toBe([]);
});

test('a region file with a box off the globe is refused', function () {
    $name = str_repeat('c', 40).'.geojson';
    file_put_contents($this->regions.'/'.$name, json_encode([
        'type' => 'Feature', 'properties' => ['name' => 'Nowhere'], 'bbox' => [-200, 0, 10, 95],
    ]));

    expect(($this->errors)(['zoom' => '10', 'area' => 'place', 'region' => $name]))->toBe(['region']);
});

test('world builds one extract with maxzoom', function () {
    $request = MapRequest::fromInput(['zoom' => '9', 'area' => 'world', 'overview' => '1'], $this->regions);

    expect($request->overview)->toBeFalse()
        ->and($request->label())->toBe('Whole world, zoom 9')
        ->and($request->bbox())->toBeNull()
        ->and($request->commands('/bin/pmtiles', 'https://build.example/b.pmtiles', '/out/map.part', false))
        ->toBe([['/bin/pmtiles', 'extract', 'https://build.example/b.pmtiles', '/out/map.part', '--maxzoom=9']]);
});

test('an area with overview builds two extracts and a merge that do not overlap', function () {
    $request = MapRequest::fromInput(['zoom' => '10', 'area' => 'europe', 'overview' => '1'], $this->regions);
    $commands = $request->commands('pmtiles', 'SRC', 'OUT', false);
    $box = '--bbox='.implode(',', $request->bbox());

    expect($request->label())->toBe('Europe, zoom 10, with world overview')
        ->and($commands)->toBe([
            ['pmtiles', 'extract', 'SRC', 'OUT.area', $box, '--minzoom=6', '--maxzoom=10'],
            ['pmtiles', 'extract', 'SRC', 'OUT.world', '--maxzoom=5'],
            ['pmtiles', 'merge', 'OUT.world', 'OUT.area', 'OUT'],
        ]);
});

test('an area without overview is one extract over the whole zoom range', function () {
    $name = str_repeat('d', 40).'.geojson';
    file_put_contents($this->regions.'/'.$name, json_encode([
        'type' => 'Feature', 'properties' => ['name' => 'Portugal'], 'bbox' => [-9.5, 36.9, -6.2, 42.2],
    ]));
    $request = MapRequest::fromInput(['zoom' => '8', 'area' => 'place', 'region' => $name], $this->regions);

    expect($request->label())->toBe('Portugal, zoom 8')
        ->and($request->bbox())->toBe([-9.5, 36.9, -6.2, 42.2])
        ->and($request->commands('pmtiles', 'SRC', 'OUT', false))->toBe([
            ['pmtiles', 'extract', 'SRC', 'OUT', '--region='.$this->regions.'/'.$name, '--maxzoom=8'],
        ]);
});

test('dry run adds the flag and drops the merge', function () {
    $request = MapRequest::fromInput(['zoom' => '10', 'area' => 'asia', 'overview' => '1'], $this->regions);
    $commands = $request->commands('pmtiles', 'SRC', 'OUT', true);

    expect($commands)->toHaveCount(2)
        ->and(array_map(fn (array $argv): string => end($argv), $commands))->toBe(['--dry-run', '--dry-run'])
        ->and(array_column($commands, 1))->toBe(['extract', 'extract']);
});

test('the request survives the job file', function () {
    $request = MapRequest::fromInput(['zoom' => '10', 'area' => 'oceania', 'overview' => '0'], $this->regions);
    $again = MapRequest::fromArray($request->toArray(), $this->regions);

    expect($again->toArray())->toBe($request->toArray())
        ->and($again->toArray())->toBe(['zoom' => 10, 'area' => 'oceania', 'region' => null, 'overview' => false]);
});
