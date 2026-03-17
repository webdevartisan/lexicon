<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\CacheService;

/**
 * Geolocation service using ip-api.com.
 *
 * Provides timezone and location detection based on IP address.
 * Results are cached to reduce external API calls and respect rate limits.
 */
class GeoLocationService
{
    private const API_URL = 'http://ip-api.com/json/';

    private const TIMEOUT = 3;

    private const CACHE_TTL = 3600; // 1 hour

    private const FIELDS = [
        'status',
        'message',
        'countryCode',
        'timezone',
        'lat',
        'lon',
    ];

    public function __construct(
        private readonly CacheService $cache,
    ) {}

    /**
     * Get geolocation data for the current request IP.
     *
     * @return array{status: string, message?: string, countryCode?: string, timezone?: string, lat?: float, lon?: float}
     */
    public function getTimezoneData(): array
    {
        $ip = $this->getClientIp();

        $cacheKey = "geo:timezone:{$ip}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            // Deserialize from JSON
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Fetch from API
        $data = $this->fetchFromApi();

        // Cache successful responses only
        if (isset($data['status']) && $data['status'] === 'success') {
            // Serialize to JSON for caching
            $this->cache->set($cacheKey, json_encode($data), self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Fetch geolocation data from ip-api.com.
     *
     * @return array API response data or error array
     */
    private function fetchFromApi(): array
    {
        $fields = implode(',', self::FIELDS);
        $url = self::API_URL.'?fields='.$fields;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'header' => "Accept: application/json\r\nUser-Agent: Lexicon/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $raw = file_get_contents($url, false, $context);

        if ($raw === false) {
            error_log('GeoLocationService: Failed to fetch from ip-api.com');

            return ['status' => 'fail', 'message' => 'GeoIP lookup failed'];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            error_log('GeoLocationService: Invalid JSON response from ip-api.com');

            return ['status' => 'fail', 'message' => 'Invalid API response'];
        }

        return $data;
    }

    /**
     * Get the client's IP address.
     *
     * Checks common proxy headers for real IP when behind reverse proxy.
     *
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        // Check for IP from reverse proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
