<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use App\Models\UserModel;
use App\Services\Modules\SlotRegistry;
use Framework\Cache\CacheService;
use Framework\Core\Container;
use Framework\Database;
use Framework\Modules\ModuleBooter;
use Framework\Modules\ModuleRegistry;
use Framework\View\RouteContext;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Fixtures\Modules\SampleExtra\NoteSlot;
use Tests\Helpers\ModuleFixtures;

/**
 * @return array{0: SlotRegistry, 1: CacheService}
 */
function slotRegistryFor(Database $db, string $cacheDir, bool $debug = false): array
{
    $modules = new ModuleModel($db);
    $registry = new ModuleRegistry(ModuleFixtures::PATH, ModuleFixtures::cacheFile(), fn () => $modules->states());
    $container = new Container();
    $container->set(Database::class, fn () => $db);
    $booter = new ModuleBooter($registry, $container, ModuleFixtures::instances($db));
    $booter->registerAll();
    $cache = new CacheService($cacheDir);

    $blogModules = new BlogModuleModel($db);

    return [new SlotRegistry($booter, $blogModules, $modules, $registry, $cache, new RouteContext(), $debug), $cache];
}

beforeEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::installer($this->db)->install('sample-extra');
    NoteSlot::$fail = false;

    $blogs = new BlogModel($this->db);
    $ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->on = BlogFactory::new($blogs)->create($ownerId);
    $this->off = BlogFactory::new($blogs)->create($ownerId);
    (new BlogModuleModel($this->db))->enable($this->on, 'sample-extra');

    $this->cacheDir = sys_get_temp_dir().'/lexicon-slot-cache-'.bin2hex(random_bytes(4));
    [$this->slots, $this->cache] = slotRegistryFor($this->db, $this->cacheDir);
    $this->post = ['id' => 9, 'title' => 'Day one'];
});

afterEach(function () {
    NoteSlot::$fail = false;
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::dropSamplePlatform($this->db);
    ModuleFixtures::cleanUp();
    $this->cache->clear();
    @rmdir($this->cacheDir);
});

test('a module renders only on blogs that switched it on', function () {
    expect($this->slots->render('post.after-content', ['blog' => ['id' => $this->on], 'post' => $this->post]))
        ->toContain('sample-extra-note')
        ->and($this->slots->render('post.after-content', ['blog' => ['id' => $this->off], 'post' => $this->post]))
        ->toBe('');
});

test('a slot with no blog on the page renders nothing', function () {
    expect($this->slots->render('post.after-content', ['post' => $this->post]))->toBe('');
});

test('a renderer that declines renders nothing', function () {
    expect($this->slots->render('post.after-content', ['blog' => ['id' => $this->on], 'post' => null]))->toBe('');
});

test('a failing slot renders empty and is counted', function () {
    NoteSlot::$fail = true;

    expect($this->slots->render('post.after-content', ['blog' => ['id' => $this->on], 'post' => $this->post]))->toBe('')
        ->and($this->cache->get('module-failures-sample-extra'))->toBe('1');
});

test('twenty failures pause the module', function () {
    NoteSlot::$fail = true;
    for ($i = 0; $i < 20; $i++) {
        $this->slots->render('post.after-content', ['blog' => ['id' => $this->on], 'post' => $this->post]);
    }

    expect((new ModuleModel($this->db))->states()['sample-extra'])->toBe('paused');
});

test('with debug on a failing slot rethrows', function () {
    [$debugSlots] = slotRegistryFor($this->db, $this->cacheDir, debug: true);
    NoteSlot::$fail = true;

    expect(fn () => $debugSlots->render('post.after-content', ['blog' => ['id' => $this->on], 'post' => $this->post]))
        ->toThrow(RuntimeException::class, 'The note could not be drawn');
});

test('head.end links the stylesheet of modules switched on for the blog', function () {
    expect($this->slots->render('head.end', ['blog' => ['id' => $this->on]]))
        ->toContain('<link rel="stylesheet" href="/modules/sample-extra/note.css')
        ->and($this->slots->render('head.end', ['blog' => ['id' => $this->off]]))->toBe('');
});

test('body.end loads the script only for modules that rendered on the page', function () {
    $vars = ['blog' => ['id' => $this->on], 'post' => $this->post];
    $before = $this->slots->render('body.end', $vars);

    $this->slots->render('post.after-content', $vars);

    expect($before)->toBe('')
        ->and($this->slots->render('body.end', $vars))
        ->toContain('<script src="/modules/sample-extra/note.js')
        ->toContain('defer');
});

test('a platform module renders on a blog page without any per blog switch', function () {
    (new ModuleModel($this->db))->record('sample-platform', 1);
    [$slots] = slotRegistryFor($this->db, $this->cacheDir);

    expect($slots->render('body.end', ['blog' => ['id' => $this->off]]))->toContain('sample-platform-mark');
});

test('a platform module renders on a page with no blog', function () {
    (new ModuleModel($this->db))->record('sample-platform', 1);
    [$slots] = slotRegistryFor($this->db, $this->cacheDir);

    expect($slots->render('body.end', []))->toContain('sample-platform-mark');
});

test('a blog module still renders nothing on a blog that did not switch it on', function () {
    (new ModuleModel($this->db))->record('sample-platform', 1);
    [$slots] = slotRegistryFor($this->db, $this->cacheDir);

    expect($slots->render('post.after-content', ['blog' => ['id' => $this->off], 'post' => $this->post]))->toBe('');
});
