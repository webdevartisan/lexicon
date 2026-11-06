<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Plans the waiting legs with a started route source and stores what it found, simplified for the map.
 */
final class RoutePlanner
{
    public function __construct(private LegModel $legs)
    {
    }

    /**
     * Plan up to the limit of waiting legs.
     *
     * @return int How many legs got an answer
     */
    public function plan(RouteSource $source, int $limit): int
    {
        $planned = 0;
        foreach ($this->legs->waiting($limit) as $leg) {
            $from = ['lat' => (float) $leg['from_lat'], 'lng' => (float) $leg['from_lng']];
            $to = ['lat' => (float) $leg['to_lat'], 'lng' => (float) $leg['to_lng']];
            $this->store($leg, $source->route($from, $to));
            $planned++;
        }

        return $planned;
    }

    /**
     * Save one answer: a failure counts an attempt, the rest are final until a place moves.
     *
     * @param array<string, mixed> $leg The waiting leg's row
     */
    private function store(array $leg, RouteAnswer $answer): void
    {
        $blogId = (int) $leg['blog_id'];
        if ($answer->kind === 'failed') {
            $this->legs->failed($blogId, $leg['from_hash'], $leg['to_hash']);

            return;
        }

        $line = $answer->kind === 'road' ? Polyline::encode(Polyline::simplify($answer->points)) : null;
        $km = $answer->km === null ? null : round($answer->km, 1);
        $this->legs->store($blogId, $leg['from_hash'], $leg['to_hash'], $answer->kind, $km, $line);
    }
}
