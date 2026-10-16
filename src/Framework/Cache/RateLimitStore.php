<?php

declare(strict_types=1);

namespace Framework\Cache;

/**
 * Cache storage for rate limiter state, kept apart from the page cache.
 *
 * Lockouts must outlive a cache clear, file-limit eviction and CACHE_ENABLED=false,
 * so this store lives in its own directory, is always on and has no file cap.
 * Entries still expire through their TTL and are removed by the normal GC.
 */
final class RateLimitStore extends CacheService
{
    /** Marks a hard lockout in every limiter's key: "{namespace}:hardlock:{subject}". */
    private const HARD_LOCK_MARKER = ':hardlock:';

    /** Marks a subject that must pass the sign-in check: "{namespace}:challenge:{subject}". */
    private const CHALLENGE_MARKER = ':challenge:';

    /**
     * @param  string  $path  Absolute path to the limiter directory
     */
    public function __construct(string $path)
    {
        parent::__construct(cachePath: $path, enabled: true, maxFiles: 0);
    }

    /**
     * Store a marker only if none is live under the key yet.
     *
     * Opening with mode 'x' fails when the file exists, on Windows and POSIX
     * alike, so of two requests racing for one key exactly one gets true.
     * Claims are not indexed: nothing lists them, and GC prunes them by file.
     *
     * @param  string  $key  Key to claim
     * @param  int  $ttl  Seconds the claim stays in force
     * @return bool True if this call made the claim, false if it was already taken
     *
     * @throws \RuntimeException If the limiter directory cannot be written
     */
    public function claim(string $key, int $ttl): bool
    {
        $file = $this->getCachePath().'/'.$this->hashKey($key).'.cache';

        // get() deletes an expired entry, which frees the key for this claim
        if ($this->get($key) !== null) {
            return false;
        }

        $handle = @fopen($file, 'x');
        if ($handle === false) {
            if (is_file($file)) {
                return false;
            }

            throw new \RuntimeException('Cannot write to the rate limit store at '.$this->getCachePath());
        }

        fwrite($handle, str_pad((string) (time() + $ttl), 10, '0', STR_PAD_LEFT).serialize('1'));
        fclose($handle);

        return true;
    }

    /**
     * List the hard lockouts and sign-in check requirements that are still in force.
     *
     * @return list<array{namespace: string, subject: string, locked_at: int, expires_at: int, manual: bool, challenge: bool}>
     */
    public function hardLocks(): array
    {
        $locks = [];

        foreach ($this->readIndex() as $key => $hash) {
            $marker = self::HARD_LOCK_MARKER;
            $position = strpos($key, $marker);
            if ($position === false) {
                $marker = self::CHALLENGE_MARKER;
                $position = strpos($key, $marker);
            }
            if ($position === false) {
                continue;
            }

            $state = json_decode((string) $this->get($key), true);
            $expiresAt = $this->expiryOf($hash);
            if (!is_array($state) || $expiresAt === null) {
                continue;
            }

            $locks[] = [
                'namespace' => substr($key, 0, $position),
                'subject' => substr($key, $position + strlen($marker)),
                // Manual blocks backdate locked_at so the login wrappers count the wait right; blocked_at is the real time
                'locked_at' => (int) ($state['blocked_at'] ?? $state['locked_at'] ?? 0),
                'expires_at' => $expiresAt,
                'manual' => !empty($state['manual']),
                'challenge' => $marker === self::CHALLENGE_MARKER,
            ];
        }

        return $locks;
    }

    /**
     * Delete every limiter entry that belongs to an email address or IP.
     *
     * Matches on the key's segments rather than a fixed list of limiters, so a
     * limiter added later is covered without touching this class.
     *
     * @param  string  $subject  Email address or IP the keys were built from
     * @return int Number of entries removed
     */
    public function forgetSubject(string $subject): int
    {
        if ($subject === '') {
            return 0;
        }

        // Keys keep an email as typed, so "A@x.com" and "a@x.com" are separate entries to clear
        $needle = ':'.strtolower($subject);

        $removed = 0;
        foreach (array_keys($this->readIndex()) as $key) {
            $lowered = strtolower($key);
            $matches = str_ends_with($lowered, $needle) || str_contains($lowered, $needle.':');
            if ($matches && $this->delete($key)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Read the expiry timestamp from a cache file's header.
     *
     * @return int|null Unix time the entry expires, or null if it is gone or already expired
     */
    private function expiryOf(string $hash): ?int
    {
        $file = $this->getCachePath().'/'.$hash.'.cache';
        $header = is_file($file) ? @file_get_contents($file, false, null, 0, 10) : false;
        if ($header === false || !ctype_digit($header)) {
            return null;
        }

        $expiresAt = (int) $header;

        return $expiresAt > time() ? $expiresAt : null;
    }
}
