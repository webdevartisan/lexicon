<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * Builds the road network for one area: gets Java and GraphHopper when missing, downloads the area's road data
 * from Geofabrik, checks every file against its published digest, imports it, and only then swaps the new
 * network in. A failed or cancelled build leaves the old network as it was.
 */
final class RoutingSetup
{
    public const GRAPHHOPPER_URL = 'https://repo1.maven.org/maven2/com/graphhopper/graphhopper-web/'
        .RoutingFiles::GRAPHHOPPER.'/graphhopper-web-'.RoutingFiles::GRAPHHOPPER.'.jar';

    private const MIN_HEAP_MB = 2048;

    /**
     * @param  Closure(string, string): bool  $download  Save a URL to a path
     */
    public function __construct(
        private RoutingFiles $files,
        private CountryIndex $areas,
        private JavaRuntime $java,
        private ToolRunner $runner,
        private Closure $download,
    ) {}

    /**
     * Build the network the request asks for, reporting each step to the job.
     *
     * @throws MapJobFailed With one word the admin page turns into a sentence
     */
    public function run(RoutingRequest $request, MapJob $job): void
    {
        $area = $this->areas->find($request->region) ?? throw new MapJobFailed('country');
        if (!is_dir($this->files->root())) {
            mkdir($this->files->root(), 0775, true);
        }

        $job->progress(0, 'java');
        $java = $this->java->path() ?? $this->java->install();
        $job->progress(0, 'graphhopper');
        $this->fetchGraphHopper();
        $job->progress(0, 'road-data');
        $roadData = $this->fetchRoadData($area['url']);

        try {
            $this->import($java, $roadData, $request, $job);
        } finally {
            unlink($roadData);
            Folder::remove($this->files->network().'.new');
        }

        $this->files->saveInfo([
            'region' => $area['id'],
            'name' => $area['name'],
            'port' => $request->port,
            'builtAt' => gmdate('c'),
        ]);
    }

    /**
     * Download GraphHopper once, checked against Maven's SHA-1 file.
     */
    private function fetchGraphHopper(): void
    {
        if (!is_file($this->files->jar())) {
            $this->fetchChecked(self::GRAPHHOPPER_URL, $this->files->jar(), 'sha1', 'graphhopper');
        }
    }

    /**
     * Download the area's road data, checked against Geofabrik's MD5 file.
     */
    private function fetchRoadData(string $url): string
    {
        $path = $this->files->root().'/road-data.osm.pbf';
        $this->fetchChecked($url, $path, 'md5', 'download');

        return $path;
    }

    /**
     * Download into a part file, compare it with the digest published next to it, then move it in place.
     *
     * @param  string  $algo  sha1 or md5, also the digest file's extension
     */
    private function fetchChecked(string $url, string $path, string $algo, string $reason): void
    {
        $part = $path.'.part';
        $digestFile = $path.'.'.$algo;
        try {
            $ok = ($this->download)($url.'.'.$algo, $digestFile) && ($this->download)($url, $part);
            if (!$ok || !hash_equals(self::digest($digestFile, $algo), (string) hash_file($algo, $part))
                || !rename($part, $path)) {
                throw new MapJobFailed($reason);
            }
        } finally {
            foreach ([$part, $digestFile] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * The digest at the start of a published digest file, which may name the file after it.
     */
    private static function digest(string $file, string $algo): string
    {
        $length = $algo === 'md5' ? 32 : 40;
        $text = strtolower(trim((string) file_get_contents($file)));

        return preg_match('/^[a-f0-9]{'.$length.'}/', $text, $match) === 1 ? $match[0] : 'missing';
    }

    /**
     * Import the road data into a new network folder, then put it in place of the old one.
     */
    private function import(string $java, string $roadData, RoutingRequest $request, MapJob $job): void
    {
        $staging = $this->files->network().'.new';
        Folder::remove($staging);
        GraphHopperConfig::write($this->files, $staging, $request->port, $roadData);
        $job->progress(0, 'import');

        $heap = '-Xmx'.$this->heapMb((int) filesize($roadData)).'m';
        $argv = [$java, $heap, '-jar', $this->files->jar(), 'import', $this->files->config()];
        $code = $this->runner->run(
            $argv,
            fn () => $job->progress(0, 'import'),
            fn (): bool => $job->cancelRequested()
        );
        if ($code === ToolRunner::STOPPED) {
            throw new MapJobFailed('cancelled');
        }
        if ($code !== 0 || !is_dir($staging)) {
            throw new MapJobFailed('import');
        }

        Folder::remove($this->files->network());
        if (!rename($staging, $this->files->network())) {
            throw new MapJobFailed('import');
        }
        GraphHopperConfig::write($this->files, $this->files->network(), $request->port);
    }

    /**
     * Memory for the import: eight times the road data, at least 2 GB, at most three quarters of the server's.
     */
    public function heapMb(int $roadDataBytes): int
    {
        $wanted = max(self::MIN_HEAP_MB, (int) ceil($roadDataBytes * 8 / 1048576));
        $total = self::memoryMb();

        return $total === null ? $wanted : max(self::MIN_HEAP_MB, min($wanted, intdiv($total * 3, 4)));
    }

    /**
     * The server's memory in MB where the system tells PHP (Linux), otherwise null.
     */
    private static function memoryMb(): ?int
    {
        $info = is_readable('/proc/meminfo') ? (string) file_get_contents('/proc/meminfo') : '';

        return preg_match('/^MemTotal:\s+(\d+) kB/m', $info, $match) === 1 ? intdiv((int) $match[1], 1024) : null;
    }
}
