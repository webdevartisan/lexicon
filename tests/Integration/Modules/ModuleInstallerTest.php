<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use App\Models\UserModel;
use App\Services\Modules\ModuleHasDataException;
use Framework\Database;
use Framework\Modules\InvalidManifestException;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModuleFixtures;

/**
 * Schema files run DDL, which commits on its own in MySQL, so these tests cannot
 * lean on a rollback. Each one drops the fixture table and its migration rows itself.
 */
function tableExists(Database $db, string $table): bool
{
    return (bool) $db->query(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$table]
    )->fetchColumn();
}

function migrationRecorded(Database $db, string $filename): bool
{
    return (bool) $db->query('SELECT 1 FROM migrations WHERE filename = ?', [$filename])->fetchColumn();
}

function makeBlog(Database $db): int
{
    $ownerId = UserFactory::new(new UserModel($db))->create();

    return BlogFactory::new(new BlogModel($db))->create($ownerId);
}

beforeEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::dropSamplePlatform($this->db);
    $this->installer = ModuleFixtures::installer($this->db);
});

afterEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::dropSamplePlatform($this->db);
    ModuleFixtures::cleanUp();
});

test('install applies schema files once and records each', function () {
    expect($this->installer->install('sample-extra'))->toBe(['001_create.sql'])
        ->and($this->installer->install('sample-extra'))->toBe([])
        ->and(tableExists($this->db, 'sample_extra_notes'))->toBeTrue()
        ->and(migrationRecorded($this->db, 'module:sample-extra:001_create.sql'))->toBeTrue()
        ->and((new ModuleModel($this->db))->states())->toBe(['sample-extra' => 'on']);
});

test('install refuses a key with no module folder', function () {
    expect(fn () => $this->installer->install('missing-one'))->toThrow(InvalidManifestException::class);
});

test('uninstall is refused while a blog still has data', function () {
    $this->installer->install('sample-extra');
    $blogId = makeBlog($this->db);
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [$blogId, 'kept']);

    expect(fn () => $this->installer->uninstall('sample-extra', false))
        ->toThrow(ModuleHasDataException::class, (string) $blogId);
    expect(tableExists($this->db, 'sample_extra_notes'))->toBeTrue();
});

test('uninstall with purge erases every blog, drops the tables and forgets the module', function () {
    $this->installer->install('sample-extra');
    $blogId = makeBlog($this->db);
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [$blogId, 'gone']);
    (new BlogModuleModel($this->db))->enable($blogId, 'sample-extra');

    $this->installer->uninstall('sample-extra', true);

    $leftover = $this->db->query("SELECT COUNT(*) FROM migrations WHERE filename LIKE 'module:sample-extra:%'")
        ->fetchColumn();
    expect(tableExists($this->db, 'sample_extra_notes'))->toBeFalse()
        ->and((new ModuleModel($this->db))->states())->toBe([])
        ->and((new BlogModuleModel($this->db))->activeKeys($blogId))->toBe([])
        ->and((int) $leftover)->toBe(0);
});

test('install refuses a module with tables but no BlogDataEraser', function () {
    $installer = ModuleFixtures::installer($this->db, ROOT_PATH.'/tests/Fixtures/modules-bad');

    expect(fn () => $installer->install('no-eraser'))
        ->toThrow(InvalidManifestException::class, 'declares tables but does not implement BlogDataEraser');
    expect((new ModuleModel($this->db))->states())->toBe([]);
});

test('blog modules list the keys switched on for one blog', function () {
    $this->installer->install('sample-extra');
    $blogId = makeBlog($this->db);
    $model = new BlogModuleModel($this->db);

    $model->enable($blogId, 'sample-extra');
    $model->enable($blogId, 'sample-extra');
    $on = $model->activeKeys($blogId);
    $blogs = $model->blogsWithModule('sample-extra');
    $model->disable($blogId, 'sample-extra');

    expect($on)->toBe(['sample-extra'])
        ->and($blogs)->toBe([$blogId])
        ->and($model->activeKeys($blogId))->toBe([]);
});

test('install copies the module assets to the public folder and uninstall removes them', function () {
    $public = ModuleFixtures::publicDir();

    $this->installer->install('sample-extra');
    $copied = is_file($public.'/sample-extra/note.css') && is_file($public.'/sample-extra/note.js');
    $this->installer->uninstall('sample-extra', true);

    expect($copied)->toBeTrue()
        ->and(is_dir($public.'/sample-extra'))->toBeFalse();
});

test('pause and switching back on change the module state', function () {
    $this->installer->install('sample-extra');
    $modules = new ModuleModel($this->db);

    $modules->pause('sample-extra', 'slot kept failing');
    $paused = $modules->states();
    $pausedList = $modules->paused();
    $modules->switchOn('sample-extra');

    expect($paused)->toBe(['sample-extra' => 'paused'])
        ->and($pausedList)->toBe([['module_key' => 'sample-extra', 'paused_reason' => 'slot kept failing']])
        ->and($modules->paused())->toBe([])
        ->and($modules->states())->toBe(['sample-extra' => 'on']);
});

test('a platform module with tables installs without a blog data eraser', function () {
    $this->installer->install('sample-platform');

    expect((new ModuleModel($this->db))->states())->toMatchArray(['sample-platform' => 'on'])
        ->and(tableExists($this->db, 'sample_platform_items'))->toBeTrue();
});

test('uninstalling a platform module whose table has rows is refused without purge', function () {
    $this->installer->install('sample-platform');
    $this->db->execute('INSERT INTO sample_platform_items (label) VALUES (?)', ['First item']);

    expect(fn () => $this->installer->uninstall('sample-platform', false))
        ->toThrow(ModuleHasDataException::class, 'sample_platform_items')
        ->and(tableExists($this->db, 'sample_platform_items'))->toBeTrue();
});

test('purge drops the platform module tables and forgets it', function () {
    $this->installer->install('sample-platform');
    $this->db->execute('INSERT INTO sample_platform_items (label) VALUES (?)', ['First item']);

    $this->installer->uninstall('sample-platform', true);

    expect(tableExists($this->db, 'sample_platform_items'))->toBeFalse()
        ->and((new ModuleModel($this->db))->states())->not->toHaveKey('sample-platform');
});

test('an empty platform module uninstalls without purge', function () {
    $this->installer->install('sample-platform');

    $this->installer->uninstall('sample-platform', false);

    expect(tableExists($this->db, 'sample_platform_items'))->toBeFalse();
});

test('a module switched off can still be uninstalled, and its data still blocks it', function () {
    $this->installer->install('sample-extra');
    $modules = new ModuleModel($this->db);
    $modules->switchOff('sample-extra');
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [makeBlog($this->db), 'Kept']);

    expect(fn () => $this->installer->uninstall('sample-extra', false))->toThrow(ModuleHasDataException::class);

    $this->installer->uninstall('sample-extra', true);

    expect($modules->states())->not->toHaveKey('sample-extra');
});
