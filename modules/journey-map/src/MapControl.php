<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Services\PublicCacheInvalidator;

/**
 * What the admin may do with the map, and when: one job at a time, a download only after a size
 * check for the same choice, and only when it fits on the disk with room to spare.
 */
final class MapControl
{
    /** A download must leave a tenth of its size free, for the tool's own working files. */
    private const ROOM = 1.1;

    private const JOB_REASONS = [
        'request' => 'The map choice is no longer valid. Choose it again.',
        'no-tool' => 'The map tool is missing. Get it first.',
        'builds' => 'The list of map builds could not be read. Try again later.',
        'tool' => 'The map tool stopped with an error. Nothing was changed.',
        'size' => 'The map tool did not report a size.',
        'archive' => 'The download is not a map file, so it was not used. Nothing was changed.',
        'assets' => 'The map fonts and icons could not be installed. Nothing was changed.',
        'cancelled' => 'Cancelled. Nothing was changed.',
        'stopped' => 'The job stopped answering, probably stopped by the server. Nothing was changed.',
        'start' => 'The job could not be started. Check the PHP binary setting on the Scheduler page.',
        'error' => 'Something went wrong. Nothing was changed.',
    ];

    private const TOOL_REASONS = [
        'system' => 'The map tool has no build for this server.',
        'programs' => 'This server does not let the site run programs.',
        'release' => 'The tool release could not be read from GitHub. Try again later.',
        'download' => 'The tool could not be downloaded. Try again later.',
        'digest' => 'The download did not match the checksum GitHub publishes, so it was not kept.',
        'archive' => 'The download held something unexpected, so it was not kept.',
    ];

    public function __construct(
        private MapJob $job,
        private MapData $data,
        private MapTool $tool,
        private MapJobStarter $starter,
        private PublicCacheInvalidator $pages,
        private RegionStore $regions,
    ) {}

    /**
     * Check the admin's choice of detail and area.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws InvalidMapRequest
     */
    public function request(array $input): MapRequest
    {
        return MapRequest::fromInput($input, $this->job->regionDir());
    }

    /**
     * The map file's web address, for the server check.
     */
    public function fileUrl(): string
    {
        return $this->data->fileUrl();
    }

    /**
     * What the page needs to draw the installed map with its area outlined, or null without a map.
     * The box is null for a world map, which has no edge to outline.
     *
     * @return array{urls: array<string, string>, bbox: ?list<float>,
     *     files: array{css: list<string>, js: list<string>}}|null
     */
    public function preview(): ?array
    {
        $map = $this->describeMap();
        if ($map === null || !$this->data->available()) {
            return null;
        }

        return ['urls' => $this->data->urls(), 'bbox' => $map['bbox'], 'files' => $this->data->assets()];
    }

    /**
     * Everything the page shows about the tool, the job and the installed map, ready to print.
     *
     * @return array{tool: array<string, mixed>, job: ?array<string, mixed>, map: ?array<string, mixed>,
     *     busy: bool, lines: array{map: string, job: string, size: string, replace: string}}
     */
    public function status(): array
    {
        $job = $this->job->current();
        $described = $job === null ? null : $this->describeJob($job);
        $map = $this->describeMap();

        return [
            'tool' => [
                'installed' => $this->tool->path() !== null,
                'version' => $this->tool->version(),
                'programs' => $this->tool->canRunPrograms(),
                'supported' => $this->tool->assetSuffix() !== null,
            ],
            'job' => $described,
            'map' => $map,
            'busy' => $this->job->busy(),
            'lines' => [
                'map' => $map === null ? 'No map yet. Pages show places and stops without a map.'
                    : 'Installed: '.$map['label'].'. '.$map['size'].', map from '.$map['build'].'.',
                'job' => $described['line'] ?? '',
                'size' => $this->sizeLine($job),
                'replace' => $map === null ? ''
                    : 'This replaces the installed map, '.$map['label'].'. Only one map is kept.',
            ],
        ];
    }

    /**
     * Download, check and store the tool.
     *
     * @throws MapActionRefused With the reason in words
     */
    public function installTool(): string
    {
        try {
            return 'Installed the map tool '.$this->tool->install().'.';
        } catch (MapToolException $e) {
            throw new MapActionRefused(self::TOOL_REASONS[$e->reason] ?? self::TOOL_REASONS['download']);
        }
    }

    /**
     * Keep a looked up outline for the area choice.
     *
     * @param  array{name: string, bbox: list<float>, outline: array<string, mixed>}  $area
     * @return array{name: string, bbox: list<float>, file: string}
     *
     * @throws MapActionRefused When the outline is not a usable area
     */
    public function saveRegion(array $area): array
    {
        return $this->regions->save($area) ?? throw new MapActionRefused('No area with an outline was found.', 404);
    }

    /**
     * Start a size check.
     *
     * @throws MapActionRefused
     */
    public function check(MapRequest $request, int $userId): string
    {
        $this->refuseWhileBusy();
        $this->requireTool();
        $this->job->queue('size', $request, $userId);

        return $this->launch();
    }

