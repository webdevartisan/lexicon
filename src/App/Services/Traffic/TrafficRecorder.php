<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\BlogModel;
use App\Models\TrafficHitModel;
use Framework\Core\Request;

/**
 * Decides whether a beacon is a real reader's page view and stores it if so.
 *
 * Refusals come back as one of the outcome constants. Database errors propagate.
 */
class TrafficRecorder
{
    public const RECORDED = 'recorded';

    public const DISABLED = 'disabled';

    public const BLOG_OFF = 'blog_off';

    public const PREFETCH = 'prefetch';

    public const OPTED_OUT = 'opted_out';

    public const BOT = 'bot';

    public const UNKNOWN_PAGE = 'unknown_page';

    public const EXCLUDED_PATH = 'excluded_path';

    public const MEMBER = 'member';

    public const SPAM = 'spam';

    public const DUPLICATE = 'duplicate';

    /**
     * @param  array<string, mixed>  $config  config/traffic.php
     */
    public function __construct(
        private TrafficSettings $settings,
        private PagePathResolver $pages,
        private UserAgentClassifier $agents,
        private ReferrerClassifier $referrers,
        private VisitorIdentity $identity,
        private CountryLookup $countries,
        private TrafficHitModel $hits,
        private BlogModel $blogs,
        private array $config,
    ) {}

