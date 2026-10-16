<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Helpers\RateLimiter;

/**
 * Throttles test emails sent from the control panel, per user.
 *
 * A test send goes straight to the transport, to any address, with content the
 * sender typed, and the permissions behind it can be given to non-admin roles.
 * One budget covers the Email Delivery check and the template editor's send.
 */
final class TestEmailRateLimiter
{
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 3600;

    public function __construct(private RateLimiter $limiter) {}

    /**
     * Whether this user has used up their test sends for the window.
     */
    public function tooManyAttempts(int $userId): bool
    {
        return $this->limiter->tooManyAttempts($this->key($userId), self::MAX_ATTEMPTS, self::DECAY_SECONDS);
    }

    /**
     * Count one test send.
     */
    public function hit(int $userId): void
    {
        $this->limiter->hit($this->key($userId), self::DECAY_SECONDS);
    }

    /**
     * Seconds until this user may send another test, or 0 when not throttled.
     */
    public function availableIn(int $userId): int
    {
        return $this->limiter->availableIn($this->key($userId), self::MAX_ATTEMPTS, self::DECAY_SECONDS);
    }

    private function key(int $userId): string
    {
        return "test-email:user:{$userId}";
    }
}
