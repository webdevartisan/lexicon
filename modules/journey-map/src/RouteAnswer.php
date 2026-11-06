<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * How planning one leg went: a road with its km and [lng, lat] points, no road at all, or a failure to retry.
 */
final class RouteAnswer
{
    /**
     * @param string $kind road, none or failed
     * @param list<array{0: float, 1: float}> $points
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?float $km,
        public readonly array $points,
    ) {
    }

    /**
     * A road joins the two places.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function road(float $km, array $points): self
    {
        return new self('road', $km, $points);
    }

    /**
     * No road joins them, or a place lies outside the network.
     */
    public static function none(): self
    {
        return new self('none', null, []);
    }

    /**
     * The planner did not answer properly; worth another try later.
     */
    public static function failed(): self
    {
        return new self('failed', null, []);
    }
}
