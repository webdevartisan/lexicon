<?php

declare(strict_types=1);

namespace Framework\Session\Redis;

/**
 * The handful of Redis commands the session driver uses, so it runs on either
 * client. Every command here exists in Redis 5.0.
 */
interface RedisConnection
{
    public function get(string $key): ?string;

    public function setEx(string $key, int $seconds, string $value): void;

    /**
     * SET key value NX PX ms: true when the key was created.
     */
    public function setNxPx(string $key, string $value, int $milliseconds): bool;

    public function expire(string $key, int $seconds): void;

    public function exists(string $key): bool;

    public function ttl(string $key): int;

    /**
     * @param  list<string>  $keys
     */
    public function del(array $keys): void;

    /**
     * Delete $key only while it still holds $value.
     */
    public function deleteIfEquals(string $key, string $value): void;
}
