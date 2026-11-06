<?php

declare(strict_types=1);

use App\Auth;
use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\PublicCacheInvalidator;
use Framework\Cache\CacheService;
use Framework\Core\App;
use Framework\Interfaces\TemplateViewerInterface;
use Modules\JourneyMap\AdminController;
use Modules\JourneyMap\MapControl;
use Modules\JourneyMap\MapData;
use Modules\JourneyMap\MapJob;
use Modules\JourneyMap\MapJobStarter;
use Modules\JourneyMap\MapRequest;
use Modules\JourneyMap\MapTool;
use Modules\JourneyMap\PlaceSearch;
use Modules\JourneyMap\RangeProbe;
use Modules\JourneyMap\RegionStore;
use Modules\JourneyMap\SearchSettings;
use Modules\JourneyMap\SearchStatus;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * The module's control panel page: map status and jobs, place search settings and usage.
 * Jobs are queued for real but never started; the starter is a stand in.
 */
beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    $this->root = TempFolder::make('lexicon-jm-admin');
    mkdir($this->root.'/storage/bin', 0775, true);
    mkdir($this->root.'/public/map-data', 0775, true);
    touch($this->root.'/storage/bin/pmtiles');

    $this->job = new MapJob($this->root.'/storage');
    $this->data = new MapData(
        $this->root.'/public/map-data',
        $this->root.'/storage',
        'https://example.test/files/maps'
    );
    $this->starter = new class extends MapJobStarter {
        public int $starts = 0;

        public function __construct()
        {
        }

        public function start(): array
        {
            $this->starts++;

            return ['ok' => true, 'message' => 'Started in the background.'];
        }
    };
    $tool = new MapTool($this->root.'/storage/bin', fn () => null, fn () => false, 'Linux', 'x86_64');
    $regions = new RegionStore($this->job->regionDir());
    $control = new MapControl($this->job, $this->data, $tool, $this->starter, new PublicCacheInvalidator(), $regions);
    $this->places = Mockery::mock(PlaceSearch::class);
    $cache = App::container()->get(CacheService::class);
    $auth = Mockery::mock(Auth::class);
    $auth->shouldReceive('user')->andReturn(['id' => 5]);

    $this->controller = function (string $path, array $post = []) use ($control, $cache, $auth): AdminController {
        $controller = new AdminController(
            $control,
            $this->places,
            new SearchSettings(new SettingModel($this->db)),
            new SearchStatus($cache),
            new StopModel($this->db),
            new class extends RangeProbe {
                public function status(string $url): int
                {
                    return 206;
                }
            },
            $auth
        );
        $request = makeRequest('/admin/m/journey-map'.$path, $post === [] ? 'GET' : 'POST', $post);
        $this->seen = [];
        $viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
        $viewer->shouldReceive('render')->andReturnUsing(function ($template, $data) {
            $this->seen = ['template' => $template, 'data' => $data];

            return '';
        });
        setupController($controller, $request, $viewer);

        return $controller;
    };
    $this->post = function (string $action, array $post = []): array {
        $response = ($this->controller)('/'.$action, $post + ['_token' => 'checked by the middleware'])->{$action}();

        return [$response->getStatusCode(), json_decode($response->getBody(), true)];
    };
    $this->request = fn (string $area): MapRequest => MapRequest::fromInput(
        ['zoom' => '10', 'area' => $area, 'overview' => '1'],
        $this->job->regionDir()
    );
    $this->measured = function (string $area, int $bytes): void {
        $this->job->queue('size', ($this->request)($area), 1);
        $this->job->begin(1);
        $this->job->sized($bytes);
        $this->job->finish();
    };
});

