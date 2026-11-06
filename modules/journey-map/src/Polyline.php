<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Road lines in the encoded polyline format (precision 5), stored as [lng, lat] points like the map uses.
 */
final class Polyline
{
    private const FACTOR = 1e5;

    /** About 11 m: invisible at the post map's zoom, and it drops most points of a long road. */
    public const TOLERANCE = 0.0001;

    /**
     * Turn an encoded line into [lng, lat] points. Broken input gives the points read before the break.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function decode(string $encoded): array
    {
        $points = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $length = strlen($encoded);

        while ($index < $length) {
            $dLat = self::readValue($encoded, $index);
            $dLng = self::readValue($encoded, $index);
            if ($dLat === null || $dLng === null) {
                break;
            }
            $lat += $dLat;
            $lng += $dLng;
            $points[] = [$lng / self::FACTOR, $lat / self::FACTOR];
        }

        return $points;
    }

    /**
     * Encode [lng, lat] points.
     *
     * @param list<array{0: float, 1: float}> $points
     */
    public static function encode(array $points): string
    {
        $encoded = '';
        $lastLat = 0;
        $lastLng = 0;

        foreach ($points as [$lng, $lat]) {
            $lat = (int) round($lat * self::FACTOR);
            $lng = (int) round($lng * self::FACTOR);
            $encoded .= self::writeValue($lat - $lastLat).self::writeValue($lng - $lastLng);
            $lastLat = $lat;
            $lastLng = $lng;
        }

        return $encoded;
    }

    /**
     * Drop points that sit within the tolerance of the line around them (Douglas Peucker), keeping both ends.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return list<array{0: float, 1: float}>
     */
    public static function simplify(array $points, float $tolerance = self::TOLERANCE): array
    {
        $count = count($points);
        if ($count < 3) {
            return $points;
        }

        $keep = array_fill(0, $count, false);
        $keep[0] = $keep[$count - 1] = true;
        // A stack instead of recursion, so a road with many thousand points cannot run out of call depth.
        $spans = [[0, $count - 1]];
        while ($spans !== []) {
            [$first, $last] = array_pop($spans);
            [$index, $distance] = self::farthest($points, $first, $last);
            if ($distance > $tolerance) {
                $keep[$index] = true;
                $spans[] = [$first, $index];
                $spans[] = [$index, $last];
            }
        }

        return array_values(array_filter($points, fn (int $i): bool => $keep[$i], ARRAY_FILTER_USE_KEY));
    }

    /**
     * The point between two others that lies farthest from the straight line joining them.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return array{0: int, 1: float}
     */
    private static function farthest(array $points, int $first, int $last): array
    {
        $index = $first;
        $max = 0.0;
        for ($i = $first + 1; $i < $last; $i++) {
            $distance = self::offLine($points[$i], $points[$first], $points[$last]);
            if ($distance > $max) {
                $index = $i;
                $max = $distance;
            }
        }

        return [$index, $max];
    }

    /**
     * How far a point lies from the segment between a and b, in degrees.
     *
     * @param array{0: float, 1: float} $p
     * @param array{0: float, 1: float} $a
     * @param array{0: float, 1: float} $b
     */
    private static function offLine(array $p, array $a, array $b): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = 0.0;
        if ($lengthSquared > 0) {
            $t = max(0.0, min(1.0, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $lengthSquared));
        }

        return hypot($p[0] - ($a[0] + $t * $dx), $p[1] - ($a[1] + $t * $dy));
    }

    /**
     * Read one signed value, moving the index past it, or null when the line ends mid value.
     */
    private static function readValue(string $encoded, int &$index): ?int
    {
        $result = 0;
        $shift = 0;
        $length = strlen($encoded);
        do {
            if ($index >= $length || $shift > 30) {
                return null;
            }
            $byte = ord($encoded[$index++]) - 63;
            $result |= ($byte & 0x1f) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : ($result >> 1);
    }

    /**
     * Write one signed value.
     */
    private static function writeValue(int $value): string
    {
        $value = $value < 0 ? ~($value << 1) : ($value << 1);
        $out = '';
        while ($value >= 0x20) {
            $out .= chr((0x20 | ($value & 0x1f)) + 63);
            $value >>= 5;
        }

        return $out.chr($value + 63);
    }
}
