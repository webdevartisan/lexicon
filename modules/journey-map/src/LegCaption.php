<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Closure;

/**
 * The line under a post's map: "Famagusta · 46 km from Nicosia · stop 4 of 12", or with several places
 * "Kyrenia → Famagusta · 61 km from Nicosia · stop 4 of 12".
 */
final class LegCaption
{
    /**
     * The caption for a leg. A post readers cannot see on the journey gets only its places.
     *
     * @param Closure(string, array<string, mixed>=): string $t
     */
    public static function text(Leg $leg, Closure $t): string
    {
        $parts = [self::places($leg)];
        if ($leg->position === null) {
            return $parts[0];
        }

        $parts[] = self::distance($leg, $t);
        $parts[] = $t('post.stopOf', ['number' => $leg->position, 'total' => $leg->total]);

        return implode(' · ', $parts);
    }

    /**
     * The post's place, or its first and last places when it has several.
     */
    public static function places(Leg $leg): string
    {
        $first = (string) $leg->first()['place_name'];

        return count($leg->stops) === 1 ? $first : $first.' → '.$leg->last()['place_name'];
    }

    /**
     * How far the post travels from the place before it, or that it starts the journey.
     *
     * @param Closure(string, array<string, mixed>=): string $t
     */
    private static function distance(Leg $leg, Closure $t): string
    {
        $km = $leg->km();
        if ($km === null) {
            return $t('post.start');
        }

        $about = $leg->isApproximate() ? 'about' : '';
        if ($leg->previous === null) {
            return $t('post.'.($about === '' ? 'km' : 'aboutKm'), ['km' => Distance::rounded($km)]);
        }

        $words = ['km' => Distance::rounded($km), 'place' => (string) $leg->previous['place_name']];

        return $t('post.'.($about === '' ? 'kmFrom' : 'aboutKmFrom'), $words);
    }
}
