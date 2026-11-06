<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * The settings file GraphHopper reads: one car profile in fast mode, listening on this machine only.
 */
final class GraphHopperConfig
{
    /**
     * Write config.yml for building from a road data file, or for serving an already built network.
     *
     * @param string|null $roadData The .osm.pbf file to build from, or null when only serving
     */
    public static function write(RoutingFiles $files, string $network, int $port, ?string $roadData = null): void
    {
        $lines = [
            'graphhopper:',
            '  datareader.file: '.self::quote($roadData ?? ''),
            '  graph.location: '.self::quote($network),
            '  graph.encoded_values: car_access, car_average_speed, road_access',
            '  import.osm.ignored_highways: footway,cycleway,path,pedestrian,steps,bridleway',
            '  profiles:',
            '    - name: car',
            '      custom_model_files: [car.json]',
            '  profiles_ch:',
            '    - profile: car',
            '  profiles_lm: []',
            'server:',
            '  application_connectors:',
            '    - type: http',
            '      port: '.$port,
            '      bind_host: 127.0.0.1',
            '  admin_connectors:',
            '    - type: http',
            '      port: '.($port + 1),
            '      bind_host: 127.0.0.1',
            'logging:',
            '  level: WARN',
        ];

        file_put_contents($files->config(), implode("\n", $lines)."\n");
    }

    /**
     * A YAML string in single quotes, so Windows paths and spaces stay as they are.
     */
    private static function quote(string $value): string
    {
        return "'".str_replace("'", "''", str_replace('\\', '/', $value))."'";
    }
}
