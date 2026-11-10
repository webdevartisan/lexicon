<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;
use PharData;
use Throwable;
use ZipArchive;

/**
 * The Java that runs GraphHopper: the server's own when it is version 25 or newer, otherwise a Temurin runtime
 * from Eclipse Adoptium, checked against its published digest and unpacked under the routing folder.
 */
final class JavaRuntime
{
    public const VERSION = 25;

    private const ASSETS = 'https://api.adoptium.net/v3/assets/latest/'.self::VERSION.'/hotspot';

    private const DOWNLOAD_PREFIX = 'https://github.com/adoptium/';

    /**
     * @param  Closure(string): ?array  $fetchJson
     * @param  Closure(string, string): bool  $download
     */
    public function __construct(
        private RoutingFiles $files,
        private ToolRunner $runner,
        private Closure $fetchJson,
        private Closure $download,
    ) {}

    /**
     * The java program to use, or null when neither the server nor the routing folder has a new enough one.
     */
    public function path(): ?string
    {
        $system = $this->onPath();

        return $system !== null && $this->isNewEnough($system) ? $system : $this->files->bundledJava();
    }

    /**
     * The runtime to download for this server: its address, digest and size.
     *
     * @return array{url: string, sha256: string, size: int}
     *
     * @throws MapJobFailed When Adoptium has no runtime for this system
     */
    public function package(): array
    {
        $query = http_build_query([
            'architecture' => $this->arch(),
            'image_type' => 'jre',
            'os' => $this->os(),
            'vendor' => 'eclipse',
        ]);
        $package = (array) ((($this->fetchJson)(self::ASSETS.'?'.$query))[0]['binary']['package'] ?? []);
        $url = (string) ($package['link'] ?? '');
        $sha256 = (string) ($package['checksum'] ?? '');
        if (!str_starts_with($url, self::DOWNLOAD_PREFIX) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
            throw new MapJobFailed('java');
        }

        return ['url' => $url, 'sha256' => $sha256, 'size' => (int) ($package['size'] ?? 0)];
    }

    /**
     * Download and unpack the runtime, and return its java program.
     *
     * @throws MapJobFailed
     */
    public function install(): string
    {
        $package = $this->package();
        $zip = str_ends_with($package['url'], '.zip');
        $archive = $this->files->root().'/java-download'.($zip ? '.zip' : '.tar.gz');
        Folder::remove($this->files->javaDir());
        mkdir($this->files->javaDir(), 0775, true);

        try {
            if (!($this->download)($package['url'], $archive)
                || !hash_equals($package['sha256'], (string) hash_file('sha256', $archive))) {
                throw new MapJobFailed('java');
            }
            $this->unpack($archive, $zip);
        } finally {
            if (is_file($archive)) {
                unlink($archive);
            }
        }

        return $this->files->bundledJava() ?? throw new MapJobFailed('java');
    }

    /**
     * The java program on the server's path, found by hand because starting a missing program warns.
     */
    private function onPath(): ?string
    {
        $name = PHP_OS_FAMILY === 'Windows' ? 'java.exe' : 'java';
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            $java = rtrim(trim($dir, '"'), '/\\').DIRECTORY_SEPARATOR.$name;
            if ($dir !== '' && is_file($java)) {
                return $java;
            }
        }

        return null;
    }

    /**
     * Whether "java -version" reports version 25 or newer.
     */
    private function isNewEnough(string $java): bool
    {
        $output = '';
        $code = $this->runner->run(
            [$java, '-version'],
            function (string $line) use (&$output): void {
                $output .= $line."\n";
            },
            fn (): bool => false
        );

        return $code === 0
            && preg_match('/version "(\d+)/', $output, $match) === 1
            && (int) $match[1] >= self::VERSION;
    }

    /**
     * @throws MapJobFailed
     */
    private function unpack(string $archive, bool $zip): void
    {
        try {
            if ($zip) {
                $reader = new ZipArchive();
                $ok = $reader->open($archive) === true && $reader->extractTo($this->files->javaDir());
                $reader->close();
            } else {
                $ok = (new PharData($archive))->extractTo($this->files->javaDir(), null, true);
            }
        } catch (Throwable) {
            $ok = false;
        }

        $java = $this->files->bundledJava();
        if (!$ok || $java === null) {
            throw new MapJobFailed('java');
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($java, 0755);
        }
    }

    /**
     * The system name Adoptium uses.
     */
    private function os(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'windows',
            'Darwin' => 'mac',
            default => 'linux',
        };
    }

    /**
     * The processor name Adoptium uses.
     */
    private function arch(): string
    {
        return in_array(strtolower(php_uname('m')), ['arm64', 'aarch64'], true) ? 'aarch64' : 'x64';
    }
}
