<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsVisitModel;
use Framework\Core\Request;

/**
 * Who the reader is counted as, and the visit they are on. The page view
 * recorder and the server-side events (goals, sign-ups) use the same rules, so
 * a like lands in the visit its page views are in.
 */
class VisitFinder
{
    public function __construct(
        private VisitorIdentity $identity,
        private VisitorCookie $cookie,
        private AnalyticsVisitModel $visits,
        private int $visitMinutes,
    ) {}

    /**
     * A signed-in reader is recognised by their account only if they allowed
     * analytics, which is also the only time there is a cookie id.
     *
     * @param  array<string, mixed>|null  $viewer
     * @return array{0: string, 1: string} Hash and kind
     */
    public function visitor(Request $request, ?array $viewer, ?string $cookieId, \DateTimeImmutable $now): array
    {
        if ($viewer !== null && $cookieId !== null) {
            return [$this->identity->forAccount((int) $viewer['id']), 'account'];
        }

        if ($cookieId !== null) {
            return [$this->identity->forCookie($cookieId), 'cookie'];
        }

        return [$this->identity->dailyFor($request, $now), 'daily'];
    }

    /**
     * The visit the reader making this request is on, or null when none of their
     * page views was counted in the last visit gap.
     *
     * @param  array<string, mixed>|null  $viewer
     * @return array<string, mixed>|null
     */
    public function open(Request $request, ?array $viewer): ?array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hashes = [$this->identity->dailyFor($request, $now)];

        $cookieId = $this->cookie->read($request);
        if ($cookieId !== null) {
            $hashes[] = $this->identity->forCookie($cookieId);
            if ($viewer !== null) {
                $hashes[] = $this->identity->forAccount((int) $viewer['id']);
            }
        }

        return $this->visits->openFor($hashes, $this->visitMinutes);
    }

    public function visitMinutes(): int
    {
        return $this->visitMinutes;
    }
}
