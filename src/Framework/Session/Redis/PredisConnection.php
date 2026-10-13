<?php

declare(strict_types=1);

namespace Framework\Session\Redis;

use Predis\Client;

/**
 * Redis through Predis, a pure-PHP client that needs no extension.
 */
final class PredisConnection implements RedisConnection
{
    // Fixed Lua run by Redis EVAL; the key and token travel as arguments, never spliced in.
    private const RELEASE_SCRIPT = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";

    public function __construct(private Client $client) {}

    /**
     * @param  array{host: string, port: int, password?: ?string, database?: int, timeout?: float}  $config
     */
    public static function connect(array $config): self
    {
        $parameters = [
            'scheme' => 'tcp',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'] ?? 0,
            'timeout' => $config['timeout'] ?? 2.0,
        ];

        if (($config['password'] ?? null) !== null && $config['password'] !== '') {
            $parameters['password'] = $config['password'];
        }

        return new self(new Client($parameters));
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);

        return $value === null ? null : (string) $value;
    }

    public function setEx(string $key, int $seconds, string $value): void
    {
        $this->client->setex($key, $seconds, $value);
    }

    public function setNxPx(string $key, string $value, int $milliseconds): bool
    {
        return $this->client->set($key, $value, 'PX', $milliseconds, 'NX') !== null;
    }

    public function expire(string $key, int $seconds): void
    {
        $this->client->expire($key, $seconds);
    }

    public function exists(string $key): bool
    {
        return (int) $this->client->exists($key) > 0;
    }

    public function ttl(string $key): int
    {
        return (int) $this->client->ttl($key);
    }

    public function del(array $keys): void
    {
        if ($keys !== []) {
            $this->client->del($keys);
        }
    }

    public function deleteIfEquals(string $key, string $value): void
    {
        $this->client->eval(self::RELEASE_SCRIPT, 1, $key, $value);
    }
}
