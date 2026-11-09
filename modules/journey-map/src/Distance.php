<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Straight line distance between two places, and how readers see it.
 */
final class Distance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /** Below this, one decimal still means something; above it, whole kilometres read better. */
    private const DECIMAL_BELOW_KM = 10.0;

    /**
     * Kilometres between two points over the earth's surface (haversine).
     */
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * Kilometres between two stop rows.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    public static function between(array $from, array $to): float
    {
        return self::km((float) $from['lat'], (float) $from['lng'], (float) $to['lat'], (float) $to['lng']);
    }

    /**
     * The number readers see: one decimal under 10 km, whole kilometres above.
     */
    public static function rounded(float $km): string
    {
        if ($km < self::DECIMAL_BELOW_KM) {
            return number_format($km, 1, '.', '');
        }

        return (string) (int) round($km);
    }
}
