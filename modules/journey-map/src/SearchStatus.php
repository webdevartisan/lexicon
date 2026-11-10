<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Cache\CacheService;

/**
 * How place search has been doing, for the admin page: last success, last failure and busy refusals today.
 *
 * Kept in the cache, not the database, so a search never costs a write; losing it only blanks the status line.
 */
final class SearchStatus
{
    private const KEY = 'journey-map:search-status';

    private const KEEP_SECONDS = 2592000;

    public function __construct(private CacheService $cache) {}

    /**
     * A search got an answer.
     */
    public function recordSuccess(): void
    {
        $this->write(['lastSuccess' => $this->now()]);
    }

    /**
     * A search got no usable answer.
     */
    public function recordFailure(string $reason): void
    {
        $this->write(['lastFailure' => $this->now(), 'failureReason' => $reason]);
    }

    /**
     * A search was refused because another went out less than a second before.
     */
    public function recordBusy(): void
    {
        $status = $this->read();
        $this->write(['busyToday' => $status['busyToday'] + 1, 'busyDay' => gmdate('Y-m-d')]);
    }

    /**
     * @return array{lastSuccess: ?string, lastFailure: ?string, failureReason: ?string, busyToday: int}
     */
    public function read(): array
    {
        $stored = json_decode((string) $this->cache->get(self::KEY), true);
        $stored = is_array($stored) ? $stored : [];
        $today = ($stored['busyDay'] ?? null) === gmdate('Y-m-d');

        return [
            'lastSuccess' => isset($stored['lastSuccess']) ? (string) $stored['lastSuccess'] : null,
            'lastFailure' => isset($stored['lastFailure']) ? (string) $stored['lastFailure'] : null,
            'failureReason' => isset($stored['failureReason']) ? (string) $stored['failureReason'] : null,
            'busyToday' => $today ? (int) ($stored['busyToday'] ?? 0) : 0,
        ];
    }

    /**
     * @param  array<string, string|int>  $changes
     */
    private function write(array $changes): void
    {
        $stored = json_decode((string) $this->cache->get(self::KEY), true);
        $merged = array_merge(is_array($stored) ? $stored : [], $changes);

        $this->cache->set(self::KEY, json_encode($merged, JSON_THROW_ON_ERROR), self::KEEP_SECONDS);
    }

    /**
     * The current UTC time in ISO 8601.
     */
    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
