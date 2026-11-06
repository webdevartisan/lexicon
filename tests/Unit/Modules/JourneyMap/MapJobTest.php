<?php

declare(strict_types=1);

use Modules\JourneyMap\MapJob;
use Modules\JourneyMap\MapRequest;
use Tests\Helpers\ModuleFixtures;
use Tests\Helpers\TempFolder;

/**
 * The job file is how the page and the background command talk: queued, running, finished or failed.
 */
beforeEach(function () {
    ModuleFixtures::loadJourneyMap();
    $this->dir = TempFolder::make('lexicon-jm-job');
    $this->now = 1_000_000;
    $this->job = new MapJob($this->dir, fn (): int => $this->now);
    $this->request = fn (string $area): MapRequest => MapRequest::fromInput(
        ['zoom' => '10', 'area' => $area, 'overview' => '1'],
        $this->dir
    );
});

afterEach(function () {
    TempFolder::remove($this->dir);
});

test('a job moves from running to finished', function () {
    expect($this->job->current())->toBeNull();

    $this->job->queue('download', ($this->request)('europe'), 7);
    expect($this->job->current())->toMatchArray(['state' => 'queued', 'kind' => 'download', 'userId' => 7]);

    $this->job->begin(4242);
    $this->job->progress(35, 'Step 1 of 3');
    expect($this->job->current())->toMatchArray(['state' => 'running', 'percent' => 35, 'step' => 'Step 1 of 3']);

    $this->job->finish();
    expect($this->job->current())->toMatchArray(['state' => 'finished'])
        ->and($this->job->current()['request'])->toBe(($this->request)('europe')->toArray());
});

test('cancel is seen by the runner', function () {
    $this->job->queue('download', ($this->request)('europe'), 7);
    $this->job->begin(4242);
    expect($this->job->cancelRequested())->toBeFalse();

    $this->job->requestCancel();
    $this->job->progress(50, 'Step 1 of 1');

    expect($this->job->cancelRequested())->toBeTrue();

    $this->job->queue('download', ($this->request)('europe'), 7);
    expect($this->job->cancelRequested())->toBeFalse();
});

test('a stale job counts as failed', function () {
    $this->job->queue('download', ($this->request)('europe'), 7);
    $this->job->begin(4242);
    $this->job->progress(10, 'Step 1 of 1');

    $this->now += 599;
    expect($this->job->current()['state'])->toBe('running');

    $this->now += 2;
    expect($this->job->current())->toMatchArray(['state' => 'failed', 'reason' => 'stopped']);
});

test('the size is only reused for the same request', function () {
    $this->job->queue('size', ($this->request)('europe'), 7);
    $this->job->begin(4242);
    $this->job->sized(4_100_000_000);
    $this->job->finish();

    expect($this->job->sizeFor(($this->request)('europe')))->toBe(4_100_000_000)
        ->and($this->job->sizeFor(($this->request)('asia')))->toBeNull();

    $this->job->queue('download', ($this->request)('europe'), 7);
    expect($this->job->sizeFor(($this->request)('europe')))->toBe(4_100_000_000);
});

test('a queued job that never started counts as failed', function () {
    $this->job->queue('download', ($this->request)('europe'), 7);

    $this->now += 601;

    expect($this->job->current())->toMatchArray(['state' => 'failed', 'reason' => 'stopped']);
});