    /**
     * Start a download, only after a size check of this exact choice and only when it fits.
     *
     * @throws MapActionRefused
     */
    public function download(MapRequest $request, int $userId): string
    {
        $this->refuseWhileBusy();
        $this->requireTool();
        $size = $this->job->sizeFor($request) ?? throw new MapActionRefused('Check the size of this map first.');
        $free = $this->data->freeBytes();
        if ($free < $size * self::ROOM) {
            throw new MapActionRefused(
                'The map needs '.self::bytes((int) ($size * self::ROOM)).' with room to spare, and '
                .self::bytes($free).' is free.'
            );
        }

        $this->job->queue('download', $request, $userId);

        return $this->launch();
    }

    /**
     * Ask the running job to stop.
     *
     * @throws MapActionRefused When nothing runs
     */
    public function cancel(): string
    {
        if (!$this->job->busy()) {
            throw new MapActionRefused('No map job is running.', 409);
        }
        $this->job->requestCancel();

        return 'Stopping. The old map stays as it was.';
    }

    /**
     * Delete the map, its fonts and icons. Pages drop their map at once.
     *
     * @throws MapActionRefused While a job runs
     */
    public function remove(): string
    {
        $this->refuseWhileBusy();
        $this->data->remove();
        $this->pages->purgeAllPages();

        return 'Removed the map, its fonts and its icons.';
    }

    /**
     * Sizes the way the tool writes them, in SI units: 4.1 GB.
     */
    public static function bytes(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $size = (float) max(0, $bytes);
        $unit = 0;
        while ($size >= 1000 && $unit < count($units) - 1) {
            $size /= 1000;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1)).' '.$units[$unit];
    }

    /**
     * Start the queued job. A job that cannot start is marked failed, so it never blocks the next one.
     */
    private function launch(): string
    {
        $started = $this->starter->start();
        if (!$started['ok']) {
            $this->job->fail('start');
            throw new MapActionRefused($started['message'], 503);
        }

        return $started['message'];
    }

    /**
     * @throws MapActionRefused
     */
    private function refuseWhileBusy(): void
    {
        if ($this->job->busy()) {
            throw new MapActionRefused('A map job is already running.', 409);
        }
    }

    /**
     * @throws MapActionRefused
     */
    private function requireTool(): void
    {
        if ($this->tool->path() === null) {
            throw new MapActionRefused('Get the map tool first.');
        }
    }

    /**
     * The job for the page: its state, progress and one sentence about it.
     *
     * @param  array<string, mixed>  $job
     * @return array{state: string, kind: string, percent: int, line: string}
     */
    private function describeJob(array $job): array
    {
        $state = (string) $job['state'];
        $kind = (string) ($job['kind'] ?? 'download');
        $label = $this->label((array) ($job['request'] ?? []));
        $reason = (string) ($job['reason'] ?? '');
        $percent = (int) ($job['percent'] ?? 0);

        $line = match ($state) {
            'queued' => 'Waiting to start: '.$label.'.',
            'running' => ($kind === 'size' ? 'Measuring ' : 'Downloading ').$label.'. '
                .trim(($job['step'] ?? '').', '.$percent.'%', ', ').'.',
            'finished' => ($kind === 'size' ? 'Size check done: ' : 'Downloaded ').$label.'.',
            default => self::JOB_REASONS[$reason] ?? self::JOB_REASONS['error'],
        };

        return ['state' => $state, 'kind' => $kind, 'percent' => $percent, 'line' => $line];
    }

    /**
     * The installed map: its choice in words, size, build date and box, or null without a map.
     *
     * @return array<string, mixed>|null
     */
    private function describeMap(): ?array
    {
        $info = $this->data->info();
        if ($info === null) {
            return null;
        }

        $request = $this->requestOf((array) ($info['request'] ?? []));

        return [
            'label' => $request?->label() ?? 'Unknown area',
            'size' => self::bytes((int) ($info['bytes'] ?? 0)),
            'build' => (string) ($info['build'] ?? ''),
            'bbox' => $request?->bbox(),
        ];
    }

    /**
     * The last measured size and the free space, in one sentence.
     *
     * @param  array<string, mixed>|null  $job
     */
    private function sizeLine(?array $job): string
    {
        $free = 'Free space: '.self::bytes($this->data->freeBytes()).'.';
        $size = $job['size'] ?? null;
        if (!is_array($size) || !is_array($size['request'] ?? null)) {
            return 'No size check yet. '.$free;
        }

        return 'Last size check: '.$this->label($size['request']).', '.self::bytes((int) $size['bytes']).'. '.$free;
    }

    /**
     * A stored choice in words.
     *
     * @param  array<string, mixed>  $request
     */
    private function label(array $request): string
    {
        return $this->requestOf($request)?->label() ?? 'Unknown area';
    }

    /**
     * A stored choice, or null when it no longer passes the checks (a region file was removed).
     *
     * @param  array<string, mixed>  $request
     */
    private function requestOf(array $request): ?MapRequest
    {
        try {
            return MapRequest::fromArray($request, $this->job->regionDir());
        } catch (InvalidMapRequest) {
            return null;
        }
    }
}
