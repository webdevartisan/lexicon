<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Where the road network lives under storage/journey-map/routing: the planner program, its Java, the built
 * network and a note saying which region it covers. Nothing here is ever served to readers.
 */
final class RoutingFiles
{
    public const GRAPHHOPPER = '11.1';

    public function __construct(private string $root) {}

    /**
     * The routing folder.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * The GraphHopper program.
     */
    public function jar(): string
    {
        return $this->root.'/graphhopper-web-'.self::GRAPHHOPPER.'.jar';
    }

    /**
     * Where a downloaded Java is unpacked.
     */
    public function javaDir(): string
    {
        return $this->root.'/java';
    }

    /**
     * The java program of the downloaded runtime, or null when none was downloaded.
     */
    public function bundledJava(): ?string
    {
        $name = PHP_OS_FAMILY === 'Windows' ? 'java.exe' : 'java';
        // The macOS runtime keeps its programs inside an app bundle folder.
        foreach (['/*/bin/', '/*/Contents/Home/bin/'] as $folder) {
            foreach (glob($this->javaDir().$folder.$name) ?: [] as $java) {
                return $java;
            }
        }

        return null;
    }

    /**
     * The built road network.
     */
    public function network(): string
    {
        return $this->root.'/network';
    }

    /**
     * The settings file GraphHopper reads.
     */
    public function config(): string
    {
        return $this->root.'/config.yml';
    }

    /**
     * Whether a network was built and can plan routes.
     */
    public function built(): bool
    {
        return $this->info() !== null && is_dir($this->network());
    }

    /**
     * What the built network covers: region id and name, build time, port, download size.
     *
     * @return array<string, mixed>|null
     */
    public function info(): ?array
    {
        $file = $this->root.'/network.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && isset($data['region']) ? $data : null;
    }

    /**
     * Note what the network covers, once it is built.
     *
     * @param  array<string, mixed>  $info
     */
    public function saveInfo(array $info): void
    {
        file_put_contents($this->root.'/network.json', json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * The port the planner listens on while the job runs.
     */
    public function port(): int
    {
        return (int) ($this->info()['port'] ?? RoutingRequest::DEFAULT_PORT);
    }

    /**
     * Disk space everything under the routing folder takes.
     */
    public function diskBytes(): int
    {
        if (!is_dir($this->root)) {
            return 0;
        }

        $bytes = 0;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($items as $item) {
            $bytes += $item->isFile() ? $item->getSize() : 0;
        }

        return $bytes;
    }

    /**
     * Delete the network, the program, Java and the region files.
     */
    public function remove(): void
    {
        Folder::remove($this->root);
    }
}
