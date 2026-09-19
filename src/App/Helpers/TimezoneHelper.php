<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTime;
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

    /**
     * Check a stored timezone string before handing it to DateTimeZone.
     *
     * Preferences and blog settings hold whatever was in the database, which
     * might be null, empty or a zone that PHP dropped in a later tzdata.
     *
     * @param  string|null  $timezone  Candidate identifier, e.g. 'Europe/Athens'
     */
    public static function isValid(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        return in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true);
    }

    public const INVALID_PUBLISH_DATE = 'Enter the date as dd.mm.yy hh:mm, for example 25.12.26 09:30.';

    /**
     * Convert a publish date typed in the writer's timezone to UTC for storage.
     *
     * @param  string  $value  Date as typed, e.g. '25.12.26 09:30'
     * @param  string  $timezone  The writer's timezone identifier
     * @return string|null UTC 'Y-m-d H:i:s', or null when the date or the zone cannot be understood
     */
    public static function localToUtc(string $value, string $timezone): ?string
    {
        if (!self::isValid($timezone)) {
            return null;
        }

        $date = DateTime::createFromFormat('!d.m.y H:i', trim($value), new DateTimeZone($timezone));
        $problems = DateTime::getLastErrors();
        // Without this, 31.02 or 25:99 roll over into a different date instead of failing.
        if ($date === false || ($problems !== false && ($problems['warning_count'] > 0 || $problems['error_count'] > 0))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
