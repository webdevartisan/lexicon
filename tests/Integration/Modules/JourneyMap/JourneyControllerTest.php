<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\BlogSettingsModel;
use App\Models\BlogTextModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Services\BlogCacheInvalidator;
use App\Services\BlogLocales;
use App\Services\BlogPageContext;
use App\Services\ThemeService;
use App\Services\ThemeWording;
use App\Services\ViewerContext;
use Framework\Core\App;
use Framework\Exceptions\PageNotFoundException;
use Framework\Interfaces\TemplateViewerInterface;
use Modules\JourneyMap\JourneyController;
use Modules\JourneyMap\MapData;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * The Journey page shows every public stop in the blog's own theme.
 */
beforeEach(function () {
    $_SESSION = [];
    auth()->logout();
    ModuleFixtures::installJourneyMap($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogs = new BlogModel($this->db);
    $this->blogId = BlogFactory::new($this->blogs)->published()->create($ownerId);
    $this->slug = (string) $this->blogs->getBlog($this->blogId)->toArray()['blog_slug'];
    $this->blogModules = new BlogModuleModel($this->db);
    $this->blogModules->enable($this->blogId, 'journey-map');
    $this->stops = new StopModel($this->db);

    $this->post = function (string $title, array $attributes = []) use ($ownerId): int {
        $id = PostFactory::new(new PostModel($this->db))->published()->withAttributes($attributes + [
            'author_id' => $ownerId,
            'blog_id' => $this->blogId,
            'title' => $title,
        ])->create();
        $this->stops->save($this->blogId, $id, Stop::fromInput([
            'place_name' => $title.' place',
            'lat' => '38.7',
            'lng' => '-9.1',
            'precision' => 'exact',
            'stop_number' => '',
        ]));

        return $id;
    };

    $container = App::container();
    $texts = new BlogTextModel($this->db);
    $blogCache = $container->get(BlogCacheInvalidator::class);
    $this->pageContext = new BlogPageContext(
        $this->blogs,
        new UserModel($this->db),
        new BlogSettingsModel($this->db),
        new UserProfileModel($this->db),
        new BlogLocales(new PostTranslationModel($this->db)),
        new ThemeWording($texts, $container->get(ThemeService::class), $blogCache),
        $container->get(ViewerContext::class),
    );

    $this->open = function (?string $publicPath = null): array {
        $seen = [];
        $viewer = Mockery::mock(TemplateViewerInterface::class)->shouldIgnoreMissing();
        $viewer->shouldReceive('render')->andReturnUsing(function ($template, $data) use (&$seen) {
            $seen = ['template' => $template, 'data' => $data];

            return '';
        });
        $map = new MapData(($publicPath ?? sys_get_temp_dir().'/no-map-here').'/map-data');
        $controller = new JourneyController($this->blogModules, $this->stops, $map, $this->pageContext);
        setupController($controller, makeRequest('/blog/'.$this->slug.'/m/journey-map'), $viewer);
        $controller->show($this->slug);

        return $seen;
    };
});

afterEach(function () {
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
});

test('the page lists public stops in order', function () {
    $second = ($this->post)('Porto', ['published_at' => '2026-02-01 10:00:00']);
    $first = ($this->post)('Lisbon', ['published_at' => '2026-01-01 10:00:00']);

    $page = ($this->open)();

    expect($page['template'])->toBe('journey-map::journey')
        ->and(array_map('intval', array_column($page['data']['stops'], 'post_id')))->toBe([$first, $second])
        ->and($page['data']['blog']['blog_slug'])->toBe($this->slug)
        ->and($page['data']['meta']['title'])->toContain('Journey');
});

test('stops on posts that are not public are not listed', function () {
    ($this->post)('Lisbon');
    ($this->post)('Draft day', ['status' => 'draft']);
    ($this->post)('Private day', ['visibility' => 'private']);

    expect(array_column(($this->open)()['data']['stops'], 'title'))->toBe(['Lisbon']);
});

test('the page is a 404 while the module is off for the blog', function () {
    $this->blogModules->disable($this->blogId, 'journey-map');

    expect(fn () => ($this->open)())->toThrow(PageNotFoundException::class);
});

test('with no map file the page still lists the stops and loads no map script', function () {
    ($this->post)('Lisbon');

    $data = ($this->open)()['data'];

    expect($data['stops'])->toHaveCount(1)
        ->and($data['map'])->toBeNull()
        ->and(implode(' ', $data['scripts']))->not->toContain('maplibre');
});

test('with the map file the page loads the map scripts', function () {
    $publicPath = sys_get_temp_dir().'/lexicon-jm-public-'.bin2hex(random_bytes(4));
    mkdir($publicPath.'/map-data', 0775, true);
    touch($publicPath.'/map-data/journey-map.pmtiles');
    ($this->post)('Lisbon');

    $data = ($this->open)($publicPath)['data'];
    unlink($publicPath.'/map-data/journey-map.pmtiles');
    rmdir($publicPath.'/map-data');
    rmdir($publicPath);

    expect($data['map'])->toHaveKeys(['tiles', 'glyphs', 'sprite', 'worker'])
        ->and(implode(' ', $data['scripts']))->toContain('maplibre-gl-csp.js')
        ->and(implode(' ', $data['scripts']))->toContain('map.js');
});
