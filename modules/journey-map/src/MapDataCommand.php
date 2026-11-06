<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Interfaces\SchedulableCommandInterface;
use App\Services\PublicCacheInvalidator;
use Closure;
use Throwable;

/**
 * Measures or downloads the journey map in the background, as the admin queued it on the module's page.
 *
 * A run with nothing queued downloads the last map again from the newest build, so the Scheduler page
 * can keep the map fresh. The live file is only ever replaced by a finished, checked download.
 *
 * Usage: php cli journey-map:map-data
 */
final class MapDataCommand implements SchedulableCommandInterface
{
    public const NAME = 'journey-map:map-data';

    private const BUILDS = 'https://build-metadata.protomaps.dev/builds.json';
    private const BUILD_HOST = 'https://build.protomaps.com/';
    private const BUILD_KEY = '/^(\d{4})(\d{2})(\d{2})\.pmtiles$/';
    private const SIZE_LINE = '/archive size of ([\d.]+) ([kMGT]?B)/';
    private const UNITS = ['B' => 1, 'kB' => 1e3, 'MB' => 1e6, 'GB' => 1e9, 'TB' => 1e12];

    /**
     * @param Closure(string): ?array $fetchJson The decoded JSON at a URL, or null when the call failed
     */
    public function __construct(
        private MapJob $job,
        private MapData $data,
        private MapAssets $assets,
        private ToolRunner $runner,
        private MapTool $tool,
        private PublicCacheInvalidator $pages,
        private Closure $fetchJson,
    ) {
    }

    /**
     * The name on the Scheduler page.
     */
    public static function scheduleLabel(): string
    {
        return 'Download or refresh the journey map';
    }

    /**
     * Nothing to choose: what to download is set on the module's page.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * Run the queued job, or refresh the installed map when nothing is queued.
     */
    public function handle(array $arguments = []): int
    {
        if (!$this->job->lock()) {
            echo "Another map job is running.\n";

            return 1;
        }

        try {
            $next = $this->nextJob();
            if ($next === null) {
                echo "No map to refresh.\n";

                return 0;
            }

            return $this->runJob($next['kind'], $next['request']);
        } catch (InvalidMapRequest) {
            return $this->failed('request');
        } finally {
            $this->job->unlock();
        }
    }

    /**
     * The queued job, or a refresh of the installed map, or null when there is neither.
     *
     * @return array{kind: string, request: MapRequest}|null
     * @throws InvalidMapRequest
     */
    private function nextJob(): ?array
    {
        $job = $this->job->current();
        if (($job['state'] ?? null) === 'queued') {
            return [
                'kind' => (string) $job['kind'],
                'request' => MapRequest::fromArray((array) $job['request'], $this->job->regionDir()),
            ];
        }

        $info = $this->data->info();
        if ($info === null) {
            return null;
        }

        $request = MapRequest::fromArray((array) ($info['request'] ?? []), $this->job->regionDir());
        $this->job->queue('download', $request, 0);

        return ['kind' => 'download', 'request' => $request];
    }

    /**
     * Run one job to its end and record how it went. Partial files never outlive the run.
     */
    private function runJob(string $kind, MapRequest $request): int
    {
        $this->job->begin((int) getmypid());

        try {
            if ($kind === 'size') {
                $this->measure($request);
            } else {
                $this->download($request);
            }
            $this->job->finish();
            echo "Done: {$request->label()}\n";

            return 0;
        } catch (MapJobFailed $e) {
            return $this->failed($e->reason);
        } catch (Throwable $e) {
            return $this->failed('error');
        } finally {
            $this->removeParts();
        }
    }

    /**
     * Ask the tool for the size of the request without downloading it.
     */
    private function measure(MapRequest $request): void
    {
        $runs = $request->commands($this->toolPath(), $this->latestBuild()['url'], $this->data->partPath(), true);
        $total = 0;
        foreach ($runs as $index => $argv) {
            $total += $this->archiveSize($this->runStep($argv, $index, count($runs)));
        }

        $this->job->sized($total);
    }

