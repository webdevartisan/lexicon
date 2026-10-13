<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * City, region and country for an IP address, from the DB-IP Lite city file.
 */
class CityLookup extends MmdbLookup
{
    /**
     * @return array{country: string|null, region: string|null, city: string|null}|null
     */
    public function place(string $ip): ?array
    {
        $record = $this->record($ip);

        if ($record === null) {
            return null;
        }

        $country = $record['country']['iso_code'] ?? null;

        return [
            'country' => is_string($country) && preg_match('/^[A-Z]{2}$/', $country) ? $country : null,
            'region' => self::name($record['subdivisions'][0]['names'] ?? null),
            'city' => self::name($record['city']['names'] ?? null),
        ];
    }

    /**
     * @param  mixed  $names  Localised names keyed by language
     */
    private static function name(mixed $names): ?string
    {
        $name = is_array($names) ? ($names['en'] ?? null) : null;

        return is_string($name) && $name !== '' ? mb_substr($name, 0, 100) : null;
    }
}
