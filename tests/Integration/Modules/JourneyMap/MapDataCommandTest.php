<?php

declare(strict_types=1);

use App\Services\PublicCacheInvalidator;
use Modules\JourneyMap\MapAssets;
use Modules\JourneyMap\MapData;
use Modules\JourneyMap\MapDataCommand;
use Modules\JourneyMap\MapJob;
use Modules\JourneyMap\MapRequest;
use Modules\JourneyMap\MapTool;
use Modules\JourneyMap\ToolRunner;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * The background command that measures or downloads the map. The tool, the build list and the
 * page cache are stand-ins, so nothing here reaches the network or starts a program.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->root = TempFolder::make('lexicon-jm-command');
    $this->public = $this->root.'/public';
    $this->state = $this->root.'/state';
    mkdir($this->public.'/map-data/fonts', 0775, true);
    mkdir($this->public.'/map-data/sprites', 0775, true);
    mkdir($this->root.'/bin', 0775, true);
    touch($this->root.'/bin/pmtiles');
    $this->live = $this->public.'/map-data/journey-map.pmtiles';

    $this->job = new MapJob($this->state);
    $this->data = new MapData($this->public.'/map-data', $this->state);
    // Plays the tool: extracts write a small archive, merges write theirs, and a test can swap either step.
    $this->runner = new class implements ToolRunner {
        /** @var list<list<string>> */
        public array $calls = [];

        public ?Closure $onExtract = null;

        public ?Closure $onMerge = null;

        public function run(array $argv, Closure $onOutput, Closure $shouldStop): int
        {
            $this->calls[] = $argv;
            $step = $argv[1] === 'merge' ? $this->onMerge : $this->onExtract;
            if ($step !== null) {
                return $step($argv, $onOutput, $shouldStop);
            }

            $output = $argv[1] === 'merge' ? end($argv) : $argv[3];
            file_put_contents($output, 'PMTiles'.chr(3).str_repeat('x', 20));

            return 0;
        }
    };
    $this->pages = new class extends PublicCacheInvalidator {
        public int $purged = 0;

        public function purgeAllPages(): void
        {
            $this->purged++;
        }
    };

    $this->command = function (): MapDataCommand {
        $builds = fn (string $url): array => [
            ['key' => '20261001.pmtiles'], ['key' => '20261105.pmtiles'], ['key' => 'notes.txt'],
        ];
        $tool = new MapTool($this->root.'/bin', fn () => null, fn () => false, 'Linux', 'x86_64');
        $assets = new MapAssets($this->public.'/map-data', fn () => false);

        return new MapDataCommand($this->job, $this->data, $assets, $this->runner, $tool, $this->pages, $builds);
    };
    $this->run = function (): int {
        ob_start();
        try {
            return ($this->command)()->handle();
        } finally {
            ob_end_clean();
        }
    };
    $this->request = fn (string $area = 'europe'): MapRequest => MapRequest::fromInput(
        ['zoom' => '10', 'area' => $area, 'overview' => '1'],
        $this->job->regionDir()
    );
});

afterEach(function () {
    TempFolder::remove($this->root);
});

test('a size check records the archive size', function () {
    $this->runner->onExtract = function (array $argv, Closure $say): int {
        $say('fetching directories 100%');
        $say(str_contains(implode(' ', $argv), '--bbox=')
            ? 'Extract transferred 1.2 GB (overfetch 0.05) for an archive size of 4.1 GB'
            : 'Extract transferred 80 MB (overfetch 0.05) for an archive size of 512 MB');

        return 0;
    };
    $this->job->queue('size', ($this->request)(), 1);

    expect(($this->run)())->toBe(0)
        ->and($this->job->current()['state'])->toBe('finished')
        ->and($this->job->sizeFor(($this->request)()))->toBe(4_612_000_000)
        ->and($this->runner->calls[0][2])->toBe('https://build.protomaps.com/20261105.pmtiles')
        ->and(is_file($this->live))->toBeFalse();
});

