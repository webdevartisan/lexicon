<?php

namespace App\Helpers;

use DateTimeZone;

/**
 * Timezone utility functions for the application.
 *
 * We centralize timezone operations here to avoid duplicating
 * this logic across multiple controllers and services.
 */
class TimezoneHelper
{
    /**
     * Get timezones grouped by region for select dropdown.
     *
     * We group timezones like "America/New_York" by their region prefix
     * to create organized optgroups in the UI.
     *
     * TODO: Cache this result as it's expensive and static.
     *
     * @return array<string, string[]>
     */
    public static function getGroupedTimezones(): array
    {
        // could check cache here before computing
        // if (Cache::has('grouped_timezones')) {
        //     return Cache::get('grouped_timezones');
        // }

        $zones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $grouped = [];

        foreach ($zones as $zone) {
            // split "America/New_York" into ["America", "New_York"]
            $parts = explode('/', $zone, 2);
            $region = $parts[0];

            // skip deprecated/unusual zones without region prefix
            if (count($parts) === 1) {
                $grouped['Other'][] = $zone;
                continue;
            }

            $grouped[$region][] = $zone;
        }

        // should cache this for 24 hours since it never changes
        // Cache::put('grouped_timezones', $grouped, 86400);

        return $grouped;
    }

    /**
     * Get a flat list of all available timezones.
     *
     * @return string[]
     */
    public static function getAllTimezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }
}
