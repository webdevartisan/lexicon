<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEventModel;
use Framework\Core\Request;

/**
 * Notes a sign-up with where the visit that led to it came from: how the visit
 * began, and the last page read before signing up. Both come from the visit,
 * so a sign-up outside a counted visit leaves no event.
 */
class SignupAttribution
{
    public function __construct(
        private AnalyticsSettings $settings,
        private UserAgentClassifier $agents,
        private VisitFinder $finder,
        private AnalyticsEventModel $events,
    ) {}

    /**
     * Call before the new account signs in, while the visit is still under the guest's ids.
     *
     * @return bool Whether a sign-up event was stored
     */
    public function record(Request $request): bool
    {
        if (!$this->counts($request)) {
            return false;
        }

        $visit = $this->finder->open($request, null);
        if ($visit === null) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $event = [
            'name' => 'signup',
            'props' => ['came_from' => $this->cameFrom($this->events->viewsOfVisit((string) $visit['id']))],
            'local_date' => $now->format('Y-m-d'),
            'local_hour' => (int) $now->format('G'),
        ];

        // Arriving from another part of Lexicon is still moving around the site.
        if ($visit['channel'] === 'lexicon') {
            $event += ['channel' => 'internal', 'referrer_source' => null];
        }

        return $this->events->recordInVisit($event, $visit, false);
    }

    private function counts(Request $request): bool
    {
        return $this->settings->enabled()
            && !AnalyticsRecorder::optedOut($request)
            && !$this->agents->isBot((string) $request->header('User-Agent', ''), $this->settings->extraBotPatterns());
    }

    /**
     * The last page read before signing up, passing over the sign-in and sign-up
     * pages themselves. In the LexiconSource format.
     *
     * @param  list<array<string, mixed>>  $views  Newest first
     */
    private function cameFrom(array $views): string
    {
        foreach ($views as $view) {
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
