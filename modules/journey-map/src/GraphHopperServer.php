<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * Runs GraphHopper on 127.0.0.1 for one planning run: started in the background, asked over local HTTP,
 * and stopped again, so it never holds memory between runs.
 */
final class GraphHopperServer implements RouteSource
{
    private const START_SECONDS = 60;

    private const NONE_MESSAGES = ['Cannot find point', 'Connection between locations not found', 'out of bounds'];

    /** @var resource|null */
    private $process = null;

    public function __construct(private RoutingFiles $files, private JavaRuntime $java) {}

    /**
     * Start the server and wait until it answers its health check.
     */
    public function start(): bool
    {
        $java = $this->java->path();
        if ($java === null || !$this->files->built()) {
            return false;
        }

        $argv = [$java, '-Xmx'.$this->heapMb().'m', '-jar', $this->files->jar(), 'server', $this->files->config()];
        $log = $this->files->root().'/server.log';
        $spec = [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['redirect', 1]];
        $process = proc_open($argv, $spec, $pipes, $this->files->root(), null, ['bypass_shell' => true]);
        if ($process === false) {
            return false;
        }
        fclose($pipes[0]);
        $this->process = $process;

        return $this->waitUntilReady();
    }

    /**
     * Ask for the car route between two places.
     */
    public function route(array $from, array $to): RouteAnswer
    {
        $query = 'point='.$this->point($from).'&point='.$this->point($to)
            .'&profile=car&points_encoded=true&instructions=false&calc_points=true';
        [$status, $body] = $this->get('/route?'.$query, 30);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return RouteAnswer::failed();
        }

        if ($status === 200 && isset($data['paths'][0]['distance'], $data['paths'][0]['points'])) {
            $path = $data['paths'][0];

            return RouteAnswer::road((float) $path['distance'] / 1000, Polyline::decode((string) $path['points']));
        }

        return $this->isNoRoad((string) ($data['message'] ?? '')) ? RouteAnswer::none() : RouteAnswer::failed();
    }

    /**
     * Stop the server if it is running.
     */
    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        proc_terminate($this->process);
        proc_close($this->process);
        $this->process = null;
    }

    /**
     * Poll the health check until it answers, the server dies, or the start time runs out.
     */
    private function waitUntilReady(): bool
    {
        $until = time() + self::START_SECONDS;
        while (time() < $until) {
            if (!proc_get_status($this->process)['running']) {
                $this->stop();

                return false;
            }
            if ($this->get('/health', 1)[0] === 200) {
                return true;
            }
            usleep(500_000);
        }

        $this->stop();

        return false;
    }

    /**
     * GraphHopper's words for places it cannot join by road.
     */
    private function isNoRoad(string $message): bool
    {
        foreach (self::NONE_MESSAGES as $words) {
            if (str_contains($message, $words)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{lat: float, lng: float}  $place
     */
    private function point(array $place): string
    {
        return sprintf('%.6F,%.6F', (float) $place['lat'], (float) $place['lng']);
    }

    /**
     * A GET to the local server.
     *
     * @return array{0: int, 1: string} Status and body
     */
    private function get(string $path, int $timeout): array
    {
        $curl = curl_init('http://127.0.0.1:'.$this->files->port().$path);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [$status, is_string($body) ? $body : ''];
    }

    /**
     * The network loads into memory, so the heap follows its size with room to spare.
     */
    private function heapMb(): int
    {
        return max(1024, (int) ceil($this->files->diskBytes() * 1.5 / 1048576) + 512);
    }
}
