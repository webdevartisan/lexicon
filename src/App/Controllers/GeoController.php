<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GeoLocationService;

/**
 * Geolocation API endpoints.
 *
 * Provides HTTPS-secured access to geolocation data for client-side timezone detection.
 */
final class GeoController extends AppController
{
    public function __construct(
        private readonly GeoLocationService $geo,
    ) {}

    /**
     * Get timezone and location data based on client IP.
     *
     * Proxies ip-api.com through HTTPS to avoid mixed-content browser errors.
     *
     * @return mixed JSON response with geolocation data
     */
    public function timezone()
    {
        $data = $this->geo->getTimezoneData();

        // Return 502 for failed lookups, 200 for API responses (even if status=fail)
        $statusCode = ($data['status'] ?? 'fail') === 'fail' && !isset($data['timezone']) ? 502 : 200;

        return $this->json($data, $statusCode);
    }
}
