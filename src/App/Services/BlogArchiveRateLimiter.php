<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Helpers\RateLimiter;

/**
 * Throttles blog archive exports and import uploads per user.
 *
 * Each export zips every post and file of a blog, and each import upload unpacks
 * up to the archive caps, so both are expensive enough to bound. Keyed on the
 * user, since only the owner of a signed-in session can reach either route.
 * Exports and imports count separately so a restore is never blocked by the
 * download that preceded it.
 */
final class BlogArchiveRateLimiter
{
    private int $maxAttempts = 10;

    private int $decaySeconds = 3600;

    public function __construct(private RateLimiter $limiter) {}

    /**
     * Whether this user has used up their attempts of this kind for the window.
     *
     * @param  string  $kind  'export' or 'import'
     */
    public function tooManyAttempts(int $userId, string $kind): bool
    {
        return $this->limiter->tooManyAttempts($this->key($userId, $kind), $this->maxAttempts, $this->decaySeconds);
    }

    /**
     * Count one attempt.
     *
     * @param  string  $kind  'export' or 'import'
     */
    public function hit(int $userId, string $kind): void
    {
        $this->limiter->hit($this->key($userId, $kind), $this->decaySeconds);
    }

    /**
     * Seconds until this user may try again, or 0 when not throttled.
     *
     * @param  string  $kind  'export' or 'import'
     */
    public function availableIn(int $userId, string $kind): int
    {
        return $this->limiter->availableIn($this->key($userId, $kind), $this->maxAttempts, $this->decaySeconds);
    }

    private function key(int $userId, string $kind): string
    {
        return "blog-archive:{$kind}:user:{$userId}";
    }
}
