<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\ModuleModel;
use App\Services\Modules\ModuleAssets;
use App\Services\Modules\ModuleInstaller;
use Framework\Core\Container;
use Framework\Database;
use Framework\Modules\ModuleClassLoader;
use Framework\Modules\ModuleDuties;
use Framework\Modules\ModuleInstances;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;

/**
 * Registries over the fixture modules, each with its own throwaway cache file.
 */
final class ModuleFixtures
{
    public const PATH = ROOT_PATH.'/tests/Fixtures/modules';

    /** @var list<string> */
    private static array $files = [];

    /**
     * A fresh cache file path, deleted by cleanUp().
     */
    public static function cacheFile(): string
    {
        return self::$files[] = sys_get_temp_dir().'/lexicon-modules-'.bin2hex(random_bytes(6)).'.php';
    }

    /**
     * A registry over the fixture modules with the given install states.
     *
     * @param array<string, string> $states Module key => installed or paused
     */
    public static function registry(array $states = [], string $path = self::PATH): ModuleRegistry
    {
        return new ModuleRegistry($path, self::cacheFile(), fn () => $states);
    }

    /**
     * Make the sample-extra fixture classes loadable before any registry boots it.
     */
    public static function loadSampleExtra(): void
    {
        ModuleClassLoader::register(ModuleManifest::fromFile(self::PATH.'/sample-extra/module.json'));
    }

    /**
     * Module instances built by a container that only knows the test database.
     */
    public static function instances(Database $db): ModuleInstances
    {
        $container = new Container();
        $container->set(Database::class, fn () => $db);

        return new ModuleInstances($container);
    }

    /**
     * An installer over a modules folder, reading install states from the test database.
     */
    public static function installer(Database $db, string $path = self::PATH): ModuleInstaller
    {
        $modules = new ModuleModel($db);
        $registry = new ModuleRegistry($path, self::cacheFile(), fn () => $modules->states());

        return new ModuleInstaller($registry, $modules, $db, self::instances($db), new ModuleAssets(self::publicDir()));
    }

    /**
     * Where test installs copy module assets, instead of the real public/modules.
     */
    public static function publicDir(): string
    {
        return sys_get_temp_dir().'/lexicon-public-modules';
    }

    /**
     * Install sample-extra with its table and return duties that see it.
     */
    public static function installSampleExtra(Database $db): ModuleDuties
    {
        self::installer($db)->install('sample-extra');
        $modules = new ModuleModel($db);
        $registry = new ModuleRegistry(self::PATH, self::cacheFile(), fn () => $modules->states());

        return new ModuleDuties($registry, self::instances($db));
    }

    /**
     * Duties over an empty registry, for services whose tests do not involve modules.
     */
    public static function noDuties(Database $db): ModuleDuties
    {
        // An empty folder always compiles to the same empty cache, so one shared file is enough
        $registry = new ModuleRegistry(
            ROOT_PATH.'/tests/Fixtures/no-modules',
            sys_get_temp_dir().'/lexicon-modules-none.php',
            fn () => []
        );

        return new ModuleDuties($registry, self::instances($db));
    }

    /**
     * DDL commits on its own in MySQL, so tests that install sample-extra clean up by hand.
     */
    public static function dropSampleExtra(Database $db): void
    {
        self::dropModule($db, 'sample-extra', 'sample_extra_notes');
    }

    /**
     * Undo installing the sample-platform fixture: its table, migration rows and assets.
     */
    public static function dropSamplePlatform(Database $db): void
    {
        self::dropModule($db, 'sample-platform', 'sample_platform_items');
    }

    private static function dropModule(Database $db, string $key, string $table): void
    {
        $db->getConnection()->exec("DROP TABLE IF EXISTS {$table}");
        $db->execute('DELETE FROM migrations WHERE filename LIKE ?', ['module:'.$key.':%']);
        (new ModuleAssets(self::publicDir()))->remove($key);
    }

    /**
     * Delete every cache file handed out so far.
     */
    public static function cleanUp(): void
    {
        foreach (array_filter(self::$files, 'is_file') as $file) {
            unlink($file);
        }
        self::$files = [];
    }
}
