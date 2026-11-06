<?php

declare(strict_types=1);

use App\Auth;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use Framework\Helpers\RateLimiter;
use Framework\Interfaces\TemplateViewerInterface;
use Modules\JourneyMap\PlaceController;
use Modules\JourneyMap\PlaceSearch;
use Modules\JourneyMap\PlaceSearchUnavailable;
use Modules\JourneyMap\SearchSettings;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\ThrottleTestHelper;

/**
 * Place search answers the editor with places, or with a short message it can show.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $this->userId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->userId);
    (new BlogModuleModel($this->db))->enable($this->blogId, 'journey-map');

    $this->places = Mockery::mock(PlaceSearch::class);
    $this->limiter = new RateLimiter(ThrottleTestHelper::fakeCache());
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    $this->settings = new SearchSettings(new SettingModel($this->db));
    $this->call = function (string $query = 'Lisbon') {
        $auth = Mockery::mock(Auth::class);
        $auth->shouldReceive('user')->andReturn(['id' => $this->userId]);
        $request = makeRequest('/dashboard/blog/'.$this->blogId.'/m/journey-map/places', 'GET', [], ['q' => $query]);
        $controller = new PlaceController(
            new BlogModuleModel($this->db),
            $this->places,
            $this->limiter,
            $auth,
            $this->settings
        );
        setupController($controller, $request, Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing());

        $response = $controller->search((string) $this->blogId);

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };
});

afterEach(function () {
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('results come back as places', function () {
    $this->places->shouldReceive('search')->with('Lisbon', 'en')
        ->andReturn([['name' => 'Lisbon, Portugal', 'lat' => 38.7, 'lng' => -9.1]]);

    expect(($this->call)())->toBe([200, ['places' => [['name' => 'Lisbon, Portugal', 'lat' => 38.7, 'lng' => -9.1]]]]);
});

test('a busy search answers 429 and a failed one 503, each with a message', function () {
    $this->places->shouldReceive('search')->once()->andThrow(new PlaceSearchUnavailable('busy'));
    $this->places->shouldReceive('search')->once()->andThrow(new PlaceSearchUnavailable('failed'));

    expect(($this->call)())->toBe([429, ['error' => 'Place search is busy. Try again in a moment.']])
        ->and(($this->call)())->toBe([503, ['error' => 'Place search is not available right now.']]);
});

test('a user over 30 searches a minute gets 429 with a message', function () {
    $this->places->shouldReceive('search')->andReturn([]);

    $statuses = [];
    for ($i = 0; $i < 35; $i++) {
        $statuses[] = ($this->call)()[0];
    }

    expect($statuses[0])->toBe(200)
        ->and(end($statuses))->toBe(429)
        ->and(($this->call)()[1])->toBe(['error' => 'Too many searches. Wait a minute and try again.']);
});

test('search is a 404 while it is switched off', function () {
    $this->settings->save(false, '', '');
    $this->places->shouldNotReceive('search');

    expect(($this->call)())->toBe([404, ['error' => 'Place search is switched off on this site.']]);
});
