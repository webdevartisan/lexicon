<?php

declare(strict_types=1);

use Modules\JourneyMap\InvalidStopException;
use Modules\JourneyMap\Stop;
use Tests\Helpers\ModuleFixtures;

/**
 * A stop is checked and rounded in one place, whether it comes from the editor or a backup.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->input = fn (array $changes = []): array => $changes + [
        'place_name' => 'Paris',
        'lat' => '48.8583704',
        'lng' => '2.2944813',
        'precision' => 'exact',
        'stop_number' => '3',
    ];
    $this->errors = function (array $input): array {
        try {
            Stop::fromInput($input);
        } catch (InvalidStopException $e) {
            return $e->errors;
        }

        return [];
    };
});

test('an area stop is rounded before it is stored', function () {
    $stop = Stop::fromInput(($this->input)(['precision' => 'area']));

    expect($stop->lat)->toBe(48.86)
        ->and($stop->lng)->toBe(2.29)
        ->and($stop->precision)->toBe('area');
});

test('an exact stop keeps six decimals', function () {
    $stop = Stop::fromInput(($this->input)());

    expect($stop->lat)->toBe(48.85837)
        ->and($stop->lng)->toBe(2.294481)
        ->and($stop->stopNumber)->toBe(3)
        ->and($stop->placeName)->toBe('Paris');
});

test('each bad field is reported', function () {
    $errors = ($this->errors)([
        'place_name' => '   ',
        'lat' => '91',
        'lng' => '-181',
        'precision' => 'near',
        'stop_number' => '0',
    ]);

    expect(array_keys($errors))->toEqualCanonicalizing(['place_name', 'lat', 'lng', 'precision', 'stop_number']);
});

test('a name over 160 characters is refused', function () {
    expect(($this->errors)(($this->input)(['place_name' => str_repeat('a', 161)])))->toHaveKey('place_name');
});

test('words are not coordinates', function () {
    expect(($this->errors)(($this->input)(['lat' => 'north', 'lng' => ''])))->toHaveKeys(['lat', 'lng']);
});

test('an empty stop number is null', function () {
    expect(Stop::fromInput(($this->input)(['stop_number' => '']))->stopNumber)->toBeNull()
        ->and(Stop::fromInput(($this->input)(['stop_number' => null]))->stopNumber)->toBeNull();
});

test('a stop gives its table row', function () {
    expect(Stop::fromInput(($this->input)())->toRow())->toBe([
        'place_name' => 'Paris',
        'lat' => 48.85837,
        'lng' => 2.294481,
        'place_precision' => 'exact',
        'stop_number' => 3,
    ]);
});
