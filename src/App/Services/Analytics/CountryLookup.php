<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Country for an IP address, looked up in the DB-IP Lite country file.
 */
class CountryLookup extends MmdbLookup
{
    /**
     * @return string|null Upper-case ISO 3166 alpha-2 code
     */
    public function country(string $ip): ?string
    {
        $code = $this->record($ip)['country']['iso_code'] ?? null;

        return is_string($code) && preg_match('/^[A-Z]{2}$/', $code) ? $code : null;
    }
}
