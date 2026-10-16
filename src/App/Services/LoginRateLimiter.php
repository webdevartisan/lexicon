<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\CacheService;
use Framework\Helpers\RateLimiter;

/**
 * Multi-tier login rate limiting with a sign-in check instead of an account lockout.
 *
 * Three decaying tiers count failures:
 * - Per-email: across every IP, so spreading guesses over many addresses does not help
 * - Per-IP: one address guessing at many accounts
 * - Per-IP-email: one address guessing at one account
 *
 * Only the IP tiers refuse an attempt outright. Anything keyed on the email alone
 * would let a stranger lock the owner out, so the email tier and repeated soft
 * blocks put the account behind the sign-in check instead (see CaptchaInterface).
 * The one email-keyed lock left is the ceiling on failures that passed the check.
 */
final class LoginRateLimiter
{
    private RateLimiter $limiter;

    private CacheService $cache;

    // Soft limits (exponential decay)
    private int $emailMaxAttempts = 5;  // Per-email limit

    private int $ipEmailMaxAttempts = 8;  // Per-IP+email limit

    private int $ipMaxAttempts = 20; // Per-IP limit

    private int $decaySeconds = 300; // 5 minutes half-life

    // Escalation (after repeated soft blocks)
    private int $maxSoftBlocksPerHour = 3;

    private int $emailChallengeAt;

    private int $ipChallengeAt;

    private int $challengeSeconds;

    private int $solvedFailureCeiling;

    private int $ceilingWindowSeconds;

    /**
     * @param  array{email_failures?: int, ip_failures?: int, challenge_seconds?: int, solved_failure_ceiling?: int, ceiling_window_seconds?: int}  $config  The login section of config/captcha.php
     */
    public function __construct(RateLimiter $limiter, CacheService $cache, array $config = [])
    {
        $this->limiter = $limiter;
        $this->cache = $cache;
        $this->emailChallengeAt = $config['email_failures'] ?? 3;
        $this->ipChallengeAt = $config['ip_failures'] ?? 10;
        $this->challengeSeconds = $config['challenge_seconds'] ?? 3600;
        $this->solvedFailureCeiling = $config['solved_failure_ceiling'] ?? 100;
        $this->ceilingWindowSeconds = $config['ceiling_window_seconds'] ?? 86400;
    }

    /**
     * Whether the attempt is refused whatever the client sends.
     *
     * Covers the hard lock (failure ceiling or an admin block) and the two IP
     * tiers. Checks only; call hit() after a failed login.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $email  Email being authenticated
     * @return bool True if refused, false otherwise
     */
    public function tooManyAttempts(string $ip, string $email): bool
    {
        $email = $this->normalizeEmail($email);

        if ($this->limiter->isLocked("login:hardlock:{$email}")) {
            return true;
        }

        return $this->limiter->tooManyAttempts("login:ip:{$ip}", $this->ipMaxAttempts, $this->decaySeconds)
            || $this->limiter->tooManyAttempts("login:ip_email:{$ip}:{$email}", $this->ipEmailMaxAttempts, $this->decaySeconds);
    }

    /**
     * Whether the attempt has to pass the sign-in check before the password is tried.
     *
     * Starts below the email tier's limit, so the check is always in place
     * before the failures that used to lock the account can build up.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $email  Email being authenticated
     */
    public function requiresChallenge(string $ip, string $email): bool
    {
        $email = $this->normalizeEmail($email);

        // Three failures a second apart have already decayed to 2.99, so a plain >= 3
        // would let the next attempt through unchecked. Half an attempt of slack
        // keeps the threshold meaning what it says for about a minute.
        return $this->cache->get("login:challenge:{$email}") !== null
            || $this->limiter->score("login:email:{$email}", $this->decaySeconds) >= $this->emailChallengeAt - 0.5
            || $this->limiter->score("login:ip:{$ip}", $this->decaySeconds) >= $this->ipChallengeAt - 0.5;
    }

    /**
     * Difficulty step for the next challenge: one per soft block this hour.
     *
     * @param  string  $email  Email being authenticated
     */
    public function challengeLevel(string $email): int
    {
        $state = $this->windowState('login:soft_blocks:'.$this->normalizeEmail($email), 3600);

        return $state['count'];
    }

    /**
     * Record a failed login attempt and escalate when a tier is exceeded.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $email  Email that failed authentication
     * @param  bool  $passedCheck  True when the attempt passed the sign-in check, which counts toward the ceiling
     */
    public function hit(string $ip, string $email, bool $passedCheck = false): void
    {
        $email = $this->normalizeEmail($email);

        $emailKey = "login:email:{$email}";
        $ipKey = "login:ip:{$ip}";
        $ipEmailKey = "login:ip_email:{$ip}:{$email}";

        $this->limiter->hit($emailKey, $this->decaySeconds);
        $this->limiter->hit($ipKey, $this->decaySeconds);
        $this->limiter->hit($ipEmailKey, $this->decaySeconds);

        $emailBlocked = $this->limiter->tooManyAttempts($emailKey, $this->emailMaxAttempts, $this->decaySeconds);
        $ipBlocked = $this->limiter->tooManyAttempts($ipKey, $this->ipMaxAttempts, $this->decaySeconds);
        $ipEmailBlocked = $this->limiter->tooManyAttempts($ipEmailKey, $this->ipEmailMaxAttempts, $this->decaySeconds);

        if ($emailBlocked || $ipBlocked || $ipEmailBlocked) {
            $this->registerSoftBlock($email);
        }

        if ($passedCheck) {
            $this->registerCheckedFailure($email);
        }
    }

