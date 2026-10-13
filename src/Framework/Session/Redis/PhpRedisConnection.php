<?php

declare(strict_types=1);

namespace Framework\Session\Redis;

use RuntimeException;

/**
 * Redis through the phpredis extension, used when it is loaded.
 */
final class PhpRedisConnection implements RedisConnection
{
    // Fixed Lua run by Redis EVAL; the key and token travel as arguments, never spliced in.
    private const RELEASE_SCRIPT = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";

    public function __construct(private \Redis $redis) {}

    /**
     * @param  array{host: string, port: int, password?: ?string, database?: int, timeout?: float}  $config
     */
    public static function connect(array $config): self
    {
        $redis = new \Redis();

        if (!$redis->connect($config['host'], $config['port'], $config['timeout'] ?? 2.0)) {
            throw new RuntimeException("Cannot connect to Redis at {$config['host']}:{$config['port']}.");
        }

        if (($config['password'] ?? null) !== null && $config['password'] !== '' && !$redis->auth($config['password'])) {
            throw new RuntimeException('Redis refused the session password.');
        }

        $redis->select($config['database'] ?? 0);

        return new self($redis);
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);

        return $value === false ? null : (string) $value;
    }

    public function setEx(string $key, int $seconds, string $value): void
    {
        $this->redis->setex($key, $seconds, $value);
    }

    public function setNxPx(string $key, string $value, int $milliseconds): bool
    {
        return $this->redis->set($key, $value, ['nx', 'px' => $milliseconds]) === true;
    }

    public function expire(string $key, int $seconds): void
    {
        $this->redis->expire($key, $seconds);
    }

    public function exists(string $key): bool
    {
        return (int) $this->redis->exists($key) > 0;
    }

    public function ttl(string $key): int
    {
        return (int) $this->redis->ttl($key);
    }

    public function del(array $keys): void
    {
        if ($keys !== []) {
            $this->redis->del($keys);
        }
    }

    public function deleteIfEquals(string $key, string $value): void
    {
        $this->redis->eval(self::RELEASE_SCRIPT, [$key, $value], 1);
    }
}
