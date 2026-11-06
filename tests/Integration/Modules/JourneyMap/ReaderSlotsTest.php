<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\Modules\SlotRegistry;
use Framework\Cache\CacheService;
use Framework\Core\Container;
use Framework\Database;
use Framework\Modules\ModuleBooter;
use Framework\Modules\ModuleRegistry;
use Framework\View\RouteContext;
use Modules\JourneyMap\MapData;
use Modules\JourneyMap\Stop;
use Modules\JourneyMap\StopModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Readers see a stop's place on its post, a link to the next stop and a Journey link in the blog menu.
 */
function journeySlots(Database $db, string $publicPath, string $cacheDir): SlotRegistry
{
    $modules = new ModuleModel($db);
    $registry = new ModuleRegistry(ModuleFixtures::REAL, ModuleFixtures::cacheFile(), fn () => $modules->states());
    $container = new Container();
    $container->set(Database::class, fn () => $db);
    $booter = new ModuleBooter($registry, $container, ModuleFixtures::instances($db));
    $booter->registerAll();
    $container->setShared(MapData::class, fn () => new MapData($publicPath.'/map-data'));

    return new SlotRegistry(
        $booter,
        new BlogModuleModel($db),
        $modules,
        $registry,
        new CacheService($cacheDir),
        new RouteContext(),
        true
    );
}

beforeEach(function () {
    ModuleFixtures::installJourneyMap($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $blogs = new BlogModel($this->db);
    $blogId = BlogFactory::new($blogs)->published()->create($ownerId);
    $this->blog = $blogs->getBlog($blogId)->toArray();
    $this->blogModules = new BlogModuleModel($this->db);
    $this->blogModules->enable($blogId, 'journey-map');
    $this->stops = new StopModel($this->db);

    $this->post = function (string $title, string $slug, string $status = 'published') use ($ownerId, $blogId): array {
        $id = PostFactory::new(new PostModel($this->db))->published()->withAttributes([
            'author_id' => $ownerId,
            'blog_id' => $blogId,
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
        ])->create();

        return ['id' => $id, 'title' => $title, 'slug' => $slug];
    };
    $this->place = fn (array $post, string $name, int $number) => $this->stops->save(
        $blogId,
        $post['id'],
        Stop::fromInput(['place_name' => $name, 'lat' => '38.7', 'lng' => '-9.1', 'precision' => 'exact',
            'stop_number' => (string) $number])
    );

    $this->publicPath = sys_get_temp_dir().'/lexicon-jm-public-'.bin2hex(random_bytes(4));
    mkdir($this->publicPath.'/map-data', 0775, true);
    touch($this->publicPath.'/map-data/journey-map.pmtiles');
    $this->cacheDir = sys_get_temp_dir().'/lexicon-jm-cache-'.bin2hex(random_bytes(4));
    $this->slots = fn (?string $publicPath = null): SlotRegistry => journeySlots(
        $this->db,
        $publicPath ?? $this->publicPath,
        $this->cacheDir
    );
    $this->draw = fn (SlotRegistry $slots, string $slot, ?array $post): string => $slots->render(
        $slot,
        ['blog' => $this->blog, 'post' => $post]
    );
});

afterEach(function () {
    ModuleFixtures::dropJourneyMap($this->db);
    ModuleFixtures::cleanUp();
    @unlink($this->publicPath.'/map-data/journey-map.pmtiles');
    @rmdir($this->publicPath.'/map-data');
    @rmdir($this->publicPath);
    (new CacheService($this->cacheDir))->clear();
    @rmdir($this->cacheDir);
});

test('a post with a stop shows its place and loads the map scripts', function () {
    $lisbon = ($this->post)('Day one', 'day-one');
    ($this->place)($lisbon, 'Lisbon', 1);
    $slots = ($this->slots)();

    expect(($this->draw)($slots, 'post.header-aside', $lisbon))
        ->toContain('Lisbon')
        ->toContain('data-journey-map="locator"')
        ->and(($this->draw)($slots, 'body.end', $lisbon))->toContain('maplibre-gl-csp.js');
});

test('the locator has no map box when the map file is missing', function () {
    $lisbon = ($this->post)('Day one', 'day-one');
    ($this->place)($lisbon, 'Lisbon', 1);
    $slots = ($this->slots)(sys_get_temp_dir().'/no-map-here');

    expect(($this->draw)($slots, 'post.header-aside', $lisbon))
        ->toContain('Lisbon')
        ->not->toContain('data-journey-map=')
        ->and(($this->draw)($slots, 'body.end', $lisbon))->not->toContain('maplibre');
});

test('next stop links the following public stop', function () {
    $first = ($this->post)('Day one', 'day-one');
    $hidden = ($this->post)('Secret day', 'secret-day', 'draft');
    $second = ($this->post)('Day & night', 'day-two');
    ($this->place)($first, 'Lisbon', 1);
    ($this->place)($hidden, 'Sintra', 2);
    ($this->place)($second, 'Porto', 3);

    expect(($this->draw)(($this->slots)(), 'post.after-content', $first))
        ->toContain('Next stop: Day &amp; night')
        ->toContain('/blog/'.$this->blog['blog_slug'].'/day-two')
        ->not->toContain('Secret day');
});

test('the last stop shows no next stop', function () {
    $only = ($this->post)('Day one', 'day-one');
    ($this->place)($only, 'Lisbon', 1);

    expect(($this->draw)(($this->slots)(), 'post.after-content', $only))->toBe('');
});

test('the nav link shows only once a public stop exists', function () {
    $draft = ($this->post)('Draft day', 'draft-day', 'draft');
    ($this->place)($draft, 'Sintra', 1);

    expect(($this->draw)(($this->slots)(), 'blog.nav', null))->toBe('');

    ($this->place)(($this->post)('Day one', 'day-one'), 'Lisbon', 2);

    expect(($this->draw)(($this->slots)(), 'blog.nav', null))
        ->toContain('Journey')
        ->toContain('/blog/'.$this->blog['blog_slug'].'/m/journey-map');
});

test('nothing renders on a blog that has the module off', function () {
    $lisbon = ($this->post)('Day one', 'day-one');
    ($this->place)($lisbon, 'Lisbon', 1);
    ($this->place)(($this->post)('Day two', 'day-two'), 'Porto', 2);
    $this->blogModules->disable((int) $this->blog['id'], 'journey-map');
    $slots = ($this->slots)();

    foreach (['post.header-aside', 'post.after-content', 'blog.nav'] as $slot) {
        expect(($this->draw)($slots, $slot, $lisbon))->toBe('');
    }
});
