<?php

declare(strict_types=1);

namespace App\Services\Modules;

use RuntimeException;

/**
 * Thrown when uninstalling a module would destroy data it still holds.
 */
final class ModuleHasDataException extends RuntimeException
{
    private function __construct(string $key, string $where)
    {
        parent::__construct("Module {$key} still has data in {$where}. Uninstall with --purge to erase it.");
    }

    /**
     * A blog module whose data some blogs still hold.
     *
     * @param list<int> $blogIds
     */
    public static function inBlogs(string $key, array $blogIds): self
    {
        return new self($key, 'blogs '.implode(', ', $blogIds));
    }

    /**
     * A module whose declared tables still have rows.
     *
     * @param list<string> $tables
     */
    public static function inTables(string $key, array $tables): self
    {
        return new self($key, 'tables '.implode(', ', $tables));
    }
}
