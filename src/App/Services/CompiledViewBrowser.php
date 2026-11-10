<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lists and removes individual compiled template files for the cache browser.
 */
class CompiledViewBrowser
{
    private string $path;

    /**
     * @param  string|null  $path  Folder holding the compiled files; the app's own by default
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? ROOT_PATH.'/storage/cache/views';
    }

    /**
     * List compiled templates with the source file named in each header.
     *
     * A template is stale when it was built under the opposite fragment-cache setting: that choice
     * is baked in at compile time, so it keeps behaving the old way until it is recompiled.
     *
     * @return array<int, array{id: string, source: string, size_bytes: int, compiled_at: int, stale: bool}>
     */
    public function all(bool $cacheEnabled): array
    {
        $staleMarker = $cacheEnabled ? 'Fragment cache disabled:' : 'fragment()->remember($__cacheKey';
        $views = [];

        foreach (glob($this->path.'/*.php') ?: [] as $file) {
            $contents = (string) @file_get_contents($file);
            $source = preg_match('#^// Source:\s+(.+)$#m', $contents, $match) === 1 ? trim($match[1]) : 'unknown';

            $views[] = [
                'id' => basename($file, '.php'),
                'source' => str_replace('\\', '/', str_replace(ROOT_PATH, '', $source)),
                'size_bytes' => (int) filesize($file),
                'compiled_at' => (int) filemtime($file),
                'stale' => str_contains($contents, $staleMarker),
            ];
        }

        array_multisort(array_column($views, 'source'), $views);

        return $views;
    }

    /**
     * Read the start of one compiled file.
     *
     * @param  int  $limit  Maximum number of bytes returned
     * @return string|null Null if the id is not a plain hash or the file does not exist.
     */
    public function read(string $id, int $limit): ?string
    {
        $file = $this->fileFor($id);
        if ($file === null || !is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file, false, null, 0, $limit);

        return $contents === false ? null : $contents;
    }

    /**
     * Delete one compiled file by id.
     *
     * @return bool False if the id is not a plain hash or the file does not exist.
     */
    public function delete(string $id): bool
    {
        $file = $this->fileFor($id);

        return $file !== null && is_file($file) && @unlink($file);
    }

    /**
     * Path of the compiled file for an id, or null when the id is not a file hash.
     *
     * Ids come from a form, and anything but a plain hash could point outside the views folder.
     */
    private function fileFor(string $id): ?string
    {
        return preg_match('/^[a-f0-9]{40}$/', $id) === 1 ? $this->path.'/'.$id.'.php' : null;
    }
}