    /**
     * Download into the part file, check it, add fonts and icons if needed, then swap it in.
     */
    private function download(MapRequest $request): void
    {
        $build = $this->latestBuild();
        $part = $this->data->preparePart();
        $runs = $request->commands($this->toolPath(), $build['url'], $part, false);
        foreach ($runs as $index => $argv) {
            $this->runStep($argv, $index, count($runs));
        }

        $this->checkArchive($part);
        if ($this->assets->missing()) {
            $this->assets->install();
        }

        $this->data->swapIn($part, ['build' => $build['date'], 'request' => $request->toArray()]);
        $this->pages->purgeAllPages();
    }

    /**
     * Run one tool step, passing its percentages to the job file.
     *
     * @param list<string> $argv
     * @return list<string> The last lines it printed
     */
    private function runStep(array $argv, int $index, int $count): array
    {
        $step = 'Step '.($index + 1).' of '.$count;
        $lines = [];
        $percent = 0;
        $this->job->progress($percent, $step);

        $code = $this->runner->run($argv, function (string $line) use (&$lines, &$percent, $step): void {
            $lines = array_slice([...$lines, $line], -20);
            if (preg_match('/(\d{1,3})%/', $line, $match) === 1 && (int) $match[1] !== $percent) {
                $percent = min(100, (int) $match[1]);
                $this->job->progress($percent, $step);
            }
        }, fn (): bool => $this->job->cancelRequested());

        if ($code === ToolRunner::STOPPED) {
            throw new MapJobFailed('cancelled');
        }

        return $code === 0 ? $lines : throw new MapJobFailed('tool');
    }

    /**
     * The archive size from a dry run's last line, such as "4.1 GB", in bytes.
     *
     * @param list<string> $lines
     */
    private function archiveSize(array $lines): int
    {
        foreach (array_reverse($lines) as $line) {
            if (preg_match(self::SIZE_LINE, $line, $match) === 1) {
                return (int) round((float) $match[1] * self::UNITS[$match[2]]);
            }
        }

        throw new MapJobFailed('size');
    }

    /**
     * The newest planet build Protomaps publishes.
     *
     * @return array{url: string, date: string}
     */
    private function latestBuild(): array
    {
        $keys = [];
        foreach ((array) ($this->fetchJson)(self::BUILDS) as $build) {
            $key = is_array($build) ? (string) ($build['key'] ?? '') : '';
            if (preg_match(self::BUILD_KEY, $key) === 1) {
                $keys[] = $key;
            }
        }
        if ($keys === []) {
            throw new MapJobFailed('builds');
        }

        rsort($keys);
        preg_match(self::BUILD_KEY, $keys[0], $date);

        return ['url' => self::BUILD_HOST.$keys[0], 'date' => $date[1].'-'.$date[2].'-'.$date[3]];
    }

    /**
     * A pmtiles archive starts with the word PMTiles and the format version, 3.
     */
    private function checkArchive(string $part): void
    {
        $head = is_file($part) ? (string) file_get_contents($part, false, null, 0, 8) : '';
        if (strlen($head) !== 8 || !str_starts_with($head, 'PMTiles') || ord($head[7]) !== 3) {
            throw new MapJobFailed('archive');
        }
    }

    /**
     * The installed tool.
     */
    private function toolPath(): string
    {
        return $this->tool->path() ?? throw new MapJobFailed('no-tool');
    }

    /**
     * Note the failure for the page and the console.
     */
    private function failed(string $reason): int
    {
        $this->job->fail($reason);
        echo "The map job failed: {$reason}\n";

        return 1;
    }

    /**
     * Delete the files a run leaves behind; the live map is never one of them.
     */
    private function removeParts(): void
    {
        $part = $this->data->partPath();
        foreach ([$part, $part.'.area', $part.'.world'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
