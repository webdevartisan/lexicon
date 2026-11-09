<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;
use PharData;
use Throwable;
use ZipArchive;

/**
 * The pmtiles command line tool that cuts the map down and downloads it.
 *
 * Installed from the official go-pmtiles release for this server's system, and only when the file
 * matches the SHA-256 digest GitHub publishes for it. It lives outside public/.
 */
final class MapTool
{
    private const RELEASE_API = 'https://api.github.com/repos/protomaps/go-pmtiles/releases/latest';

    private const DOWNLOAD_PREFIX = 'https://github.com/protomaps/go-pmtiles/releases/download/';

    private const SYSTEMS = ['Linux' => 'Linux', 'Darwin' => 'Darwin', 'Windows' => 'Windows'];

    private const CHIPS = [
        'x86_64' => 'x86_64', 'amd64' => 'x86_64', 'AMD64' => 'x86_64',
        'arm64' => 'arm64', 'aarch64' => 'arm64', 'ARM64' => 'arm64',
    ];

    private string $arch;

    /**
     * @param  Closure(string): ?array  $fetchJson  The decoded JSON at a URL, or null when the call failed
     * @param  Closure(string, string): bool  $download  Save a URL to a file path
     * @param  string|null  $arch  The chip name, php_uname('m') when not given
     */
    public function __construct(
        private string $binDir,
        private Closure $fetchJson,
        private Closure $download,
        private string $os = PHP_OS_FAMILY,
        ?string $arch = null,
    ) {
        $this->arch = $arch ?? php_uname('m');
    }

    /**
     * Whether PHP may start programs here. Many shared hosts switch proc_open off.
     */
    public function canRunPrograms(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /**
     * The installed tool, or null when it is not there yet.
     */
    public function path(): ?string
    {
        $file = $this->binDir.DIRECTORY_SEPARATOR.$this->binaryName();

        return is_file($file) ? $file : null;
    }

    /**
     * The release tag of the installed tool, such as v1.31.2.
     */
    public function version(): ?string
    {
        $file = $this->binDir.'/version.txt';

        return $this->path() !== null && is_file($file) ? trim((string) file_get_contents($file)) : null;
    }

    /**
     * The end of the release file name for this system, such as _Linux_x86_64.tar.gz, or null when
     * the project publishes no build for it.
     */
    public function assetSuffix(): ?string
    {
        $system = self::SYSTEMS[$this->os] ?? null;
        $chip = self::CHIPS[$this->arch] ?? null;
        if ($system === null || $chip === null) {
            return null;
        }

        return '_'.$system.'_'.$chip.($system === 'Linux' ? '.tar.gz' : '.zip');
    }

    /**
     * Download, check and install the newest release.
     *
     * @return string The installed release tag
     *
     * @throws MapToolException With the reason the page shows
     */
    public function install(): string
    {
        $suffix = $this->assetSuffix() ?? throw new MapToolException('system');
        if (!$this->canRunPrograms()) {
            throw new MapToolException('programs');
        }

        $release = ($this->fetchJson)(self::RELEASE_API);
        $asset = $this->asset(is_array($release) ? $release : [], $suffix);
        $archive = $this->fetchArchive($asset, $suffix);

        try {
            $this->store($this->binaryFrom($archive, $suffix));
        } finally {
            unlink($archive);
        }

        $tag = (string) ($release['tag_name'] ?? '');
        file_put_contents($this->binDir.'/version.txt', $tag);

        return $tag;
    }

    /**
     * Read a JSON answer from GitHub's release API.
     *
     * @return Closure(string): ?array
     */
    public static function curlJson(string $userAgent): Closure
    {
        return static function (string $url) use ($userAgent): ?array {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);
            $data = is_string($body) && $status === 200 ? json_decode($body, true) : null;

            return is_array($data) ? $data : null;
        };
    }

    /**
     * Save a file from GitHub to disk. GitHub answers with a redirect to its file host, so redirects
     * are followed, over HTTPS only.
     *
     * @param  int  $timeout  Seconds the whole download may take
     * @return Closure(string, string): bool
     */
    public static function curlDownload(string $userAgent, int $timeout): Closure
    {
        return static function (string $url, string $path) use ($userAgent, $timeout): bool {
            $file = fopen($path, 'wb');
            if ($file === false) {
                return false;
            }

            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => $userAgent,
            ]);
            $done = curl_exec($curl) === true && (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200;
            curl_close($curl);
            fclose($file);

            return $done;
        };
    }

    /**
     * The release file for this system, from the official download address, with its digest.
     *
     * @param  array<string, mixed>  $release
     * @return array{url: string, sha256: string}
     */
    private function asset(array $release, string $suffix): array
    {
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            $name = (string) ($asset['name'] ?? '');
            $url = (string) ($asset['browser_download_url'] ?? '');
            if (!str_ends_with($name, $suffix)) {
                continue;
            }
            if (!str_starts_with($url, self::DOWNLOAD_PREFIX) || str_contains($url, '..')) {
                throw new MapToolException('release');
            }
            if (preg_match('/^sha256:([a-f0-9]{64})$/', (string) ($asset['digest'] ?? ''), $match) !== 1) {
                throw new MapToolException('digest');
            }

            return ['url' => $url, 'sha256' => $match[1]];
        }

        throw new MapToolException('release');
    }

    /**
     * Download the release file and keep it only when its digest matches.
     *
     * @param  array{url: string, sha256: string}  $asset
     * @return string Path of the checked archive
     */
    private function fetchArchive(array $asset, string $suffix): string
    {
        if (!is_dir($this->binDir) && !mkdir($this->binDir, 0775, true) && !is_dir($this->binDir)) {
            throw new MapToolException('download');
        }

        $archive = $this->binDir.'/download'.(str_ends_with($suffix, '.zip') ? '.zip' : '.tar.gz');
        if (!($this->download)($asset['url'], $archive) || !is_file($archive)) {
            throw new MapToolException('download');
        }
        if (!hash_equals($asset['sha256'], (string) hash_file('sha256', $archive))) {
            unlink($archive);
            throw new MapToolException('digest');
        }

        return $archive;
    }

    /**
     * The tool's bytes, read only from the entry at the top of the archive; nothing else is unpacked.
     */
    private function binaryFrom(string $archive, string $suffix): string
    {
        try {
            if (str_ends_with($suffix, '.zip')) {
                $zip = new ZipArchive();
                $content = $zip->open($archive) === true ? $zip->getFromName($this->binaryName()) : false;
                $zip->close();
            } else {
                $tar = new PharData($archive);
                $content = isset($tar[$this->binaryName()]) ? $tar[$this->binaryName()]->getContent() : false;
            }
        } catch (Throwable) {
            $content = false;
        }

        return is_string($content) && $content !== '' ? $content : throw new MapToolException('archive');
    }

    /**
     * Write the tool next to its old copy and swap it in, so a broken write never leaves no tool at all.
     */
    private function store(string $binary): void
    {
        $target = $this->binDir.DIRECTORY_SEPARATOR.$this->binaryName();
        $temp = $target.'.new';
        if (file_put_contents($temp, $binary) === false || !chmod($temp, 0755) || !rename($temp, $target)) {
            throw new MapToolException('download');
        }
    }

    /**
     * The tool's file name, with .exe on Windows as the release ships it.
     */
    private function binaryName(): string
    {
        return $this->os === 'Windows' ? 'pmtiles.exe' : 'pmtiles';
    }
}
