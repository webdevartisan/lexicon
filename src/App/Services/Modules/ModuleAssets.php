<?php

declare(strict_types=1);

namespace App\Services\Modules;

use FilesystemIterator;
use Framework\Modules\ModuleManifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * A module's public folder, public/modules/<key>/: its assets folder copied there so the web server can serve it,
 * plus files/ for data the module downloads or makes (a map, say). A fresh copy of the assets keeps files/;
 * uninstalling deletes the whole folder.
 */
class ModuleAssets
{
    private const FILES = 'files';

    public function __construct(private string $publicPath = ROOT_PATH.'/public/modules') {}

    /**
     * Replace the module's assets with a fresh copy, keeping its files/ folder. A module with no assets gets none.
     */
    public function publish(ModuleManifest $manifest): void
    {
        $this->clearAssets($manifest->key);

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
        $this->delete($this->folder($key));
    }

    /**
     * Where the module keeps data readers' browsers load. It may not exist yet; the module makes it.
     */
    public function filesFolder(string $key): string
    {
        return $this->folder($key).'/'.self::FILES;
    }

    /**
     * The files folder as a web address.
     */
    public function filesUrl(string $key): string
    {
        return rtrim(base_url(), '/').'/modules/'.$this->checkedKey($key).'/'.self::FILES;
    }

    /**
     * Delete everything in the module's folder except files/, which holds data a fresh copy cannot bring back.
     */
    private function clearAssets(string $key): void
    {
        foreach (glob($this->folder($key).'/{,.}*', GLOB_BRACE) ?: [] as $entry) {
            $name = basename($entry);
            if ($name !== '.' && $name !== '..' && $name !== self::FILES) {
                is_dir($entry) ? $this->delete($entry) : unlink($entry);
            }
        }
    }

    /**
     * Delete a folder and everything in it. A missing folder is fine.
     */
    private function delete(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($folder);
    }

    private function folder(string $key): string
    {
        return $this->publicPath.'/'.$this->checkedKey($key);
    }

    /**
     * The key decides a folder that gets deleted, so it must never reach outside the modules folder.
     */
    private function checkedKey(string $key): string
    {
        if (!preg_match(ModuleManifest::KEY_PATTERN, $key)) {
            throw new RuntimeException("Invalid module key {$key}");
        }

        return $key;
    }
}
