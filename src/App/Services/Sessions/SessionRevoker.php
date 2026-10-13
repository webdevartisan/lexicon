<?php

declare(strict_types=1);

namespace App\Services\Sessions;

use Framework\Session;
use Framework\Session\SessionManager;
use Framework\Session\SessionRegistry;

/**
 * Ends signed-in sessions: one device, every other device, or all of them.
 *
 * Deleting the user_sessions row is what ends a session on every driver; the
 * driver is then asked to drop the data too where it can find it by hash.
 * Sessions an administrator is impersonating the user in are never touched
 * here: they belong to the administrator and end with impersonation.
 */
class SessionRevoker
{
    public function __construct(
        private SessionRegistry $registry,
        private SessionManager $sessions,
        private Session $session,
    ) {}

    /**
     * End every session of the user except the one making this request.
     *
     * @return int Sessions ended
     */
    public function endOthers(int $userId, string $reason): int
    {
        $current = $this->session->hashedId();

        return $this->end($userId, $current !== '' ? $current : null, null, $reason);
    }

    /**
     * End one of the user's sessions, by its hash. A hash that is not theirs
     * ends nothing.
     */
    public function endOne(int $userId, string $hash, string $reason): bool
    {
        return $this->end($userId, null, $hash, $reason) === 1;
    }

    /**
     * End every session of the user, this one included.
     *
     * @return int Sessions ended
     */
    public function endAll(int $userId, string $reason): int
    {
        return $this->end($userId, null, null, $reason);
    }

    private function end(int $userId, ?string $except, ?string $only, string $reason): int
    {
        $hashes = $this->registry->deleteForUser($userId, $except, $only);

        if ($hashes === []) {
            return 0;
        }

        $this->sessions->driver()->forgetHashes($hashes);

        audit()->log(
            (int) (auth()->user()['id'] ?? 0),
            'user.sessions_ended',
            'user',
            $userId,
            ['reason' => $reason, 'count' => count($hashes)],
            $this->session->clientIp()
        );

        return count($hashes);
    }
}