    /**
     * Record an attempt refused at the sign-in check against the IP only.
     *
     * The account is not charged, or anyone could push it into the check's
     * harder levels without solving anything. The IP is, so a client that keeps
     * posting without an answer reaches the IP limit and gets refused outright.
     *
     * @param  string  $ip  Client IP address
     */
    public function hitIp(string $ip): void
    {
        $this->limiter->hit("login:ip:{$ip}", $this->decaySeconds);
    }

    /**
     * Clear all rate limit counters for an IP/email (e.g., after successful login).
     *
     * @param  string  $ip  Client IP address
     * @param  string  $email  Email address
     */
    public function clear(string $ip, string $email): void
    {
        $email = $this->normalizeEmail($email);

        $this->limiter->clear("login:ip:{$ip}");
        $this->limiter->clear("login:ip_email:{$ip}:{$email}");
        $this->clearAccount($email);
    }

    /**
     * Lift everything held against an account, but nothing held against an IP.
     *
     * Used after a password reset: the owner has just proven they hold the
     * mailbox. An admin block stays, since lifting it is the admin's call.
     *
     * @param  string  $email  Email address
     */
    public function clearAccount(string $email): void
    {
        $email = $this->normalizeEmail($email);
        $lockKey = "login:hardlock:{$email}";

        $lock = json_decode((string) $this->cache->get($lockKey), true);
        if (!is_array($lock) || empty($lock['manual'])) {
            $this->limiter->clearLock($lockKey);
        }

        $this->limiter->clear("login:email:{$email}");
        $this->cache->delete("login:soft_blocks:{$email}");
        $this->cache->delete("login:challenge:{$email}");
        $this->cache->delete("login:checked_failures:{$email}");
    }

    /**
     * Get seconds until the refusal from tooManyAttempts() ends.
     *
     * @param  string  $ip  Client IP address
     * @param  string  $email  Email address
     * @return int Seconds until allowed, or 0 if not refused
     */
    public function availableIn(string $ip, string $email): int
    {
        $email = $this->normalizeEmail($email);

        $lockData = $this->cache->get("login:hardlock:{$email}");
        if ($lockData !== null) {
            $state = json_decode($lockData, true);
            // Ceiling locks carry their end; manual blocks backdate locked_at to fit the one hour default
            $until = (int) ($state['until'] ?? ((int) ($state['locked_at'] ?? 0) + 3600));
            $remaining = $until - time();

            if ($remaining > 0) {
                return $remaining;
            }
        }

        $ipWait = $this->limiter->availableIn("login:ip:{$ip}", $this->ipMaxAttempts, $this->decaySeconds);
        $ipEmailWait = $this->limiter->availableIn("login:ip_email:{$ip}:{$email}", $this->ipEmailMaxAttempts, $this->decaySeconds);

        return max($ipWait, $ipEmailWait);
    }

    /**
     * Fold case variants into one identity, since findByEmail matches case-insensitively.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Count a soft block and put the account behind the check after too many in an hour.
     *
     * @param  string  $email  Normalized email address
     */
    private function registerSoftBlock(string $email): void
    {
        $count = $this->incrementWindow("login:soft_blocks:{$email}", 3600);

        if ($count >= $this->maxSoftBlocksPerHour) {
            $this->cache->set("login:challenge:{$email}", json_encode(['since' => time()]) ?: '{}', $this->challengeSeconds);
        }
    }

    /**
     * Count a failure that passed the check, and lock the account at the ceiling.
     *
     * @param  string  $email  Normalized email address
     */
    private function registerCheckedFailure(string $email): void
    {
        $count = $this->incrementWindow("login:checked_failures:{$email}", $this->ceilingWindowSeconds);

        if ($count >= $this->solvedFailureCeiling) {
            $now = time();
            $this->cache->set("login:hardlock:{$email}", json_encode([
                'locked_at' => $now,
                'until' => $now + $this->ceilingWindowSeconds,
                'ceiling' => true,
            ]) ?: '{}', $this->ceilingWindowSeconds);
        }
    }

    /**
     * Add one to a counter that restarts once its window has passed.
     *
     * @return int The count after this increment
     */
    private function incrementWindow(string $key, int $window): int
    {
        $state = $this->windowState($key, $window);
        $count = $state['count'] + 1;

        $this->cache->set($key, json_encode([
            'count' => $count,
            'window_start' => $state['window_start'],
        ]) ?: '{}', $window);

        return $count;
    }

    /**
     * @return array{count: int, window_start: int} Current count, zero once the window has passed
     */
    private function windowState(string $key, int $window): array
    {
        $now = time();
        $state = json_decode((string) $this->cache->get($key), true);

        $windowStart = (int) ($state['window_start'] ?? $now);
        if (!is_array($state) || $now - $windowStart > $window) {
            return ['count' => 0, 'window_start' => $now];
        }

        return ['count' => (int) ($state['count'] ?? 0), 'window_start' => $windowStart];
    }
}
