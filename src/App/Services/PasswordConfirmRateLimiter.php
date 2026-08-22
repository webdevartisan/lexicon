<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Helpers\RateLimiter;

/**
 * Throttles password-confirmation attempts per user.
 *
 * The Preferences email gate and the Security current-password field both
 * verify the account password against a hash. Without a limit, a stolen session
 * is an unlimited oracle for confirming that password. Keyed on the user id
 * because both flows already have an authenticated session.
 *
 * The underlying RateLimiter is a fixed window whose block does not extend when
 * further attempts arrive, so the ceiling is deliberately low and the window
 * short rather than assuming a rolling window.
 */
final class PasswordConfirmRateLimiter
{
    private int $maxAttempts = 5;

    private int $decaySeconds = 900; // 15 minutes

    public function __construct(private RateLimiter $limiter) {}

    /**
     * Whether this user has spent their confirmation attempts for the window.
     */
    public function tooManyAttempts(int $userId): bool
    {
        return $this->limiter->tooManyAttempts($this->key($userId), $this->maxAttempts, $this->decaySeconds);
    }

    /**
     * Record one failed confirmation.
     */
    public function hit(int $userId): void
    {
        $this->limiter->hit($this->key($userId), $this->decaySeconds);
    }

    /**
     * Reset the counter, e.g. after a correct confirmation.
     */
    public function clear(int $userId): void
    {
        $this->limiter->clear($this->key($userId));
    }

    /**
     * Seconds until this user may try again, or 0 when not throttled.
     */
    public function availableIn(int $userId): int
    {
        return $this->limiter->availableIn($this->key($userId), $this->maxAttempts, $this->decaySeconds);
    }

    private function key(int $userId): string
    {
        return "pwconfirm:user:{$userId}";
    }
}
