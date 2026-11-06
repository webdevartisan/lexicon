<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Models\ModuleModel;
use Framework\Database;
use Framework\Modules\BlogDataEraser;
use Framework\Modules\InvalidManifestException;
use Framework\Modules\ModuleInstances;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;
use Framework\Modules\ModuleUninstaller;

/**
 * Installs and uninstalls modules: their schema files, public assets, state row and the registry cache.
 */
class ModuleInstaller
{
    private const TABLE_PATTERN = '/^[a-z][a-z0-9_]{1,63}$/';

    public function __construct(
        private ModuleRegistry $registry,
        private ModuleModel $modules,
        private Database $db,
        private ModuleInstances $instances,
        private ModuleAssets $assets,
    ) {
    }

    /**
     * Apply a module's new schema files and mark it installed.
     *
     * @param string $key The module key
     * @return list<string> Schema files applied on this run, empty when all were applied before
     * @throws InvalidManifestException When the module is missing or breaks the data rules
     */
    public function install(string $key): array
    {
        // A folder copied in since the cache was written would be invisible otherwise.
        $this->registry->rebuild();
        $manifest = $this->manifest($key);

        // Blog deletion has to reach a blog module's rows; a platform module's rows belong to no blog.
        $needsEraser = $manifest->scope === 'blog' && $manifest->tables !== [];
        if ($needsEraser && !$this->instances->for($manifest) instanceof BlogDataEraser) {
            throw new InvalidManifestException("Module {$key} declares tables but does not implement BlogDataEraser");
        }

        $applied = $this->applySchema($manifest);
        $this->assets->publish($manifest);
        $this->modules->record($key, $manifest->version);
        $this->registry->rebuild();

        return $applied;
    }

    /**
     * Remove a module, its tables, its public folder, whatever its own cleanup deletes, and its state.
     *
     * @param string $key The module key
     * @param bool $purge Erase the data instead of refusing when some exists
     * @throws ModuleHasDataException When the module still holds data and $purge is false
     */
    public function uninstall(string $key, bool $purge): void
    {
        $manifest = $this->manifest($key);
        $module = $this->instances->for($manifest);

        if ($module instanceof BlogDataEraser) {
            $this->clearBlogData($key, $module, $purge);
        } elseif (!$purge) {
            $this->refuseWhileTablesHaveRows($manifest);
        }

        if ($module instanceof ModuleUninstaller) {
            $module->uninstall();
        }
        $this->dropTables($manifest);
        $this->assets->remove($key);
        $this->db->execute('DELETE FROM migrations WHERE filename LIKE ?', ['module:'.$key.':%']);
        $this->modules->forget($key);
        $this->registry->rebuild();
    }

    private function manifest(string $key): ModuleManifest
    {
        return $this->registry->find($key)
            ?? throw new InvalidManifestException("No valid module named {$key}");
    }

    /**
     * @return list<string>
     */
    private function applySchema(ModuleManifest $manifest): array
    {
        $files = glob($manifest->path.'/schema/*.sql') ?: [];
        natsort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            $record = "module:{$manifest->key}:{$name}";
            if ($this->db->query('SELECT 1 FROM migrations WHERE filename = ?', [$record])->fetchColumn()) {
                continue;
            }

            $this->db->getConnection()->exec((string) file_get_contents($file));
            $this->db->execute('INSERT INTO migrations (filename) VALUES (?)', [$record]);
            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * Every blog is checked, not only those with the module on, because switching it off keeps the data.
     */
    private function clearBlogData(string $key, BlogDataEraser $module, bool $purge): void
    {
        $blogIds = $this->db->query('SELECT id FROM blogs ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        $blogIds = array_map('intval', $blogIds);
        $withData = array_values(array_filter($blogIds, fn (int $id) => $module->hasBlogData($id)));

        if ($withData !== [] && !$purge) {
            throw ModuleHasDataException::inBlogs($key, $withData);
        }

        foreach ($withData as $blogId) {
            $module->eraseBlog($blogId);
        }
    }

    /**
     * @throws ModuleHasDataException When any declared table still has a row
     */
    private function refuseWhileTablesHaveRows(ModuleManifest $manifest): void
    {
        $filled = array_values(array_filter(
            $manifest->tables,
            fn (string $table) => $this->tableExists($this->checkedTable($manifest, $table))
                && $this->db->query("SELECT 1 FROM `{$table}` LIMIT 1")->fetchColumn() !== false
        ));

        if ($filled !== []) {
            throw ModuleHasDataException::inTables($manifest->key, $filled);
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->db->query(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        )->fetchColumn();
    }

    private function dropTables(ModuleManifest $manifest): void
    {
        foreach (array_reverse($manifest->tables) as $table) {
            $this->db->getConnection()->exec("DROP TABLE IF EXISTS `{$this->checkedTable($manifest, $table)}`");
        }
    }

    /**
     * Table names end up inside SQL, which cannot bind identifiers, so only plain names pass.
     *
     * @throws InvalidManifestException When the name is not a plain table name
     */
    private function checkedTable(ModuleManifest $manifest, string $table): string
    {
        if (!preg_match(self::TABLE_PATTERN, $table)) {
            throw new InvalidManifestException("Module {$manifest->key} lists an invalid table name");
        }

        return $table;
    }
}
