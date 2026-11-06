<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;
use ZipArchive;

/**
 * The map's label fonts and icons, from the Protomaps basemaps-assets repository.
 *
 * Only the three fonts the map style uses and the v4 icons are kept. They are unpacked into a
 * staging folder and moved into place at the end, so a failed install leaves nothing half done.
 */
final class MapAssets
{
    private const ARCHIVE = 'https://github.com/protomaps/basemaps-assets/archive/refs/heads/main.zip';
    private const KEEP = [
        'fonts/Noto Sans Regular/',
        'fonts/Noto Sans Medium/',
        'fonts/Noto Sans Italic/',
        'sprites/v4/',
    ];
    private const FOLDERS = ['fonts', 'sprites'];

    /**
     * @param string $folder The module's public map folder, where fonts/ and sprites/ go
     * @param Closure(string, string): bool $download Save a URL to a file path
     */
    public function __construct(private string $folder, private Closure $download)
    {
    }

    /**
     * Whether the fonts or icons still need installing.
     */
    public function missing(): bool
    {
        foreach (self::FOLDERS as $folder) {
            if (!is_dir($this->base().'/'.$folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Download the archive and put the kept folders in place.
     *
     * @throws MapJobFailed With reason assets
     */
    public function install(): void
    {
        $archive = (string) tempnam(sys_get_temp_dir(), 'jm-assets');
        $staging = $this->base().'/assets.part';
        Folder::remove($staging);

        try {
            if (!($this->download)(self::ARCHIVE, $archive)) {
                throw new MapJobFailed('assets');
            }
            $this->unpack($archive, $staging);
            $this->moveInto($staging);
        } finally {
            Folder::remove($staging);
            if (is_file($archive)) {
                unlink($archive);
            }
        }
    }

    /**
     * Copy the kept entries out of the archive. Any entry name that climbs out of its folder
     * stops the whole install.
     */
    private function unpack(string $archive, string $staging): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new MapJobFailed('assets');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_contains($name, '..') || str_contains($name, '\\')) {
                    throw new MapJobFailed('assets');
                }
                // GitHub puts everything under one folder named after the repository and branch.
                $relative = substr($name, strpos($name, '/') + 1);
                if ($this->kept($relative)) {
                    $this->writeEntry($staging.'/'.$relative, (string) $zip->getFromIndex($i));
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Whether an entry is a file inside one of the kept folders.
     */
    private function kept(string $relative): bool
    {
        if (str_ends_with($relative, '/')) {
            return false;
        }
        foreach (self::KEEP as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write one file, creating its folders.
     */
    private function writeEntry(string $path, string $content): void
    {
        $folder = dirname($path);
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new MapJobFailed('assets');
        }
        if (file_put_contents($path, $content) === false) {
            throw new MapJobFailed('assets');
        }
    }

    /**
     * Swap the staged fonts and icons in for any old ones.
     */
    private function moveInto(string $staging): void
    {
        foreach (self::FOLDERS as $folder) {
            if (!is_dir($staging.'/'.$folder)) {
                throw new MapJobFailed('assets');
            }
        }
        foreach (self::FOLDERS as $folder) {
            Folder::remove($this->base().'/'.$folder);
            rename($staging.'/'.$folder, $this->base().'/'.$folder);
        }
    }

    /**
     * The public map folder.
     */
    private function base(): string
    {
        return $this->folder;
    }
}
