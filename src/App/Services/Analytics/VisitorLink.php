<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsVisitModel;
use App\Services\ConsentService;
use Framework\Core\Request;

/**
 * Keeps one reader as one visitor when their id changes mid-visit: accepting or
 * turning off analytics, or signing in. The current visit and its events move to the new id.
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
        private AnalyticsEventModel $events,
        private AnalyticsVisitModel $visits,
        private int $visitMinutes,
    ) {}

    /**
     * A guest who accepted analytics and was just given the cookie.
     */
    public function toCookie(Request $request, string $cookieId): int
    {
        return $this->move([$this->identity->dailyFor($request, $this->now())], $this->identity->forCookie($cookieId), 'cookie');
    }

    /**
     * A reader who just signed in. Views made as a guest only join the account when
     * the reader accepted analytics, since that ties them to a person.
     */
    public function toAccount(Request $request, int $userId): int
    {
        if (!$this->consent->allows('analytics') || AnalyticsRecorder::optedOut($request)) {
            return 0;
        }

        $from = [$this->identity->dailyFor($request, $this->now())];
        $cookieId = $this->cookie->read($request);
        if ($cookieId !== null) {
            $from[] = $this->identity->forCookie($cookieId);
        }

        return $this->move($from, $this->identity->forAccount($userId), 'account');
    }

    /**
     * A visitor who turned analytics off. The cookie goes, and the current visit
     * moves to the anonymous id it is counted under from now on, including the
     * views a signed-in reader made under their account.
     */
    public function dropCookie(Request $request, ?int $userId = null): void
    {
        $cookieId = $this->cookie->read($request);
        if ($cookieId !== null) {
            $from = [$this->identity->forCookie($cookieId)];
            if ($userId !== null) {
                $from[] = $this->identity->forAccount($userId);
            }

            $this->move($from, $this->identity->dailyFor($request, $this->now()), 'daily');
        }

        $this->cookie->forget($request);
    }

    /**
     * @param  list<string>  $from
     * @return int Events moved
     */
    private function move(array $from, string $to, string $kind): int
    {
        $this->visits->reassignRecent($from, $to, $kind, $this->visitMinutes);

        return $this->events->reassignRecent($from, $to, $kind, $this->visitMinutes);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
