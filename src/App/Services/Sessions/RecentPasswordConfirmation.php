<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use App\Models\UserModel;
use App\Services\PasswordConfirmRateLimiter;
use Framework\Session;

/**
 * "Confirm your password" that holds for a few minutes, so signing out three
 * devices in a row asks once. Checks are throttled like every other password
 * gate, or a stolen session would be an unlimited password oracle.
 */
class RecentPasswordConfirmation
{
    private const KEY = 'password_confirmed_at';

    public function __construct(
        private Session $session,
        private UserModel $users,
        private PasswordConfirmRateLimiter $throttle,
        private int $windowMinutes,
    ) {}

    public function isFresh(): bool
    {
        $at = (int) $this->session->get(self::KEY, 0);

        return $at > 0 && time() - $at < $this->windowMinutes * 60;
    }

    /**
     * True when the password was confirmed recently or is right now.
     */
    public function attempt(int $userId, ?string $password): bool
    {
        if ($this->isFresh()) {
            return true;
        }

        if ($this->throttle->tooManyAttempts($userId)) {
            return false;
        }

        if ($password === null || $password === '' || !$this->users->verifyPassword($userId, $password)) {
            $this->throttle->hit($userId);

            return false;
        }

        $this->throttle->clear($userId);
        $this->session->set(self::KEY, time());

        return true;
    }
}
