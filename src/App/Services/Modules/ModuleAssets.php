<?php

declare(strict_types=1);

namespace App\Services\Modules;

use Framework\Modules\ModuleManifest;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Copies a module's assets folder to public/modules/<key>/ so the web server can serve it, and removes it again.
 */
class ModuleAssets
{
    public function __construct(private string $publicPath = ROOT_PATH.'/public/modules')
    {
    }

    /**
     * Replace the module's public folder with a fresh copy of its assets. A module with no assets gets none.
     */
    public function publish(ModuleManifest $manifest): void
    {
        $this->remove($manifest->key);

        $source = $manifest->path.'/assets';
        if (!is_dir($source)) {
            return;
        }

        $target = $this->folder($manifest->key);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($source) + 1);
            $destination = $target.'/'.str_replace('\\', '/', $relative);
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0775, true)) {
                throw new RuntimeException("Could not create {$destination}");
            }
            copy($file->getPathname(), $destination);
        }
    }

    /**
     * Delete the module's public folder, if there is one.
     */
    public function remove(string $key): void
    {
        $target = $this->folder($key);
        if (!is_dir($target)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($target);
    }

    private function folder(string $key): string
    {
        // The key decides a folder that gets deleted, so it must never reach outside the modules folder.
        if (!preg_match(ModuleManifest::KEY_PATTERN, $key)) {
            throw new RuntimeException("Invalid module key {$key}");
        }

        return $this->publicPath.'/'.$key;
    }
}
