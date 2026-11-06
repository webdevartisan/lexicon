<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * The areas Geofabrik offers road data for: countries, their parts, and larger areas such as continents,
 * read from its index file and kept for a day.
 */
final class CountryIndex
{
    private const INDEX = 'https://download.geofabrik.de/index-v1-nogeom.json';
    private const HOST = 'https://download.geofabrik.de/';
    private const KEEP_SECONDS = 86400;

    /**
     * @param Closure(string): ?array $fetchJson The decoded JSON at a URL, or null when the call failed
     * @param Closure(string): ?int $fetchSize The size a URL reports, or null when it did not answer
     * @param string $cacheFile Where the area list is kept between requests
     */
    public function __construct(private Closure $fetchJson, private Closure $fetchSize, private string $cacheFile)
    {
    }

    /**
     * Every area with a road data file, sorted by name.
     *
     * @return list<array{id: string, name: string, parent: string, url: string}>
     */
    public function countries(): array
    {
        $cached = $this->cached();
        if ($cached !== null) {
            return $cached;
        }

        $areas = $this->fromIndex(($this->fetchJson)(self::INDEX));
        if ($areas !== []) {
            if (!is_dir(dirname($this->cacheFile))) {
                mkdir(dirname($this->cacheFile), 0775, true);
            }
            file_put_contents($this->cacheFile, json_encode($areas, JSON_UNESCAPED_SLASHES));
        }

        return $areas;
    }

    /**
     * One area by its id, or null when Geofabrik has no such area.
     *
     * @return array{id: string, name: string, parent: string, url: string}|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->countries() as $area) {
            if ($area['id'] === $id) {
                return $area;
            }
        }

        return null;
    }

    /**
     * The download size of an area's file in bytes, or null when it could not be read.
     */
    public function size(string $url): ?int
    {
        return str_starts_with($url, self::HOST) ? ($this->fetchSize)($url) : null;
    }

    /**
     * Ask a server for a file's size without downloading it, over HTTPS only.
     *
     * @return Closure(string): ?int
     */
    public static function curlSize(string $userAgent): Closure
    {
        return static function (string $url) use ($userAgent): ?int {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_NOBODY => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => $userAgent,
            ]);
            $ok = curl_exec($curl) !== false && (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200;
            $bytes = (int) curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            curl_close($curl);

            return $ok && $bytes > 0 ? $bytes : null;
        };
    }

    /**
     * The kept list while it is fresh.
     *
     * @return list<array{id: string, name: string, parent: string, url: string}>|null
     */
    private function cached(): ?array
    {
        if (!is_file($this->cacheFile) || time() - (int) filemtime($this->cacheFile) > self::KEEP_SECONDS) {
            return null;
        }
        $areas = json_decode((string) file_get_contents($this->cacheFile), true);

        return is_array($areas) && $areas !== [] ? $areas : null;
    }

    /**
     * The areas from Geofabrik's index whose file sits on Geofabrik's own download host.
     *
     * @param array<string, mixed>|null $index
     * @return list<array{id: string, name: string, parent: string, url: string}>
     */
    private function fromIndex(?array $index): array
    {
        $areas = [];
        foreach ((array) ($index['features'] ?? []) as $feature) {
            $area = (array) ($feature['properties'] ?? []);
            $url = (string) ($area['urls']['pbf'] ?? '');
            $id = (string) ($area['id'] ?? '');
            if ($id === '' || !str_starts_with($url, self::HOST) || !str_ends_with($url, '.osm.pbf')) {
                continue;
            }
            $areas[] = [
                'id' => $id,
                'name' => (string) ($area['name'] ?? $id),
                'parent' => (string) ($area['parent'] ?? ''),
                'url' => $url,
            ];
        }
        usort($areas, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $areas;
    }
}
