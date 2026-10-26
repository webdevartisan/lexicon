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
     * Zones like "America/New_York" are grouped by their region prefix for the
     * select's option groups.
     *
     * @return array<string, string[]>
     */
    public static function getGroupedTimezones(): array
    {
        $grouped = [];

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $zone) {
            $parts = explode('/', $zone, 2);

            // Zones such as "UTC" have no region prefix
            if (count($parts) === 1) {
                $grouped['Other'][] = $zone;
                continue;
            }

            $grouped[$parts[0]][] = $zone;
        }

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

    /**
     * The name PHP lists for a zone, turning an older name such as Asia/Calcutta
     * into the one used today, Asia/Kolkata.
     *
     * @param  string  $name  Zone identifier, current or old
     * @return string|null The listed name, or null when no listed zone keeps the same clock
     */
    public static function currentName(string $name): ?string
    {
        if (self::isValid($name)) {
            return $name;
        }

        if (!in_array($name, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            return null;
        }

        // PHP does not say which name an old one became, but the two share every clock change.
        $history = (new DateTimeZone($name))->getTransitions();

        foreach (self::getAllTimezones() as $zone) {
            if ((new DateTimeZone($zone))->getTransitions() === $history) {
                return $zone;
            }
        }

        return null;
    }

    /** How the date picker holds a publish date: the wall clock time in the writer's zone. */
    public const PICKER_FORMAT = 'Y-m-d H:i:s';

    public const INVALID_PUBLISH_DATE = 'That date could not be read. Pick it again from the calendar.';

    /**
     * Convert a publish date picked in the writer's zone to UTC for storage.
     *
     * @param  string  $value  Date as the picker sends it, e.g. '2026-11-20 10:00:00'
     * @param  string  $timezone  The zone the picker showed
     * @param  string|null  $current  The UTC date already stored, kept when the value is how the page showed it
     * @return string|null UTC 'Y-m-d H:i:s', or null when the date or the zone cannot be understood
     */
    public static function localToUtc(string $value, string $timezone, ?string $current = null): ?string
    {
        if (!self::isValid($timezone)) {
            return null;
        }

        // When the clocks go back an hour happens twice, and reading it again can
        // land on the other one, so a date nobody touched would move.
        if ($current !== null && self::utcToLocal($current, $timezone) === trim($value)) {
            return $current;
        }

        $date = DateTime::createFromFormat('!'.self::PICKER_FORMAT, trim($value), new DateTimeZone($timezone));
        $problems = DateTime::getLastErrors();
        // Without this, Feb 31 or 25:99 roll over into a different date instead of failing.
        if ($date === false || ($problems !== false && ($problems['warning_count'] > 0 || $problems['error_count'] > 0))) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * Turn a stored UTC date into the date picker's value in the writer's zone.
     *
     * @param  string|null  $utc  Datetime as stored, 'Y-m-d H:i:s'
     * @param  string  $timezone  The zone to show it in
     * @return string The picker's format, or '' when there is no date
     */
    public static function utcToLocal(?string $utc, string $timezone): string
    {
        return local_datetime($utc, self::PICKER_FORMAT, $timezone);
    }

    /**
     * A zone identifier written the way people read it.
     *
     * @param  string  $timezone  Identifier, e.g. 'America/New_York'
     * @return string e.g. 'America/New York'
     */
    public static function label(string $timezone): string
    {
        return str_replace('_', ' ', $timezone);
    }
}
