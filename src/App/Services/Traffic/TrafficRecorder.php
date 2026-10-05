<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\BlogModel;
use App\Models\TrafficEventModel;
use App\Models\TrafficHitModel;
use App\Models\TrafficNotFoundModel;
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

    public const PREFETCH = 'prefetch';

    public const OPTED_OUT = 'opted_out';

    public const BOT = 'bot';

    public const HOSTING = 'hosting';

    public const NOT_FOUND = 'not_found';

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
        private NetworkLookup $networks,
        private TrafficHitModel $hits,
        private TrafficEventModel $events,
        private TrafficNotFoundModel $notFound,
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
            return $payload->notFound ? $this->recordNotFound($request, $payload, $viewer, $impersonating) : self::UNKNOWN_PAGE;
        }

        $refusal = $this->refusePage($page, $viewer, $impersonating);
        if ($refusal !== null) {
            return $refusal;
        }

        $referrer = $this->referrers->classify($payload->referrer, $this->ownHost($request), $payload->utmMedium);
        if ($referrer === null) {
            return self::SPAM;
        }

        $fromPostId = null;
        if ($referrer['channel'] === 'internal') {
            $referrer = $this->withinLexicon($page, $payload->referrer);
            $fromPostId = $referrer['channel'] === 'internal' ? $this->fromPost($page, $payload->referrer) : null;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        [$visitorHash, $visitorKind] = $this->visitor($request, $viewer, $cookieId, $now);
        $pathHash = substr(hash('sha256', $page->path, true), 0, 8);

        if ($this->hits->seenRecently($visitorHash, $pathHash, (int) $this->config['dedupe_minutes'])) {
            return self::DUPLICATE;
        }

        return $this->store(
            $request,
            $payload,
            $page,
            $referrer + ['fromPostId' => $fromPostId],
            $pathHash,
            $visitorHash,
            $visitorKind,
            $now
        );
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

        if ($this->networks->isHosting((string) $request->ip())) {
            return self::HOSTING;
        }

        return null;
    }

    /**
     * What the blog owner chose not to count, and the people who never count.
     *
     * @param  array<string, mixed>|null  $viewer
     */
    private function refusePage(TrafficPage $page, ?array $viewer, bool $impersonating): ?string
    {
        if ($page->isExcluded()) {
            return self::EXCLUDED_PATH;
        }

        if ($impersonating || $this->isTeamOrStaff($viewer, $page)) {
            return self::MEMBER;
        }

        return null;
    }

    /**
     * @param  array{channel: string, host: ?string, source: ?string, fromPostId: ?int}  $referrer
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
        $local = $now->setTimezone(new \DateTimeZone($page->timezone()));

        $stored = $this->hits->record([
            'view_id' => $payload->viewIdBytes(),
            'blog_id' => $page->blogId,
            'post_id' => $page->postId,
            'from_post_id' => $referrer['fromPostId'],
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
            'search_term' => $page->pageType === 'discover' ? self::searchTerm($payload->searchTerm) : null,
            'device' => $family['device'],
            'browser' => $family['browser'],
            'os' => $family['os'],
            'country' => $this->countries->country((string) $request->ip()),
            'locale' => substr($page->locale, 0, 5),
            'local_date' => $local->format('Y-m-d'),
            'local_hour' => (int) $local->format('G'),
        ]);

        return $stored ? self::RECORDED : self::DUPLICATE;
    }

    /**
     * Moving around one blog, or between the platform's pages, is internal. Coming
     * from another part of Lexicon keeps the blog or the kind of page, never its path.
     *
     * @return array{channel: string, host: ?string, source: ?string}
     */
    private function withinLexicon(TrafficPage $page, string $referrer): array
    {
        $from = $this->pages->section($referrer);

        if ($from['blogId'] === $page->blogId) {
            return ['channel' => 'internal', 'host' => null, 'source' => null];
        }

        $source = $from['blogId'] !== null ? LexiconSource::blog($from['blogId']) : $from['pageType'];

        return ['channel' => 'lexicon', 'host' => null, 'source' => $source];
    }

    /**
     * The post the reader was on just before, when they moved here inside the same blog.
     */
    private function fromPost(TrafficPage $page, string $referrer): ?int
    {
        if ($page->blogId === null) {
            return null;
        }

        $from = $this->pages->resolve($referrer);

        return $from !== null && $from->blogId === $page->blogId && $from->postId !== $page->postId ? $from->postId : null;
    }

    /**
     * A reader reached a page that doesn't exist. Kept per path and referring site,
     * so the blog owner can see broken links pointing at the blog.
     *
     * @param  array<string, mixed>|null  $viewer
     */
    private function recordNotFound(Request $request, BeaconPayload $payload, ?array $viewer, bool $impersonating): string
    {
        $path = $this->pages->normalise($payload->path);

        if ($path === null || $impersonating || in_array('administrator', $viewer['roles'] ?? [], true)) {
            return self::UNKNOWN_PAGE;
        }

        if ($this->referrers->classify($payload->referrer, $this->ownHost($request), $payload->utmMedium) === null) {
            return self::SPAM;
        }

        $host = strtolower((string) parse_url($payload->referrer, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';

        $this->notFound->record($path, mb_substr($host, 0, 100), $this->pages->section($payload->path)['blogId']);

        return self::NOT_FOUND;
    }

    /**
     * A click on an outbound link or a download, on a view counted in the last few hours.
     */
    public function recordClick(Request $request, BeaconPayload $payload): bool
    {
        if (!$this->settings->enabled() || self::optedOut($request) || $payload->clickKind === null) {
            return false;
        }

        $target = $payload->clickKind === 'outbound'
            ? self::outboundHost((string) $payload->clickTarget)
            : $this->downloadName((string) $payload->clickTarget);

        if ($target === null) {
            return false;
        }

        $view = $this->hits->findRecentView($payload->viewIdBytes(), (int) $this->config['engagement_window_minutes']);
        if ($view === null) {
            return false;
        }

        $this->events->recordClick($payload->clickKind, $view['blog_id'], $view['post_id'], $target, $view['local_date']);

        return true;
    }

    private static function outboundHost(string $target): ?string
    {
        $host = strtolower(str_contains($target, '/') ? (string) parse_url($target, PHP_URL_HOST) : $target);
        $host = preg_replace('/^www\./', '', trim($host)) ?? '';

        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host) && strlen($host) <= 191 ? $host : null;
    }

    private function downloadName(string $target): ?string
    {
        $name = rawurldecode(basename((string) parse_url($target, PHP_URL_PATH)));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($name === '' || !in_array($extension, $this->config['download_extensions'], true)) {
            return null;
        }

        return mb_substr($name, 0, 191);
    }

    /**
     * What was searched, folded so the same search typed differently counts once.
     */
    private static function searchTerm(?string $term): ?string
    {
        if ($term === null) {
            return null;
        }

        $term = trim(preg_replace('/\s+/u', ' ', mb_strtolower($term)) ?? '');

        return $term === '' ? null : mb_substr($term, 0, 100);
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

        return $page->blogId !== null
            && $page->excludesMembers()
            && $this->blogs->userCanAccessBlog((int) $viewer['id'], $page->blogId);
    }

    /**
     * A signed-in reader is recognised by their account only if they allowed
     * analytics, which is also the only time there is a cookie id.
     *
     * @param  array<string, mixed>|null  $viewer
     * @return array{0: string, 1: string} Hash and kind
     */
    private function visitor(
        Request $request,
        ?array $viewer,
        ?string $cookieId,
        \DateTimeImmutable $now
    ): array {
        if ($viewer !== null && $cookieId !== null) {
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
