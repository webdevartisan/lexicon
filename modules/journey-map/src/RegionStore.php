<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Keeps the outlines of the places the admin looked up, so the tool can cut the map along them.
 *
 * Each outline is checked point by point before it is written, and the file is named after its
 * content, so no name typed by anyone reaches the disk or the tool.
 */
final class RegionStore
{
    /** How deep the coordinates nest for each shape, down to one point. */
    private const DEPTH = ['Polygon' => 3, 'MultiPolygon' => 4];

    public function __construct(private string $dir)
    {
    }

    /**
     * Write a looked up area as a GeoJSON Feature.
     *
     * @param array{name: string, bbox: list<float>, outline: array<string, mixed>} $area From PlaceSearch::area
     * @return array{name: string, bbox: list<float>, file: string}|null Null when the shape is not a place on the globe
     */
    public function save(array $area): ?array
    {
        $box = MapRequest::boxOf($area['bbox'] ?? null);
        $outline = $this->outline($area['outline'] ?? null);
        $name = trim((string) ($area['name'] ?? ''));
        if ($box === null || $outline === null || $name === '') {
            return null;
        }

        $feature = ['type' => 'Feature', 'properties' => ['name' => $name], 'bbox' => $box, 'geometry' => $outline];
        $json = (string) json_encode($feature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $file = sha1($json).'.geojson';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        file_put_contents($this->dir.'/'.$file, $json);

        return ['name' => $name, 'bbox' => $box, 'file' => $file];
    }

    /**
     * Only the shape and its points, nothing else the service sent along.
     *
     * @return array{type: string, coordinates: array<mixed>}|null
     */
    private function outline(mixed $outline): ?array
    {
        $type = is_array($outline) ? ($outline['type'] ?? null) : null;
        $depth = is_string($type) ? (self::DEPTH[$type] ?? 0) : 0;
        if ($depth === 0 || !self::nested($outline['coordinates'] ?? null, $depth)) {
            return null;
        }

        return ['type' => $type, 'coordinates' => $outline['coordinates']];
    }

    /**
     * Non empty lists nested $depth deep that end in longitude, latitude pairs on the globe.
     */
    private static function nested(mixed $value, int $depth): bool
    {
        if (!is_array($value) || $value === [] || !array_is_list($value)) {
            return false;
        }
        if ($depth === 1) {
            return self::point($value);
        }
        foreach ($value as $item) {
            if (!self::nested($item, $depth - 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $point
     */
    private static function point(array $point): bool
    {
        [$lng, $lat] = $point + [null, null];
        $numbers = (is_int($lng) || is_float($lng)) && (is_int($lat) || is_float($lat));

        return count($point) <= 3 && $numbers && abs($lng) <= 180 && abs($lat) <= 90;
    }
}
