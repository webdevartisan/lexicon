<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * How much map the admin asked for: the detail level, the area, and whether a world overview comes along.
 *
 * Every value is checked against a fixed list, or is a region file this module wrote, so the tool's
 * arguments are built from known values only.
 */
final class MapRequest
{
    public const ZOOMS = [8, 9, 10];

    /** The highest zoom of the world overview; the chosen area starts one level above it. */
    private const OVERVIEW_ZOOM = 5;

    /** Bounding boxes as west, south, east, north. */
    public const PRESETS = [
        'africa' => [-26.0, -36.0, 64.0, 38.0],
        'asia' => [25.0, -12.0, 180.0, 82.0],
        'europe' => [-32.0, 34.0, 45.0, 72.0],
        'north-america' => [-170.0, 5.0, -50.0, 84.0],
        'south-america' => [-93.0, -57.0, -32.0, 13.0],
        'oceania' => [110.0, -50.0, 180.0, 0.0],
    ];

    private const REGION_FILE = '/^[a-f0-9]{40}\.geojson$/';

    /**
     * @param  array{name: string, bbox: list<float>}|null  $place  The region file's name and box, for area place
     */
    private function __construct(
        public readonly int $zoom,
        public readonly string $area,
        public readonly ?string $region,
        public readonly bool $overview,
        private readonly string $regionDir,
        private readonly ?array $place,
    ) {}

    /**
     * Check what the page sent.
     *
     * @param  array<string, mixed>  $input  zoom, area, region (for area place) and overview
     * @param  string  $regionDir  Where the page keeps the outlines it looked up
     *
     * @throws InvalidMapRequest
     */
    public static function fromInput(array $input, string $regionDir): self
    {
        $zoom = filter_var($input['zoom'] ?? null, FILTER_VALIDATE_INT);
        $area = (string) ($input['area'] ?? '');
        $errors = [];
        if (!in_array($zoom, self::ZOOMS, true)) {
            $errors['zoom'] = 'Choose a detail level from 8 to 10.';
        }
        if ($area !== 'world' && $area !== 'place' && !isset(self::PRESETS[$area])) {
            $errors['area'] = 'Choose the whole world, a continent or a place.';
        }

        $region = $area === 'place' ? (string) ($input['region'] ?? '') : null;
        $place = $region !== null ? self::readRegion($regionDir, $region) : null;
        if ($region !== null && $place === null) {
            $errors['region'] = 'Look up the place again.';
        }
        if ($errors !== []) {
            throw new InvalidMapRequest($errors);
        }

        $overview = $area !== 'world' && filter_var($input['overview'] ?? false, FILTER_VALIDATE_BOOL);

        return new self((int) $zoom, $area, $region, $overview, $regionDir, $place);
    }

    /**
     * Read a request back from the job file, checking it again as if it were new.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidMapRequest
     */
    public static function fromArray(array $data, string $regionDir): self
    {
        return self::fromInput($data, $regionDir);
    }

    /**
     * @return array{zoom: int, area: string, region: ?string, overview: bool}
     */
    public function toArray(): array
    {
        return ['zoom' => $this->zoom, 'area' => $this->area, 'region' => $this->region, 'overview' => $this->overview];
    }

    /**
     * The request in words for the page, such as "Europe, zoom 10, with world overview".
     */
    public function label(): string
    {
        $name = match ($this->area) {
            'world' => 'Whole world',
            'place' => (string) $this->place['name'],
            default => ucwords(str_replace('-', ' ', $this->area)),
        };

        return $name.', zoom '.$this->zoom.($this->overview ? ', with world overview' : '');
    }

    /**
     * The area's box as west, south, east, north, or null for the whole world.
     *
     * @return list<float>|null
     */
    public function bbox(): ?array
    {
        return match ($this->area) {
            'world' => null,
            'place' => $this->place['bbox'],
            default => self::PRESETS[$this->area],
        };
    }

    /**
     * The tool runs that produce the map, as argument lists for a process with no shell.
     *
     * With the overview, the area and the world are cut apart (the area from zoom 6, the world up to 5)
     * because the tool only merges archives that do not overlap.
     *
     * @return list<list<string>>
     */
    public function commands(string $tool, string $source, string $output, bool $dryRun): array
    {
        $dry = $dryRun ? ['--dry-run'] : [];
        $maxZoom = '--maxzoom='.$this->zoom;
        if ($this->area === 'world') {
            return [[$tool, 'extract', $source, $output, $maxZoom, ...$dry]];
        }
        if (!$this->overview) {
            return [[$tool, 'extract', $source, $output, $this->areaFlag(), $maxZoom, ...$dry]];
        }

        $minZoom = '--minzoom='.(self::OVERVIEW_ZOOM + 1);
        $runs = [
            [$tool, 'extract', $source, $output.'.area', $this->areaFlag(), $minZoom, $maxZoom, ...$dry],
            [$tool, 'extract', $source, $output.'.world', '--maxzoom='.self::OVERVIEW_ZOOM, ...$dry],
        ];
        if (!$dryRun) {
            $runs[] = [$tool, 'merge', $output.'.world', $output.'.area', $output];
        }

        return $runs;
    }

    /**
     * A continent is cut by its box; a place by its outline, so the corners of its box stay out.
     */
    private function areaFlag(): string
    {
        if ($this->area === 'place') {
            return '--region='.$this->regionDir.'/'.$this->region;
        }

        return '--bbox='.implode(',', self::PRESETS[$this->area]);
    }

    /**
     * The name and box of a region file the page wrote, or null when the name or the file is not right.
     *
     * @return array{name: string, bbox: list<float>}|null
     */
    private static function readRegion(string $regionDir, string $file): ?array
    {
        if (preg_match(self::REGION_FILE, $file) !== 1 || !is_file($regionDir.'/'.$file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($regionDir.'/'.$file), true);
        $name = is_array($data) ? trim((string) ($data['properties']['name'] ?? '')) : '';
        $bbox = is_array($data) ? self::boxOf($data['bbox'] ?? null) : null;

        return $name !== '' && $bbox !== null ? ['name' => $name, 'bbox' => $bbox] : null;
    }

    /**
     * Four numbers on the globe, west before east and south before north, or null.
     *
     * @return list<float>|null
     */
    public static function boxOf(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4 || !array_is_list($value)) {
            return null;
        }
        foreach ($value as $number) {
            if (!is_int($number) && !is_float($number)) {
                return null;
            }
        }

        [$west, $south, $east, $north] = array_map('floatval', $value);
        $onGlobe = $west >= -180 && $east <= 180 && $south >= -90 && $north <= 90;

        return $onGlobe && $west < $east && $south < $north ? [$west, $south, $east, $north] : null;
    }
}
