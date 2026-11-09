<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * What the admin asked the road network to cover: one Geofabrik area (a country, or a larger area when routes
 * should cross borders) and the local port the planner listens on.
 */
final class RoutingRequest
{
    public const DEFAULT_PORT = 8989;

    private function __construct(public readonly string $region, public readonly int $port) {}

    /**
     * Read the request from the form or the job file.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidMapRequest When the area id or the port is not one we accept
     */
    public static function fromArray(array $data): self
    {
        $region = (string) ($data['region'] ?? '');
        $port = filter_var($data['port'] ?? self::DEFAULT_PORT, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1024, 'max_range' => 65534],
        ]);
        if (preg_match('/^[a-z0-9][a-z0-9\/-]{0,80}$/', $region) !== 1 || str_contains($region, '..')) {
            throw new InvalidMapRequest(['region' => 'invalid']);
        }
        if ($port === false) {
            throw new InvalidMapRequest(['port' => 'invalid']);
        }

        return new self($region, $port);
    }

    /**
     * The request as the job file keeps it.
     *
     * @return array{region: string, port: int}
     */
    public function toArray(): array
    {
        return ['region' => $this->region, 'port' => $this->port];
    }
}