afterEach(function () {
    $this->db->execute("DELETE FROM settings WHERE name LIKE 'journey-map.%'");
    TempFolder::remove($this->root);
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('download is refused without a size check for the same request', function () {
    ($this->measured)('europe', 1000);

    [$status, $body] = ($this->post)('download', ['zoom' => '10', 'area' => 'asia', 'overview' => '1']);

    expect($status)->toBe(422)
        ->and($body['error'])->toBe('Check the size of this map first.')
        ->and($this->starter->starts)->toBe(0);

    [$status] = ($this->post)('download', ['zoom' => '10', 'area' => 'europe', 'overview' => '1']);
    expect($status)->toBe(200)
        ->and($this->starter->starts)->toBe(1)
        ->and($this->job->current())->toMatchArray(['state' => 'queued', 'kind' => 'download', 'userId' => 5]);
});

test('download is refused when the map would not fit', function () {
    ($this->measured)('europe', 10 ** 18);

    [$status, $body] = ($this->post)('download', ['zoom' => '10', 'area' => 'europe', 'overview' => '1']);

    expect($status)->toBe(422)
        ->and($body['error'])->toStartWith('The map needs')
        ->and($this->starter->starts)->toBe(0);
});

test('bad map choices come back per field', function () {
    [$status, $body] = ($this->post)('check', ['zoom' => '11', 'area' => 'atlantis']);

    expect($status)->toBe(422)
        ->and(array_keys($body['errors']))->toBe(['zoom', 'area'])
        ->and($this->starter->starts)->toBe(0);
});

test('a second job is refused while one runs', function () {
    $this->job->queue('download', ($this->request)('europe'), 1);
    $this->job->begin(1);

    [$status, $body] = ($this->post)('check', ['zoom' => '9', 'area' => 'world']);

    expect($status)->toBe(409)
        ->and($body['error'])->toBe('A map job is already running.')
        ->and($this->starter->starts)->toBe(0);
});

test('remove is refused while a job runs', function () {
    file_put_contents($this->root.'/public/map-data/journey-map.pmtiles', 'map');
    $this->job->queue('download', ($this->request)('europe'), 1);
    $this->job->begin(1);

    [$status] = ($this->post)('remove');
    expect($status)->toBe(409)
        ->and($this->data->available())->toBeTrue();

    $this->job->finish();
    [$status] = ($this->post)('remove');
    expect($status)->toBe(200)
        ->and($this->data->available())->toBeFalse();
});

test('cancel flags the running job', function () {
    $this->job->queue('download', ($this->request)('europe'), 1);
    $this->job->begin(1);

    [$status] = ($this->post)('cancel');

    expect($status)->toBe(200)
        ->and($this->job->cancelRequested())->toBeTrue();
});

test('a typed area writes its outline under a generated name', function () {
    $this->places->shouldReceive('area')->with('Portugal', 'en')->andReturn([
        'name' => 'Portugal',
        'bbox' => [-9.5, 36.9, -6.2, 42.2],
        'outline' => ['type' => 'Polygon', 'coordinates' => [[[-9.5, 36.9], [-6.2, 36.9], [-6.2, 42.2], [-9.5, 36.9]]]],
    ]);

    [$status, $body] = ($this->post)('area', ['name' => 'Portugal']);

    expect($status)->toBe(200)
        ->and($body['name'])->toBe('Portugal')
        ->and($body['file'])->toMatch('/^[a-f0-9]{40}\.geojson$/')
        ->and(is_file($this->job->regionDir().'/'.$body['file']))->toBeTrue()
        ->and(MapRequest::fromInput(
            ['zoom' => 8, 'area' => 'place', 'region' => $body['file']],
            $this->job->regionDir()
        )->label())->toBe('Portugal, zoom 8');
});

test('an outline with points off the globe is not written', function () {
    $this->places->shouldReceive('area')->andReturn([
        'name' => 'Nowhere',
        'bbox' => [-9.5, 36.9, -6.2, 42.2],
        'outline' => ['type' => 'Polygon', 'coordinates' => [[[-9.5, 36.9], [-600, 36.9], [-9.5, 36.9]]]],
    ]);

    [$status] = ($this->post)('area', ['name' => 'Nowhere']);

    expect($status)->toBe(404)
        ->and(glob($this->job->regionDir().'/*') ?: [])->toBe([]);
});

test('a typed area name must be 3 to 120 characters', function () {
    $this->places->shouldNotReceive('area');

    expect(($this->post)('area', ['name' => 'ab'])[0])->toBe(422)
        ->and(($this->post)('area', ['name' => str_repeat('a', 121)])[0])->toBe(422);
});

test('search settings errors come back per field', function () {
    [$status, $body] = ($this->post)('saveSearch', [
        'enabled' => '1',
        'contact' => 'not an address',
        'server' => 'http://maps.example.org/search',
    ]);

    expect($status)->toBe(422)
        ->and(array_keys($body['errors']))->toBe(['contact', 'server']);

    [$status] = ($this->post)('saveSearch', ['enabled' => '0', 'contact' => '', 'server' => '']);
    expect($status)->toBe(200)
        ->and((new SearchSettings(new SettingModel($this->db)))->enabled())->toBeFalse();
});

test('the page lists blogs using the module and stops outside the area', function () {
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($ownerId);
    (new BlogModuleModel($this->db))->enable($blogId, 'journey-map');
    $postId = PostFactory::new(new PostModel($this->db))->published()
        ->withAttributes(['author_id' => $ownerId, 'blog_id' => $blogId, 'title' => 'Tokyo days'])->create();
    (new StopModel($this->db))->save($blogId, $postId, Stop::fromInput([
        'place_name' => 'Tokyo', 'lat' => '35.68', 'lng' => '139.69', 'precision' => 'exact', 'stop_number' => '',
    ]));
    file_put_contents($this->data->partPath(), 'PMTiles'.chr(3));
    $europe = ($this->request)('europe')->toArray();
    $this->data->swapIn($this->data->partPath(), ['build' => '2026-11-05', 'request' => $europe]);

    ($this->controller)('/')->show();
    $page = $this->seen['data'];

    expect($this->seen['template'])->toBe('journey-map::admin')
        ->and(array_column($page['usage'], 'blog_id'))->toContain($blogId)
        ->and(array_column($page['outside'], 'title'))->toBe(['Tokyo days'])
        ->and($page['status']['map']['label'])->toBe('Europe, zoom 10, with world overview')
        ->and($page['rangeStatus'])->toBe(206)
        ->and($page['preview']['bbox'])->toBe(MapRequest::PRESETS['europe'])
        ->and($page['preview']['files']['js'])->toContain('map-core.js');
});

test('the page has no area preview before a map is downloaded', function () {
    ($this->controller)('/')->show();

    expect($this->seen['data']['preview'])->toBeNull();
});

test('download warns that it replaces the installed map, naming it', function () {
    $status = fn (): array => json_decode(($this->controller)('/status')->status()->getBody(), true);
    expect($status()['lines']['replace'])->toBe('');

    file_put_contents($this->data->partPath(), 'PMTiles'.chr(3));
    $europe = ($this->request)('europe')->toArray();
    $this->data->swapIn($this->data->partPath(), ['build' => '2026-11-05', 'request' => $europe]);

    expect($status()['lines']['replace'])
        ->toBe('This replaces the installed map, Europe, zoom 10, with world overview. Only one map is kept.');
});
