<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use App\Services\Analytics\CityLookup;
use App\Services\Analytics\CountryLookup;

/**
 * Approximate place of a signed-in session, from the DB-IP files on this
 * server. The address is never sent anywhere else.
 */
class SessionLocation
{
    private bool $reportedMissing = false;

    public function __construct(
        private CityLookup $cities,
        private CountryLookup $countries,
    ) {}

    /**
     * @return array{country: string|null, region: string|null, city: string|null}
     */
    public function locate(?string $ip): array
    {
        $none = ['country' => null, 'region' => null, 'city' => null];

        if ($ip === null || $ip === '') {
            return $none;
        }

        if ($this->cities->available()) {
            return $this->cities->place($ip) ?? $none;
        }

        if ($this->countries->available()) {
            return ['country' => $this->countries->country($ip), 'region' => null, 'city' => null];
        }

        if (!$this->reportedMissing) {
            $this->reportedMissing = true;
            error_log('SessionLocation: no DB-IP city or country file; run analytics:update-geo. Sessions are listed without a place.');
        }

        return $none;
    }

    /**
     * Loopback and private addresses have no place, only "this network".
     */
    public static function isLocal(?string $ip): bool
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
