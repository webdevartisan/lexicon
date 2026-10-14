<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Cache\RateLimitStore;

/**
 * Lets an admin see who is locked out by a rate limiter and lift it.
 *
 * Lockouts are keyed by an email (login, password reset) or an IP (identify,
 * CSP reports, comments), so one unlock covers whichever the admin enters.
 */
class RateLimitAdminService
{
    /** Readable names for limiter key namespaces; unknown ones are shown as stored. */
    private const LABELS = [
        'login' => 'Login',
        'reset' => 'Password reset',
        'identify' => 'Sign-in identify step',
        'csp_report' => 'CSP reports',
        'comment:submit' => 'Comment submissions',
        'comment:interact' => 'Comment votes and reports',
        'manual' => 'Site-wide block',
    ];

    /** How long a manual block can last, by the value the form posts. */
    public const DURATIONS = [
        '1h' => ['label' => '1 hour', 'seconds' => 3600],
        '24h' => ['label' => '24 hours', 'seconds' => 86400],
        '7d' => ['label' => '7 days', 'seconds' => 604800],
        '30d' => ['label' => '30 days', 'seconds' => 2592000],
    ];

    /** The login and reset limiters count a lock's wait from a fixed one-hour window. */
    private const WAIT_WINDOW = 3600;

    public function __construct(private RateLimitStore $store) {}

    /**
     * Hard lockouts still in force, longest remaining first.
     *
     * @return list<array{label: string, subject: string, kind: string, locked_at: int, expires_at: int, seconds_left: int, manual: bool}>
     */
    public function blocked(): array
    {
        $now = time();
        $rows = [];

        foreach ($this->store->hardLocks() as $lock) {
            $rows[] = [
                'label' => self::LABELS[$lock['namespace']] ?? $lock['namespace'],
                'subject' => $lock['subject'],
                'kind' => $this->kindOf($lock['subject']),
                'locked_at' => $lock['locked_at'],
                'expires_at' => $lock['expires_at'],
                'seconds_left' => max(0, $lock['expires_at'] - $now),
                'manual' => $lock['manual'],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['seconds_left'] <=> $a['seconds_left']);

        return $rows;
    }

    /**
     * Whether a value is an email address or an IP, the only things limiters key on.
     */
    public function isValidSubject(string $subject): bool
    {
        return $this->kindOf($subject) !== 'unknown';
    }

    /**
     * Lift every limiter entry (locks and counters) held against an email or IP.
     *
     * @param  string  $subject  Email address or IP to unlock
     * @param  string  $adminIp  IP of the admin, for the audit trail
     * @return int Number of entries removed
     *
     * @throws \InvalidArgumentException If the subject is neither an email nor an IP
     */
    public function unlock(string $subject, string $adminIp): int
    {
        $subject = trim($subject);
        $kind = $this->kindOf($subject);
        if ($kind === 'unknown') {
            throw new \InvalidArgumentException('Enter a valid email address or IP address.');
        }

        $removed = $this->store->forgetSubject($subject);

        $user = auth()->user();
        if ($user) {
            audit()->log($user['id'], 'rate_limit.unlocked', 'system', 0, [
                'subject' => $subject,
                'kind' => $kind,
                'entries_removed' => $removed,
            ], $adminIp);
        }

        return $removed;
    }

    /**
     * Block an email or IP by hand for a fixed time.
     *
     * An IP is refused on every request (see BlockedIpMiddleware). An email is
     * locked out of login and password reset through the same keys the limiters
     * already check, so it needs no new enforcement.
     *
     * @param  string  $subject  Email address or IP to block
     * @param  string  $duration  Key of self::DURATIONS
     * @param  string  $adminIp  IP of the admin, for the audit trail and to stop them blocking themselves
     *
     * @throws \InvalidArgumentException If the subject or duration is invalid, or it is the admin's own IP
     */
    public function block(string $subject, string $duration, string $adminIp): void
    {
        $subject = trim($subject);
        $kind = $this->kindOf($subject);
        if ($kind === 'unknown') {
            throw new \InvalidArgumentException('Enter a valid email address or IP address.');
        }
        if (!isset(self::DURATIONS[$duration])) {
            throw new \InvalidArgumentException('Choose how long the block lasts.');
        }
        if ($kind === 'ip' && $subject === $adminIp) {
            throw new \InvalidArgumentException('That is your own IP address. Blocking it would lock you out.');
        }

        $seconds = self::DURATIONS[$duration]['seconds'];
        $now = time();

        if ($kind === 'ip') {
            $this->writeBlock('manual:hardlock:'.$subject, $now, $now, $seconds);
        } else {
            // Backdated so the limiters' remaining-wait maths (window minus elapsed) lands on the real expiry
            $lockedAt = $now + $seconds - self::WAIT_WINDOW;
            $this->writeBlock('login:hardlock:'.$subject, $lockedAt, $now, $seconds);
            $this->writeBlock('reset:hardlock:'.$subject, $lockedAt, $now, $seconds);
        }

        $user = auth()->user();
        if ($user) {
            audit()->log($user['id'], 'rate_limit.blocked', 'system', 0, [
                'subject' => $subject,
                'kind' => $kind,
                'duration' => self::DURATIONS[$duration]['label'],
            ], $adminIp);
        }
    }

    private function writeBlock(string $key, int $lockedAt, int $blockedAt, int $seconds): void
    {
        $this->store->set($key, json_encode([
            'locked_at' => $lockedAt,
            'blocked_at' => $blockedAt,
            'manual' => true,
        ]), $seconds);
    }

    /**
     * @return string 'email', 'ip' or 'unknown'
     */
    private function kindOf(string $subject): string
    {
        if (filter_var($subject, FILTER_VALIDATE_IP) !== false) {
            return 'ip';
        }

        return filter_var($subject, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'unknown';
    }
}
