<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Deletes the module's own folders: its public map folder, or a staging folder inside it.
 */
final class Folder
{
    /**
     * Delete a folder and everything in it. A missing folder is fine.
     */
    public static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
