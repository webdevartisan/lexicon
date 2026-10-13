<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use App\Models\UserModel;
use App\Services\Analytics\UserAgentClassifier;
use Closure;
use Framework\Session;

/**
 * What a signed-in session's user_sessions row records: who, from which
 * device and place, and how long it may last.
 *
 * Auth and ImpersonationService hold one on every request but use it only at
 * sign-in, so the classifier and the geo lookup are built on first use.
 */
class SessionRecordFactory
{
    /**
     * @param  Closure(): UserAgentClassifier  $classifier
     * @param  Closure(): SessionLocation  $location
     * @param  array<string, mixed>  $config  config/session.php
     */
    public function __construct(
        private Session $session,
        private UserModel $users,
        private Closure $classifier,
        private Closure $location,
        private array $config,
    ) {}

    /**
     * @param  int  $userId  The account the session acts as
     * @param  int|null  $impersonatedBy  The administrator behind it, while impersonating
     * @return array<string, mixed>
     */
    public function forUser(int $userId, ?int $impersonatedBy = null): array
    {
        $now = time();
        $ua = $this->session->userAgent();
        $ip = $this->session->clientIp();
        $classifier = ($this->classifier)();
        $client = $classifier->classify($ua);

        // An impersonated session is the administrator's, so it gets their limits.
        [$idleMinutes, $absoluteHours] = $this->isPrivileged($impersonatedBy ?? $userId)
            ? [$this->config['privileged_idle_minutes'], $this->config['privileged_absolute_hours']]
            : [$this->config['idle_minutes'], $this->config['absolute_hours']];

        return [
            'user_id' => $userId,
            'impersonated_by' => $impersonatedBy,
            'ip_address' => $ip,
            'user_agent' => mb_substr($ua, 0, 500),
            'device' => $client['device'],
            'browser' => $client['browser'],
            'browser_version' => $classifier->browserVersion($ua, $client['browser']),
            'os' => $client['os'],
            'os_version' => $classifier->osVersion($ua, $client['os']),
            'signed_in_at' => $now,
            'idle_seconds' => (int) $idleMinutes * 60,
            'absolute_expires_at' => $now + (int) $absoluteHours * 3600,
            ...($this->location)()->locate($ip),
        ];
    }

    /**
     * Anyone who can open the control panel.
     */
    private function isPrivileged(int $userId): bool
    {
        return in_array('administrator', $this->users->getUserRoles($userId), true)
            || in_array('access_control_panel', $this->users->getUserPermissions($userId), true);
    }
}
