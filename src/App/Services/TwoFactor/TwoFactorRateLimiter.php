<?php

declare(strict_types=1);

namespace App\Services\TwoFactor;

use Framework\Helpers\RateLimiter;

/**
 * Throttles second-step codes per account. A six digit code is guessable in a
 * few hundred thousand tries, so wrong answers are charged to the user whose
 * password was already right, whatever address they come from.
 */
final class TwoFactorRateLimiter
{
    private int $maxAttempts = 5;

    private int $decaySeconds = 900;

    public function __construct(private RateLimiter $limiter) {}

    public function tooManyAttempts(int $userId): bool
    {
        return $this->limiter->tooManyAttempts($this->key($userId), $this->maxAttempts, $this->decaySeconds);
    }

    public function hit(int $userId): void
    {
        $this->limiter->hit($this->key($userId), $this->decaySeconds);
    }

    public function clear(int $userId): void
    {
        $this->limiter->clear($this->key($userId));
    }

    public function availableIn(int $userId): int
    {
        return $this->limiter->availableIn($this->key($userId), $this->maxAttempts, $this->decaySeconds);
    }

    private function key(int $userId): string
    {
        return "twofactor:user:{$userId}";
    }
}
