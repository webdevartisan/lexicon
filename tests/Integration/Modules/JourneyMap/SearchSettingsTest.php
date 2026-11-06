<?php

declare(strict_types=1);

use App\Models\SettingModel;
use Modules\JourneyMap\PlaceSearch;
use Modules\JourneyMap\SearchSettings;
use Tests\Helpers\ModuleFixtures;

/**
 * The admin decides whether place search runs, who the search service can contact, and which server answers.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    $this->fresh = fn (): SearchSettings => new SearchSettings(new SettingModel($this->db));
});

afterEach(function () {
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    ModuleFixtures::cleanUp();
});

test('defaults are on, the site contact and the public server', function () {
    $settings = ($this->fresh)();

    expect($settings->enabled())->toBeTrue()
        ->and($settings->contact())->toBe((string) site_content('contact.email', ''))
        ->and($settings->server())->toBe('')
        ->and($settings->endpoint())->toBe(PlaceSearch::PUBLIC_ENDPOINT);
});

test('bad contact and server are refused and nothing is saved', function () {
    $settings = ($this->fresh)();

    expect(array_keys($settings->save(true, 'not an address', 'http://maps.example.test/search')))
        ->toBe(['contact', 'server'])
        ->and(array_keys($settings->save(true, '', 'https://someone@maps.example.test/search')))->toBe(['server'])
        ->and(array_keys($settings->save(true, '', 'https://maps.example.test/other')))->toBe(['server'])
        ->and(($this->fresh)()->server())->toBe('');
});

test('good values are saved', function () {
    expect(($this->fresh)()->save(false, 'maps@example.com', 'https://maps.example.test/search'))->toBe([]);

    $saved = ($this->fresh)();

    expect($saved->enabled())->toBeFalse()
        ->and($saved->contact())->toBe('maps@example.com')
        ->and($saved->server())->toBe('https://maps.example.test/search')
        ->and($saved->endpoint())->toBe('https://maps.example.test/search');
});
