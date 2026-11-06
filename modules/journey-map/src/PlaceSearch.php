<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;
use Framework\Cache\CacheService;

/**
 * Finds places by name through OpenStreetMap's Nominatim search, following its usage policy:
 * calls come from this server only, at most one per second for the whole site, with a
 * User-Agent that names the site, and answers are cached so a search never repeats.
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
class PlaceSearch
{
    public const PUBLIC_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    private const MIN_LENGTH = 3;

    private const MAX_LENGTH = 120;

    private const NAME_MAX = 160;

    private const CACHE_SECONDS = 2592000;

    /** Simplifies area outlines to about 1 km, plenty for cutting a map and far smaller to store. */
    private const OUTLINE_DETAIL = 0.01;

    /**
     * @param Closure(string): ?string $fetch Returns the response body, or null when the call failed
     * @param string $throttleFile Holds the time of the last call, shared by every request
     * @param string $endpoint The public service, or a self hosted one that sets its own limits
     * @param string $contact Sent as the email parameter so the service can reach the site
     */
    public function __construct(
        private Closure $fetch,
        private string $throttleFile,
        private CacheService $cache,
        private string $endpoint = self::PUBLIC_ENDPOINT,
        private string $contact = '',
        private ?SearchStatus $status = null,
    ) {
    }

    /**
     * Up to five places matching the text, named in the given language where OpenStreetMap has it.
     *
     * @return list<array{name: string, lat: float, lng: float}>
     *
     * @throws PlaceSearchUnavailable "busy" within a second of the last call, "failed" when no usable answer came
     */
    public function search(string $query, string $locale): array
    {
        $query = $this->clean($query);
        if ($query === null) {
            return [];
        }

        $locale = $this->language($locale);
        $key = 'journey-map:place:'.sha1($locale.'|'.mb_strtolower($query));
        $cached = json_decode((string) $this->cache->get($key), true);
        if (is_array($cached)) {
            return $cached;
        }

        $places = $this->places($this->ask(['limit' => 5, 'q' => $query, 'accept-language' => $locale]));
        $this->cache->set($key, json_encode($places, JSON_THROW_ON_ERROR), self::CACHE_SECONDS);

        return $places;
    }

    /**
     * A country or region by name with its box (min lng, min lat, max lng, max lat) and its outline,
     * for cutting the map. Null when the best match has no area outline, such as a single point.
     *
     * @return array{name: string, bbox: list<float>, outline: array<string, mixed>}|null
     *
     * @throws PlaceSearchUnavailable "busy" or "failed", as for search()
     */
    public function area(string $name, string $locale): ?array
    {
        $name = $this->clean($name);
        if ($name === null) {
            return null;
        }

        $items = $this->ask([
            'limit' => 1,
            'q' => $name,
            'accept-language' => $this->language($locale),
            'polygon_geojson' => 1,
            'polygon_threshold' => self::OUTLINE_DETAIL,
        ]);

        return is_array($items[0] ?? null) ? $this->areaOf($items[0]) : null;
    }

    /**
     * Drop every saved answer, so the next searches ask the service again.
     *
     * @return int How many answers were dropped
     */
    public function forgetAnswers(): int
    {
        return $this->cache->deletePattern('journey-map:place:*');
    }
    /**
     * The default way to call the search service: curl with short timeouts and a User-Agent naming the site.
     *
     * @return Closure(string): ?string
     */
    public static function curlFetch(string $userAgent): Closure
    {
        return static function (string $url) use ($userAgent): ?string {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            return is_string($body) && $status === 200 ? $body : null;
        };
    }

    /**
     * Claim this second's call. The lock keeps two requests from both deciding they are first.
     *
     * @throws PlaceSearchUnavailable When another call went out less than a second ago
     */
    private function takeTurn(): void
    {
        $folder = dirname($this->throttleFile);
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new PlaceSearchUnavailable('failed');
        }

        $handle = fopen($this->throttleFile, 'c+');
        if ($handle === false) {
            throw new PlaceSearchUnavailable('failed');
        }

        try {
            flock($handle, LOCK_EX);
            $last = (float) stream_get_contents($handle);
            if (microtime(true) - $last < 1.0) {
                throw new PlaceSearchUnavailable('busy');
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) microtime(true));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * One call to the service: the site wide turn on the public service, then the answer as a list.
     * Every outcome is recorded for the admin page.
     *
     * @param array<string, string|int|float> $params
     * @return list<mixed>
     *
     * @throws PlaceSearchUnavailable "busy" or "failed"
     */
    private function ask(array $params): array
    {
        try {
            if ($this->endpoint === self::PUBLIC_ENDPOINT) {
                $this->takeTurn();
            }
            $params = ['format' => 'jsonv2'] + $params + ($this->contact !== '' ? ['email' => $this->contact] : []);
            $body = ($this->fetch)($this->endpoint.'?'.http_build_query($params));
            $items = $body === null ? null : json_decode($body, true);
            if (!is_array($items) || !array_is_list($items)) {
                throw new PlaceSearchUnavailable('failed');
            }
        } catch (PlaceSearchUnavailable $e) {
            if ($e->reason === 'busy') {
                $this->status?->recordBusy();
            } else {
                $this->status?->recordFailure($e->reason);
            }
            throw $e;
        }

        $this->status?->recordSuccess();

        return $items;
    }

    /**
     * The text with spaces collapsed, or null when it is too short or too long to search for.
     */
    private function clean(string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $length = mb_strlen($text);

        return $length < self::MIN_LENGTH || $length > self::MAX_LENGTH ? null : $text;
    }

    /**
     * A two letter language code, or English when it is anything else.
     */
    private function language(string $locale): string
    {
        return preg_match('/^[a-z]{2}$/', $locale) === 1 ? $locale : 'en';
    }

    /**
     * @param array<mixed> $item
     * @return array{name: string, bbox: list<float>, outline: array<string, mixed>}|null
     */
    private function areaOf(array $item): ?array
    {
        $name = $item['display_name'] ?? null;
        $box = $item['boundingbox'] ?? null;
        $outline = $item['geojson'] ?? null;
        if (!is_string($name) || $name === '' || !is_array($box) || count($box) !== 4 || !is_array($outline)) {
            return null;
        }
        $isArea = in_array($outline['type'] ?? null, ['Polygon', 'MultiPolygon'], true);
        if (!$isArea || !is_array($outline['coordinates'] ?? null)) {
            return null;
        }

        // Nominatim orders the box min lat, max lat, min lng, max lng; maps want min lng, min lat, max lng, max lat.
        [$south, $north, $west, $east] = array_map('floatval', array_values($box));
        if (abs($south) > 90 || abs($north) > 90 || abs($west) > 180 || abs($east) > 180) {
            return null;
        }

        return [
            'name' => mb_substr($name, 0, self::NAME_MAX),
            'bbox' => [$west, $south, $east, $north],
            'outline' => $outline,
        ];
    }

    /**
     * @param list<mixed> $items
     * @return list<array{name: string, lat: float, lng: float}>
     */
    private function places(array $items): array
    {
        $places = [];
        foreach ($items as $item) {
            $place = is_array($item) ? $this->place($item) : null;
            if ($place !== null) {
                $places[] = $place;
            }
        }

        return $places;
    }

    /**
     * @param array<mixed> $item
     * @return array{name: string, lat: float, lng: float}|null
     */
    private function place(array $item): ?array
    {
        $name = $item['display_name'] ?? null;
        $lat = $item['lat'] ?? null;
        $lng = $item['lon'] ?? null;
        if (!is_string($name) || $name === '' || !is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }
        if (abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return null;
        }

        return ['name' => mb_substr($name, 0, self::NAME_MAX), 'lat' => (float) $lat, 'lng' => (float) $lng];
    }
}
