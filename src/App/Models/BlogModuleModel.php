<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Which modules each blog switched on.
 */
class BlogModuleModel extends AppModel
{
    protected ?string $table = 'blog_modules';

    /**
     * Keys of the modules a blog switched on.
     *
     * @return list<string>
     */
    public function activeKeys(int $blogId): array
    {
        $keys = $this->database->query(
            "SELECT module_key FROM {$this->getTable()} WHERE blog_id = ? ORDER BY module_key",
            [$blogId]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('strval', $keys ?: []);
    }

    /**
     * Switch a module on for a blog. Switching it on twice changes nothing.
     */
    public function enable(int $blogId, string $key): void
    {
        $this->database->execute(
            "INSERT IGNORE INTO {$this->getTable()} (blog_id, module_key) VALUES (?, ?)",
            [$blogId, $key]
        );
    }

    /**
     * Switch a module off for a blog, leaving the module's own data in place.
     */
    public function disable(int $blogId, string $key): void
    {
        $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE blog_id = ? AND module_key = ?",
            [$blogId, $key]
        );
    }

    /**
     * Ids of the blogs that switched a module on.
     *
     * @return list<int>
     */
    public function blogsWithModule(string $key): array
    {
        $ids = $this->database->query(
            "SELECT blog_id FROM {$this->getTable()} WHERE module_key = ? ORDER BY blog_id",
            [$key]
        )->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $ids ?: []);
    }
}
