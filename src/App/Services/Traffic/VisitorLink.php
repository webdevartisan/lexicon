<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\TrafficHitModel;
use App\Services\ConsentService;
use Framework\Core\Request;

/**
 * Keeps one reader as one visitor when their id changes mid-visit: accepting or
 * turning off analytics, or signing in. Their views from the current visit move to the new id.
 *
 * Runs once, when the cookie is issued or removed or the session signs in. Running it on
 * every view would keep pulling in the views of anyone else behind the same IP and browser.
 */
class VisitorLink
{
    public function __construct(
        private VisitorIdentity $identity,
        private VisitorCookie $cookie,
        private ConsentService $consent,
        private TrafficHitModel $hits,
        private int $visitMinutes,
    ) {}

    /**
     * A guest who accepted analytics and was just given the cookie.
     */
    public function toCookie(Request $request, string $cookieId): int
    {
        return $this->hits->reassignRecent(
            [$this->identity->dailyFor($request, $this->now())],
            $this->identity->forCookie($cookieId),
            'cookie',
            $this->visitMinutes
        );
    }

    /**
     * A reader who just signed in. Views made as a guest only join the account when
     * the reader accepted analytics, since that ties them to a person.
     */
    public function toAccount(Request $request, int $userId): int
    {
        if (!$this->consent->allows('analytics') || TrafficRecorder::optedOut($request)) {
            return 0;
        }

        $from = [$this->identity->dailyFor($request, $this->now())];
        $cookieId = $this->cookie->read($request);
        if ($cookieId !== null) {
            $from[] = $this->identity->forCookie($cookieId);
        }

        return $this->hits->reassignRecent($from, $this->identity->forAccount($userId), 'account', $this->visitMinutes);
    }

    /**
     * A visitor who turned analytics off. The cookie goes, and the views from this
     * visit move to the anonymous id they are counted under from now on, including
     * the ones a signed-in reader made under their account.
     */
    public function dropCookie(Request $request, ?int $userId = null): void
    {
        $cookieId = $this->cookie->read($request);
        if ($cookieId !== null) {
            $from = [$this->identity->forCookie($cookieId)];
            if ($userId !== null) {
                $from[] = $this->identity->forAccount($userId);
            }

            $this->hits->reassignRecent($from, $this->identity->dailyFor($request, $this->now()), 'daily', $this->visitMinutes);
        }

        $this->cookie->forget($request);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
