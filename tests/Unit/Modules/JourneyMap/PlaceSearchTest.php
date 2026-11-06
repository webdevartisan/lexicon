<?php

declare(strict_types=1);

use Framework\Cache\CacheService;
use Modules\JourneyMap\PlaceSearch;
use Modules\JourneyMap\PlaceSearchUnavailable;
use Modules\JourneyMap\SearchStatus;
use Tests\Helpers\ModuleFixtures;

/**
 * Place search asks OpenStreetMap's search from the server, at most once a second
 * for the whole site, and remembers answers so the same search never goes out twice.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->throttle = sys_get_temp_dir().'/lexicon-jm-'.bin2hex(random_bytes(4)).'/nominatim.lock';
    $this->calls = [];
    $this->answer = json_encode([
        ['display_name' => 'Lisbon, Portugal', 'lat' => '38.7077507', 'lon' => '-9.1365919', 'importance' => 0.8],
    ]);

    $store = [];
    $this->cache = Mockery::mock(CacheService::class);
    $this->cache->shouldReceive('get')->andReturnUsing(function (string $key) use (&$store) {
        return $store[$key] ?? null;
    });
    $this->cache->shouldReceive('set')->andReturnUsing(function (string $key, string $value) use (&$store) {
        $store[$key] = $value;

        return true;
    });

    $this->search = function (?string $body = null) {
        $fetch = function (string $url) use ($body): ?string {
            $this->calls[] = $url;

            return $body ?? $this->answer;
        };

        return new PlaceSearch($fetch, $this->throttle, $this->cache);
    };
});

afterEach(function () {
    if (is_file($this->throttle)) {
        unlink($this->throttle);
        rmdir(dirname($this->throttle));
    }
});

test('short and very long queries never call out', function () {
    $search = ($this->search)();

    expect($search->search('  a ', 'en'))->toBe([])
        ->and($search->search(str_repeat('a', 121), 'en'))->toBe([])
        ->and($this->calls)->toBe([]);
});

test('results are trimmed to name and coordinates', function () {
    $places = ($this->search)()->search('Lisbon', 'el');

    expect($places)->toBe([['name' => 'Lisbon, Portugal', 'lat' => 38.7077507, 'lng' => -9.1365919]])
        ->and($this->calls[0])->toStartWith('https://nominatim.openstreetmap.org/search?')
        ->and($this->calls[0])->toContain('q=Lisbon')
        ->and($this->calls[0])->toContain('accept-language=el')
        ->and($this->calls[0])->toContain('limit=5');
});

test('a second search within a second is refused as busy', function () {
    $search = ($this->search)();
    $search->search('Lisbon', 'en');

    expect(fn () => $search->search('Porto', 'en'))
        ->toThrow(fn (PlaceSearchUnavailable $e) => expect($e->reason)->toBe('busy'));
});

test('a timeout is reported as failed and not cached', function () {
    $this->answer = null;
    $search = ($this->search)();

    expect(fn () => $search->search('Lisbon', 'en'))
        ->toThrow(fn (PlaceSearchUnavailable $e) => expect($e->reason)->toBe('failed'));

    $this->cache->shouldNotHaveReceived('set');
});

test('junk json is reported as failed', function () {
    expect(fn () => ($this->search)('{"error": "nope"}')->search('Lisbon', 'en'))
        ->toThrow(fn (PlaceSearchUnavailable $e) => expect($e->reason)->toBe('failed'));
});

test('items with coordinates out of range or without a name are dropped', function () {
    $body = json_encode([
        ['display_name' => 'Nowhere', 'lat' => '95', 'lon' => '10'],
        ['lat' => '10', 'lon' => '10'],
        ['display_name' => 'Porto, Portugal', 'lat' => '41.1', 'lon' => '-8.6'],
        'not an item',
    ]);

    expect(($this->search)($body)->search('Porto', 'en'))
        ->toBe([['name' => 'Porto, Portugal', 'lat' => 41.1, 'lng' => -8.6]]);
});

test('a cached query does not call out again', function () {
    ($this->search)()->search('Lisbon', 'en');
    unlink($this->throttle);

    $again = ($this->search)()->search('  LISBON ', 'en');

    expect($again)->toHaveCount(1)
        ->and($this->calls)->toHaveCount(1);
});

test('a self hosted server is called without the one second turn', function () {
    $fetch = function (string $url): ?string {
        $this->calls[] = $url;

        return $this->answer;
    };
    $search = new PlaceSearch($fetch, $this->throttle, $this->cache, 'https://maps.example.test/search');

    $search->search('Lisbon', 'en');
    $search->search('Porto', 'en');

    expect($this->calls)->toHaveCount(2)
        ->and($this->calls[0])->toStartWith('https://maps.example.test/search?');
});

test('the contact goes along as the email parameter', function () {
    $fetch = function (string $url): ?string {
        $this->calls[] = $url;

        return $this->answer;
    };

    (new PlaceSearch($fetch, $this->throttle, $this->cache, PlaceSearch::PUBLIC_ENDPOINT, 'maps@example.com'))
        ->search('Lisbon', 'en');

    expect($this->calls[0])->toContain('email=maps%40example.com');
});

test('an area comes back with its box and outline', function () {
    $outline = ['type' => 'Polygon', 'coordinates' => [[[-9.6, 36.8], [-6.1, 36.8], [-6.1, 42.2], [-9.6, 36.8]]]];
    $body = json_encode([[
        'display_name' => 'Portugal',
        'boundingbox' => ['36.8', '42.2', '-9.6', '-6.1'],
        'geojson' => $outline,
    ]]);

    $area = ($this->search)($body)->area('Portugal', 'en');

    expect($area)->toBe(['name' => 'Portugal', 'bbox' => [-9.6, 36.8, -6.1, 42.2], 'outline' => $outline])
        ->and($this->calls[0])->toContain('polygon_geojson=1');
});

test('an area answer without an outline is null', function () {
    $body = json_encode([[
        'display_name' => 'Somewhere',
        'boundingbox' => ['1', '2', '3', '4'],
        'geojson' => ['type' => 'Point', 'coordinates' => [3.5, 1.5]],
    ]]);

    expect(($this->search)($body)->area('Somewhere', 'en'))->toBeNull();
});

test('success, failure and busy are recorded', function () {
    $status = new SearchStatus($this->cache);
    $answers = [$this->answer, null];
    $fetch = function (string $url) use (&$answers): ?string {
        return array_shift($answers);
    };
    $search = new PlaceSearch($fetch, $this->throttle, $this->cache, PlaceSearch::PUBLIC_ENDPOINT, '', $status);

    $search->search('Lisbon', 'en');
    expect(fn () => $search->search('Porto', 'en'))->toThrow(PlaceSearchUnavailable::class);
    unlink($this->throttle);
    expect(fn () => $search->search('Faro', 'en'))->toThrow(PlaceSearchUnavailable::class);

    $read = $status->read();
    expect($read['lastSuccess'])->not->toBeNull()
        ->and($read['lastFailure'])->not->toBeNull()
        ->and($read['failureReason'])->toBe('failed')
        ->and($read['busyToday'])->toBe(1);
});