test('a download swaps the file in and records the map', function () {
    file_put_contents($this->live, 'old map');
    $this->job->queue('download', ($this->request)(), 1);

    expect(($this->run)())->toBe(0)
        ->and(file_get_contents($this->live))->toStartWith('PMTiles'.chr(3))
        ->and($this->data->info())->toMatchArray(['build' => '2026-11-05', 'request' => ($this->request)()->toArray()])
        ->and($this->data->info()['bytes'])->toBe(filesize($this->live))
        ->and($this->job->current()['state'])->toBe('finished')
        ->and($this->pages->purged)->toBe(1)
        ->and(glob($this->public.'/map-data/*.part*'))->toBe([]);
});

test('the first download makes the map folder', function () {
    TempFolder::remove($this->public);

    $part = $this->data->preparePart();

    expect($part)->toBe($this->data->partPath())
        ->and(is_dir(dirname($part)))->toBeTrue();
});

test('a finished file that is not a pmtiles archive is not swapped in', function () {
    file_put_contents($this->live, 'old map');
    $this->runner->onMerge = function (array $argv): int {
        file_put_contents(end($argv), 'something else');

        return 0;
    };
    $this->job->queue('download', ($this->request)(), 1);

    expect(($this->run)())->toBe(1)
        ->and(file_get_contents($this->live))->toBe('old map')
        ->and($this->job->current())->toMatchArray(['state' => 'failed', 'reason' => 'archive'])
        ->and($this->pages->purged)->toBe(0);
});

test('a scheduled run with nothing queued refreshes the last map', function () {
    $this->job->queue('download', ($this->request)('africa'), 1);
    ($this->run)();
    $this->runner->calls = [];

    expect(($this->run)())->toBe(0)
        ->and($this->runner->calls)->toHaveCount(3)
        ->and($this->job->current())->toMatchArray(['state' => 'finished', 'kind' => 'download', 'userId' => 0])
        ->and($this->job->current()['request'])->toBe(($this->request)('africa')->toArray());
});

test('a scheduled run with no map and nothing queued does nothing', function () {
    expect(($this->run)())->toBe(0)
        ->and($this->runner->calls)->toBe([])
        ->and($this->job->current())->toBeNull();
});

test('a failed run leaves the live file and cleans its parts', function () {
    file_put_contents($this->live, 'old map');
    $this->runner->onMerge = fn (): int => 1;
    $this->job->queue('download', ($this->request)(), 1);

    expect(($this->run)())->toBe(1)
        ->and(file_get_contents($this->live))->toBe('old map')
        ->and($this->job->current())->toMatchArray(['state' => 'failed', 'reason' => 'tool'])
        ->and(glob($this->public.'/map-data/*.part*'))->toBe([]);
});

test('a cancelled run leaves the live file', function () {
    file_put_contents($this->live, 'old map');
    $this->runner->onExtract = function (array $argv, Closure $say, Closure $stop): int {
        file_put_contents($argv[3], 'half');
        $this->job->requestCancel();

        return $stop() ? 130 : 0;
    };
    $this->job->queue('download', ($this->request)(), 1);

    expect(($this->run)())->toBe(1)
        ->and(file_get_contents($this->live))->toBe('old map')
        ->and($this->job->current())->toMatchArray(['state' => 'failed', 'reason' => 'cancelled'])
        ->and(glob($this->public.'/map-data/*.part*'))->toBe([]);
});

test('progress percentages reach the job file', function () {
    $seen = [];
    $this->runner->onExtract = function (array $argv, Closure $say) use (&$seen): int {
        $say('downloading 12%');
        $say('downloading 64%');
        $seen[] = $this->job->current()['percent'].' '.$this->job->current()['step'];
        file_put_contents($argv[3], 'PMTiles'.chr(3));

        return 0;
    };
    $this->job->queue('download', ($this->request)(), 1);
    ($this->run)();

    expect($seen)->toBe(['64 Step 1 of 3', '64 Step 2 of 3']);
});
