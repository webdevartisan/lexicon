<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Services\PublicCacheInvalidator;
use Closure;
use Framework\Database;

/**
 * What the admin may do with the road network, and when: one build at a time, only for an area Geofabrik
 * offers, and only when the disk has room for the download and the network built from it.
 */
final class RoutingControl
{
    /** Building takes about twice the road data on disk next to the download itself. */
    private const ROOM = 3;

    /** Above this much road data the build needs far more memory than a small server has. */
    public const LARGE_BYTES = 5_000_000_000;

    private const STEPS = [
        'java' => 'getting Java.',
        'graphhopper' => 'getting GraphHopper.',
        'road-data' => 'downloading the road data.',
        'import' => 'turning the road data into a network. Large areas take a long time.',
        '' => 'starting.',
    ];

    private const JOB_REASONS = [
        'request' => 'The area choice is no longer valid. Choose it again.',
        'country' => 'Geofabrik no longer offers that area. Choose it again.',
        'java' => 'Java could not be downloaded or unpacked. Nothing was changed.',
        'graphhopper' => 'GraphHopper could not be downloaded or did not match its checksum. Nothing was changed.',
        'download' => 'The road data could not be downloaded or did not match its checksum. Nothing was changed.',
        'import' => 'GraphHopper stopped while building, often for lack of memory. Nothing was changed.',
        'cancelled' => 'Cancelled. Nothing was changed.',
        'stopped' => 'The job stopped answering, probably stopped by the server. Nothing was changed.',
        'start' => 'The job could not be started. Check the PHP binary setting on the Scheduler page.',
        'error' => 'Something went wrong. Nothing was changed.',
    ];

    public function __construct(
        private RoutingFiles $files,
        private RoutingJob $job,
        private CountryIndex $areas,
        private JavaRuntime $java,
        private MapJobStarter $starter,
        private LegModel $legs,
        private Database $db,
        private PublicCacheInvalidator $pages,
        private Closure $fetchSize,
    ) {
    }

    /**
     * The areas to choose from, empty when Geofabrik's list could not be read.
     *
     * @return list<array{id: string, name: string, parent: string, url: string}>
     */
    public function areas(): array
    {
        return $this->areas->countries();
    }

    /**
     * Everything the card shows, ready to print.
     *
     * @return array{built: bool, busy: bool, region: string, port: int, lines: array<string, string>}
     */
    public function status(): array
    {
        $info = $this->files->info();
        $job = $this->job->current();

        return [
            'built' => $this->files->built(),
            'busy' => $this->job->busy(),
            'region' => (string) ($info['region'] ?? ''),
            'port' => $this->files->port(),
            'lines' => [
                'network' => $info === null ? 'No road network yet. Legs are drawn as straight dashed lines.'
                    : 'Built for '.$info['name'].' on '.substr((string) $info['builtAt'], 0, 10).', '
                        .self::size($this->files->diskBytes()).' on disk.',
                'job' => $job === null ? '' : $this->jobLine($job),
                'legs' => $this->legs->waitingCount().' legs waiting for a road route.',
            ],
        ];
    }

    /**
     * What a build of the area downloads, in words.
     *
     * @throws MapActionRefused
     */
    public function measure(RoutingRequest $request): string
    {
        [$area, $total] = $this->downloadSize($request);
        $line = 'Building '.$area['name'].' downloads '.self::size($total).'.';

        return $total > self::LARGE_BYTES
            ? $line.' That is a lot of road data: the build needs far more memory than a small server has.'
            : $line;
    }

    /**
     * Queue the build and start it in the background.
     *
     * @throws MapActionRefused
     */
    public function build(RoutingRequest $request, int $userId): string
    {
        $this->refuseWhileBusy();
        [$area, $total] = $this->downloadSize($request);
        $free = @disk_free_space(dirname($this->files->root()));
        if ($free !== false && $free < $total * self::ROOM) {
            throw new MapActionRefused('Not enough disk space: building '.$area['name'].' needs about '
                .self::size($total * self::ROOM).'.', 409);
        }

        $this->job->queueRequest('build', $request->toArray(), $userId);
        $started = $this->starter->start();
        if (!$started['ok']) {
            $this->job->fail('start');
            throw new MapActionRefused(self::JOB_REASONS['start'], 500);
        }

        return 'Building the road network for '.$area['name'].'. This page shows how it went when you reload it.';
    }

    /**
     * Stop the running build.
     */
    public function cancel(): string
    {
        if (!$this->job->busy()) {
            return 'No build is running.';
        }
        $this->job->requestCancel();

        return 'Cancelling the build.';
    }

    /**
     * Delete the network and everything it needed; legs go back to straight lines.
     *
     * @throws MapActionRefused
     */
    public function remove(): string
    {
        $this->refuseWhileBusy();
        $this->files->remove();
        $this->legs->forgetRoads();
        RoutePlanSchedule::forget($this->db);
        $this->pages->purgeAllPages();

        return 'Removed the road network. Legs are drawn as straight lines again.';
    }

    /**
     * The area and the bytes a build would download: road data, plus Java and GraphHopper when missing.
     *
     * @return array{0: array{id: string, name: string, parent: string, url: string}, 1: int}
     * @throws MapActionRefused
     */
    private function downloadSize(RoutingRequest $request): array
    {
        $area = $this->areas->find($request->region)
            ?? throw new MapActionRefused('Geofabrik does not offer that area.', 422);
        $bytes = $this->areas->size($area['url']) ?? throw new MapActionRefused(
            'Geofabrik did not say how big the road data is. Try again later.',
            503
        );

        if (!is_file($this->files->jar())) {
            $bytes += (int) ($this->fetchSize)(RoutingSetup::GRAPHHOPPER_URL);
        }

        try {
            $bytes += $this->java->path() === null ? $this->java->package()['size'] : 0;
        } catch (MapJobFailed) {
            throw new MapActionRefused('Adoptium did not say which Java this server needs. Try again later.', 503);
        }

        return [$area, $bytes];
    }

    /**
     * @throws MapActionRefused
     */
    private function refuseWhileBusy(): void
    {
        if ($this->job->busy()) {
            throw new MapActionRefused('A road network build is running. Wait for it or cancel it.', 409);
        }
    }

    /**
     * The job as one sentence.
     *
     * @param array<string, mixed> $job
     */
    private function jobLine(array $job): string
    {
        return match ($job['state']) {
            'queued' => 'Waiting to start.',
            'running' => 'Building: '.(self::STEPS[(string) ($job['step'] ?? '')] ?? 'working.'),
            'finished' => 'The last build finished.',
            default => self::JOB_REASONS[(string) ($job['reason'] ?? 'error')] ?? self::JOB_REASONS['error'],
        };
    }

    /**
     * Bytes in words, such as "36.1 MB".
     */
    private static function size(int|float $bytes): string
    {
        foreach (['GB' => 1e9, 'MB' => 1e6, 'kB' => 1e3] as $unit => $factor) {
            if ($bytes >= $factor) {
                return number_format($bytes / $factor, 1).' '.$unit;
            }
        }

        return (int) $bytes.' bytes';
    }
}
