<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Something that plans a road route between two places, started for a run and stopped after it.
 */
interface RouteSource
{
    /**
     * Get ready to answer. False when it could not start.
     */
    public function start(): bool;

    /**
     * The road route from one place to another.
     *
     * @param  array{lat: float, lng: float}  $from
     * @param  array{lat: float, lng: float}  $to
     */
    public function route(array $from, array $to): RouteAnswer;

    /**
     * Shut down again; safe to call when it never started.
     */
    public function stop(): void;
}