    /**
     * @param  array<string, mixed>|null  $viewer  The signed-in user, or null for a guest
     * @param  string|null  $cookieId  The analytics cookie, only when the visitor accepted analytics
     */
    public function record(
        Request $request,
        BeaconPayload $payload,
        ?array $viewer,
        bool $impersonating,
        ?string $cookieId
    ): string {
        $refusal = $this->refuseRequest($request);
        if ($refusal !== null) {
            return $refusal;
        }

        $page = $this->pages->resolve($payload->path);
        if ($page === null) {
            return self::UNKNOWN_PAGE;
        }

        $refusal = $this->refusePage($page, $viewer, $impersonating);
        if ($refusal !== null) {
            return $refusal;
        }

        $referrer = $this->referrers->classify($payload->referrer, $this->ownHost($request), $payload->utmMedium);
        if ($referrer === null) {
            return self::SPAM;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        [$visitorHash, $visitorKind] = $this->visitor($request, $viewer, $cookieId, $now);
        $pathHash = substr(hash('sha256', $page->path, true), 0, 8);

        if ($this->hits->seenRecently($visitorHash, $page->blogId, $pathHash, (int) $this->config['dedupe_minutes'])) {
            return self::DUPLICATE;
        }

        return $this->store($request, $payload, $page, $referrer, $pathHash, $visitorHash, $visitorKind, $now);
    }

    /**
     * Checks that need nothing but the request itself, cheapest first.
     */
    private function refuseRequest(Request $request): ?string
    {
        if (!$this->settings->enabled()) {
            return self::DISABLED;
        }

        if ($this->isPrefetch($request)) {
            return self::PREFETCH;
        }

        if (self::optedOut($request)) {
            return self::OPTED_OUT;
        }

        if ($this->agents->isBot((string) $request->header('User-Agent', ''), $this->settings->extraBotPatterns())) {
            return self::BOT;
        }

        return null;
    }

    /**
     * What the blog owner chose not to count.
     *
     * @param  array<string, mixed>|null  $viewer
     */
    private function refusePage(TrafficPage $page, ?array $viewer, bool $impersonating): ?string
    {
        // A page cached in the browser before counting was switched off still sends the beacon.
        if (!$page->countingEnabled()) {
            return self::BLOG_OFF;
        }

        if ($page->isExcluded()) {
            return self::EXCLUDED_PATH;
        }

        if ($impersonating || $this->isTeamOrStaff($viewer, $page)) {
            return self::MEMBER;
        }

        return null;
    }

    /**
     * @param  array{channel: string, host: ?string, source: ?string}  $referrer
     */
    private function store(
        Request $request,
        BeaconPayload $payload,
        TrafficPage $page,
        array $referrer,
        string $pathHash,
        string $visitorHash,
        string $visitorKind,
        \DateTimeImmutable $now
    ): string {
        $family = $this->agents->classify((string) $request->header('User-Agent', ''));

        $stored = $this->hits->record([
            'view_id' => $payload->viewIdBytes(),
            'blog_id' => $page->blogId,
            'post_id' => $page->postId,
            'page_type' => $page->pageType,
            'path' => $page->path,
            'path_hash' => $pathHash,
            'visitor_hash' => $visitorHash,
            'visitor_kind' => $visitorKind,
            'channel' => $referrer['channel'],
            'referrer_host' => $referrer['host'],
            'referrer_source' => $referrer['source'],
            'utm_source' => $payload->utmSource,
            'utm_medium' => $payload->utmMedium,
            'utm_campaign' => $payload->utmCampaign,
            'device' => $family['device'],
            'browser' => $family['browser'],
            'os' => $family['os'],
            'country' => $this->countries->country((string) $request->ip()),
            'locale' => substr($page->locale, 0, 5),
            'local_date' => $now->setTimezone(new \DateTimeZone($page->timezone()))->format('Y-m-d'),
        ]);

        return $stored ? self::RECORDED : self::DUPLICATE;
    }

    /**
     * Attach reading time and scroll depth to a view recorded earlier.
     */
    public function recordEngagement(BeaconPayload $payload): bool
    {
        if (!$this->settings->enabled()) {
            return false;
        }

        return $this->hits->recordEngagement(
            $payload->viewIdBytes(),
            $payload->seconds,
            $payload->scrollDepth,
            (int) $this->config['engagement_window_minutes']
        );
    }

    public function maxBodyBytes(): int
    {
        return (int) $this->config['max_body_bytes'];
    }

    public function maxEngagedSeconds(): int
    {
        return (int) $this->config['max_engaged_seconds'];
    }

    /**
     * Global Privacy Control and Do Not Track: the reader asked not to be counted at all.
     */
    public static function optedOut(Request $request): bool
    {
        return trim((string) $request->header('Sec-GPC')) === '1' || trim((string) $request->header('DNT')) === '1';
    }

    /**
     * Link prefetch and prerender announce themselves in these headers.
     */
    private function isPrefetch(Request $request): bool
    {
        $purpose = strtolower(implode(' ', array_filter([
            $request->header('Sec-Purpose'),
            $request->header('Purpose'),
            $request->header('X-Moz'),
        ])));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'preview');
    }

    /**
     * Administrators always, and the blog's own team unless the owner chose to count them.
     *
     * @param  array<string, mixed>|null  $viewer
     */
    private function isTeamOrStaff(?array $viewer, TrafficPage $page): bool
    {
        if ($viewer === null) {
            return false;
        }

        if (in_array('administrator', $viewer['roles'] ?? [], true)) {
            return true;
        }

        return $page->excludesMembers() && $this->blogs->userCanAccessBlog((int) $viewer['id'], $page->blogId);
    }

    /**
     * @param  array<string, mixed>|null  $viewer
     * @return array{0: string, 1: string} Hash and kind
     */
    private function visitor(
        Request $request,
        ?array $viewer,
        ?string $cookieId,
        \DateTimeImmutable $now
    ): array {
        if ($viewer !== null) {
            return [$this->identity->forAccount((int) $viewer['id']), 'account'];
        }

        if ($cookieId !== null) {
            return [$this->identity->forCookie($cookieId), 'cookie'];
        }

        return [$this->identity->dailyFor($request, $now), 'daily'];
    }

    private function ownHost(Request $request): string
    {
        return (string) (parse_url((string) base_url(), PHP_URL_HOST) ?: $request->header('Host', ''));
    }
}
