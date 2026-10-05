<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\TrafficEventModel;
use App\Models\TrafficHitModel;
use Framework\Core\Request;

/**
 * Notes a sign-up with where the visit that led to it came from: how the visit
 * began, and the last page read before signing up. Both come from the visit's
 * counted views, so a visit that wasn't counted leaves no sign-up event.
 */
class SignupAttribution
{
    /** The daily id changes at midnight UTC, so older views can't be found anyway. */
    private const LOOKBACK_HOURS = 24;

    private const MAX_VIEWS = 200;

    public function __construct(
        private TrafficSettings $settings,
        private UserAgentClassifier $agents,
        private VisitorIdentity $identity,
        private VisitorCookie $cookie,
        private TrafficHitModel $hits,
        private TrafficEventModel $events,
        private int $visitMinutes,
    ) {}

    /**
     * Call before the new account signs in, while the visit's views are still under the guest's ids.
     *
     * @return bool Whether a sign-up event was stored
     */
    public function record(Request $request): bool
    {
        if (!$this->counts($request)) {
            return false;
        }

        $visit = $this->currentVisit($request);
        if ($visit === []) {
            return false;
        }

        $entry = $visit[array_key_last($visit)];
        // Arriving from another part of Lexicon is still moving around the site.
        $fromLexicon = $entry['channel'] === 'lexicon';

        $this->events->recordSignup([
            'channel' => $fromLexicon ? 'internal' : (string) $entry['channel'],
            'referrer_source' => $fromLexicon ? null : $entry['referrer_source'],
            'utm_source' => $entry['utm_source'],
            'utm_medium' => $entry['utm_medium'],
            'utm_campaign' => $entry['utm_campaign'],
            'came_from' => $this->cameFrom($visit),
        ]);

        return true;
    }

    private function counts(Request $request): bool
    {
        return $this->settings->enabled()
            && !TrafficRecorder::optedOut($request)
            && !$this->agents->isBot((string) $request->header('User-Agent', ''), $this->settings->extraBotPatterns());
    }

    /**
     * The views of the visit going on now, newest first: back from now until a
     * gap longer than one visit allows.
     *
     * @return list<array<string, mixed>>
     */
    private function currentVisit(Request $request): array
    {
        $utc = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable('now', $utc);
        $hashes = [$this->identity->dailyFor($request, $now)];

        $cookieId = $this->cookie->read($request);
        if ($cookieId !== null) {
            $hashes[] = $this->identity->forCookie($cookieId);
        }

        $visit = [];
        $later = $now->getTimestamp();

        foreach ($this->hits->latestForVisitors($hashes, self::LOOKBACK_HOURS, self::MAX_VIEWS) as $view) {
            $at = (new \DateTimeImmutable((string) $view['created_at'], $utc))->getTimestamp();

            if ($later - $at > $this->visitMinutes * 60) {
                break;
            }

            $visit[] = $view;
            $later = $at;
        }

        return $visit;
    }

    /**
     * The last page read before signing up, passing over the sign-in and sign-up
     * pages themselves. In the LexiconSource format.
     *
     * @param  non-empty-list<array<string, mixed>>  $visit  Newest first
     */
    private function cameFrom(array $visit): string
    {
        foreach ($visit as $view) {
            if ($view['page_type'] === 'auth') {
                continue;
            }

            if ($view['blog_id'] !== null) {
                return LexiconSource::blog((int) $view['blog_id']);
            }

            return in_array($view['page_type'], PlatformPages::PAGE_TYPES, true) ? (string) $view['page_type'] : 'other';
        }

        return 'auth';
    }
}
