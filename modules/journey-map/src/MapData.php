<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * The self hosted map: one OpenStreetMap file plus fonts and icons in the module's public folder
 * (public/modules/journey-map/files/), and a note of what was downloaded (map.json) in the
 * module's storage folder.
 *
 * The admin downloads them from the module's control panel page. Until then every page
 * still shows places and stops, just without a map.
 */
class MapData
{
    private const FILE = 'journey-map.pmtiles';

    /**
     * @param string $folder The module's public folder, which may not exist before the first download
     * @param string $stateDir The module's folder under storage/, where map.json lives
     * @param string $folderUrl The public folder's web address
     */
    public function __construct(
        private string $folder,
        private string $stateDir = ROOT_PATH.'/storage/journey-map',
        private string $folderUrl = '',
    ) {
    }

    /**
     * Whether the map file is in place.
     */
    public function available(): bool
    {
        return is_file($this->path());
    }

    /**
     * Where a download writes before it replaces the live file.
     */
    public function partPath(): string
    {
        return $this->path().'.part';
    }

    /**
     * The part file's path, with its folder made, since a fresh install has no map folder yet.
     */
    public function preparePart(): string
    {
        $folder = $this->folder;
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new MapJobFailed('archive');
        }

        return $this->partPath();
    }

    /**
     * Replace the live file with a finished download in one rename, and note what it holds.
     *
     * @param array{build: string, request: array<string, mixed>} $about
     */
    public function swapIn(string $part, array $about): void
    {
        $size = (int) filesize($part);
        if (!rename($part, $this->path())) {
            throw new MapJobFailed('archive');
        }

        if (!is_dir($this->stateDir)) {
            mkdir($this->stateDir, 0775, true);
        }
        $note = $about + ['bytes' => $size, 'finishedAt' => time()];
        file_put_contents($this->stateDir.'/map.json', json_encode($note, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * What the installed map holds: build date, request, bytes and when it finished. Null without a map.
     *
     * @return array<string, mixed>|null
     */
    public function info(): ?array
    {
        $file = $this->stateDir.'/map.json';
        $info = $this->available() && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($info) ? $info : null;
    }

    /**
     * Free space on the disk that will hold the map. The folder may not exist yet, so the nearest
     * folder that does is asked.
     */
    public function freeBytes(): int
    {
        $near = $this->folder;
        while (!is_dir($near) && dirname($near) !== $near) {
            $near = dirname($near);
        }
        $free = disk_free_space($near);

        return $free === false ? 0 : (int) $free;
    }

    /**
     * Delete the map, its fonts and icons, and the note about them.
     */
    public function remove(): void
    {
        foreach ([$this->path(), $this->stateDir.'/map.json'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        Folder::remove($this->folder);
    }

    /**
     * The live map file.
     */
    private function path(): string
    {
        return $this->folder.'/'.self::FILE;
    }

    /**
     * Where the browser finds the map file, label fonts, icons and the map's drawing worker.
     * The script adds light or dark to the sprite address.
     *
     * @return array{tiles: string, glyphs: string, sprite: string, worker: string}
     */
    public function urls(): array
    {
        $site = rtrim(base_url(), '/');

        return [
            'tiles' => 'pmtiles://'.$this->fileUrl(),
            'glyphs' => $this->folderUrl.'/fonts/{fontstack}/{range}.pbf',
            'sprite' => $this->folderUrl.'/sprites/v4',
            // The CSP build runs its worker from a file, so the page needs no blob: workers.
            'worker' => $site.versioned_asset('/modules/journey-map/vendor/maplibre-gl-csp-worker.js'),
        ];
    }

    /**
     * The map file's web address.
     */
    public function fileUrl(): string
    {
        return $this->folderUrl.'/'.self::FILE;
    }

    /**
     * The files a page with a map loads, relative to public/modules/journey-map/.
     *
     * @return array{css: list<string>, js: list<string>}
     */
    public function assets(): array
    {
        return [
            'css' => ['vendor/maplibre-gl.css'],
            'js' => ['vendor/pmtiles.js', 'vendor/maplibre-gl-csp.js', 'vendor/basemaps.js', 'map-core.js'],
        ];
    }
}
