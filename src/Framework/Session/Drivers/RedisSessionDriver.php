<?php

declare(strict_types=1);

namespace Framework\Session\Drivers;

use Framework\Session\Redis\RedisConnection;
use Framework\Session\SessionDriver;
use Framework\Session\SessionRegistry;
use RuntimeException;

/**
 * Session data in Redis, one key per session with the idle limit as its TTL.
 *
 * Keys are named by the hash of the session id, so revoking a session from
 * the device list can delete its data directly. A lock key (SET NX PX with a
 * random token) serialises requests on one session, as the file handler does.
 */
final class RedisSessionDriver implements SessionDriver
{
    /** @var array<string, string> Lock key => token, for the locks this request holds */
    private array $locks = [];

    /** @var array<string, true> Hashes of ids this driver created on this request */
    private array $minted = [];

    public function __construct(
        private RedisConnection $redis,
        private string $prefix,
        private int $ttlSeconds,
        private int $lockSeconds,
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        foreach ($this->locks as $key => $token) {
            $this->redis->deleteIfEquals($key, $token);
        }

        $this->locks = [];

        return true;
    }

    public function create_sid(): string
    {
        $id = bin2hex(random_bytes(32));

        // Nobody else can hold an id minted just now, so there is nothing to lock or read.
        $this->minted[SessionRegistry::hash($id)] = true;

        return $id;
    }

    public function validateId(string $id): bool
    {
        return $this->redis->exists($this->key(SessionRegistry::hash($id)));
    }

    public function read(string $id): string
    {
        $hash = SessionRegistry::hash($id);

        if (isset($this->minted[$hash])) {
            return '';
        }

        $this->lock($hash);

        return $this->redis->get($this->key($hash)) ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $this->redis->setEx($this->key(SessionRegistry::hash($id)), $this->ttlSeconds, $data);

        return true;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->redis->expire($this->key(SessionRegistry::hash($id)), $this->ttlSeconds);

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->redis->del([$this->key(SessionRegistry::hash($id))]);

        return true;
    }

    public function gc(int $max_lifetime): int
    {
        // Keys expire through their TTL.
        return 0;
    }

    public function storesRecords(): bool
    {
        return false;
    }

    public function record(string $id): ?array
    {
        return null;
    }

    public function forgetHashes(array $hashes): void
    {
        $this->redis->del(array_map(fn (string $hash): string => $this->key($hash), $hashes));
    }

    public function setClientIp(?string $ip): void {}

    private function key(string $hash): string
    {
        return $this->prefix.$hash;
    }

    private function lock(string $hash): void
    {
        $key = $this->prefix.'lock:'.$hash;

        if (isset($this->locks[$key])) {
            return;
        }

        $token = bin2hex(random_bytes(16));
        $deadline = microtime(true) + $this->lockSeconds;

        // The lock outlives a crashed request by at most lockSeconds.
        while (!$this->redis->setNxPx($key, $token, $this->lockSeconds * 1000)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("Another request held this session for more than {$this->lockSeconds} seconds.");
            }

            usleep(20000);
        }

        $this->locks[$key] = $token;
    }
}
