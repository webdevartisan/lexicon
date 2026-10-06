<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsVisitModel;
use App\Models\BlogModel;
use Framework\Core\Request;

/**
 * Decides whether a beacon is a real reader's page view and stores it if so,
 * in the visit it belongs to. Also stores the page's other events: missing
 * pages and link clicks.
 *
 * Refusals come back as one of the outcome constants. Database errors propagate.
 */
class AnalyticsRecorder
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
     * @param  array<string, mixed>  $config  config/analytics.php
     */
    public function __construct(
        private AnalyticsSettings $settings,
        private PagePathResolver $pages,
        private UserAgentClassifier $agents,
        private ReferrerClassifier $referrers,
        private VisitFinder $finder,
        private CountryLookup $countries,
        private NetworkLookup $networks,
        private AnalyticsEventModel $events,
        private AnalyticsVisitModel $visits,
        private EventRegistry $registry,
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
        [$visitorHash, $visitorKind] = $this->finder->visitor($request, $viewer, $cookieId, $now);
        $pathHash = substr(hash('sha256', $page->path, true), 0, 8);

        if ($this->events->seenRecently($visitorHash, $pathHash, (int) $this->config['dedupe_minutes'])) {
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
    private function refusePage(AnalyticsPage $page, ?array $viewer, bool $impersonating): ?string
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
        AnalyticsPage $page,
        array $referrer,
        string $pathHash,
        string $visitorHash,
        string $visitorKind,
        \DateTimeImmutable $now
    ): string {
        $family = $this->agents->classify((string) $request->header('User-Agent', ''));
        $local = $now->setTimezone(new \DateTimeZone($page->timezone()));
        $arrival = [
            'channel' => $referrer['channel'],
            'referrer_host' => $referrer['host'],
            'referrer_source' => $referrer['source'],
            'utm_source' => $payload->utmSource,
            'utm_medium' => $payload->utmMedium,
            'utm_campaign' => $payload->utmCampaign,
        ];

        $visitId = $this->visits->track(
            $visitorHash,
            $visitorKind,
            [
                'device' => $family['device'],
                'browser' => $family['browser'],
                'os' => $family['os'],
                'country' => $this->countries->country((string) $request->ip()),
            ],
            [
                'entry_path' => $page->path,
                'entry_page_type' => $page->pageType,
                'entry_blog_id' => $page->blogId,
                'entry_post_id' => $page->postId,
            ] + $arrival,
            (int) $this->config['visit_minutes']
        );

        $stored = $this->events->record([
            'event_key' => $payload->viewIdBytes(),
            'name' => 'page_view',
            'view_id' => $payload->viewIdBytes(),
            'visit_id' => $visitId,
            'visitor_hash' => $visitorHash,
            'visitor_kind' => $visitorKind,
            'blog_id' => $page->blogId,
            'post_id' => $page->postId,
            'from_post_id' => $referrer['fromPostId'],
            'page_type' => $page->pageType,
            'path' => $page->path,
            'path_hash' => $pathHash,
            'locale' => substr($page->locale, 0, 5),
            'props' => $this->viewProps($page, $payload, $referrer['fromPostId'] !== null),
            'local_date' => $local->format('Y-m-d'),
            'local_hour' => (int) $local->format('G'),
        ] + $arrival);

        return $stored ? self::RECORDED : self::DUPLICATE;
    }

    /**
     * Moving around one blog, or between the platform's pages, is internal. Coming
     * from another part of Lexicon keeps the blog or the kind of page, never its path.
     *
     * @return array{channel: string, host: ?string, source: ?string}
     */
    private function withinLexicon(AnalyticsPage $page, string $referrer): array
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
    private function fromPost(AnalyticsPage $page, string $referrer): ?int
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

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->events->record([
            'event_key' => random_bytes(16),
            'name' => 'not_found',
            'blog_id' => $this->pages->section($payload->path)['blogId'],
            'path' => $path,
            'path_hash' => substr(hash('sha256', $path, true), 0, 8),
            'props' => $host !== '' ? ['referrer_host' => mb_substr($host, 0, 100)] : [],
            'local_date' => $now->format('Y-m-d'),
            'local_hour' => (int) $now->format('G'),
        ]);

        return self::NOT_FOUND;
    }

    /**
     * Something the reader did on a view counted in the last few minutes: a share,
     * or a click on a link to another site or on a file. Only events the page script
     * may send get through, with their props checked by the registry.
     */
    public function recordEvent(Request $request, BeaconPayload $payload): bool
    {
        $name = (string) $payload->eventName;
        if (!$this->settings->enabled() || self::optedOut($request) || !$this->registry->fromBeacon($name) || !$this->registry->needsView($name)) {
            return false;
        }

        $props = $this->registry->clean($name, $this->normalised($name, $payload->eventProps));
        $view = $props === null ? null : $this->events->findRecentView($payload->viewIdBytes(), (int) $this->config['engagement_window_minutes']);
        if ($view === null) {
            return false;
        }

        return $this->events->record(['event_key' => random_bytes(16), 'name' => $name, 'props' => $props] + $view);
    }

    /**
     * A link's host and a file's name are worked out here rather than trusted, so a
     * full address with its query string is never kept.
     *
     * @param  array<mixed>  $props
     * @return array<mixed>
     */
    private function normalised(string $name, array $props): array
    {
        return match ($name) {
            'outbound' => ['host' => is_string($props['host'] ?? null) ? self::outboundHost($props['host']) : null],
            'download' => ['file' => is_string($props['file'] ?? null) ? $this->downloadName($props['file']) : null],
            default => $props,
        };
    }

    /**
     * What a page view keeps besides its columns: a Discover search and how many
     * results it found, and whether a related post link brought the reader here.
     *
     * @return array<string, string|int>
     */
    private function viewProps(AnalyticsPage $page, BeaconPayload $payload, bool $fromPost): array
    {
        $props = [];
        $term = $page->pageType === 'discover' ? self::searchTerm($payload->searchTerm) : null;
        if ($term !== null) {
            $props['q'] = $term;
            if ($payload->searchResults !== null) {
                $props['search_results'] = $payload->searchResults;
            }
        }

        // A related link only means something between two posts of the same blog.
        if ($payload->via === 'related' && $fromPost && $page->postId !== null) {
            $props['via'] = 'related';
        }

        return $this->registry->clean('page_view', $props) ?? [];
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

        return $this->events->recordEngagement(
            $payload->viewIdBytes(),
            $payload->seconds,
            $payload->scrollDepth,
            (int) $this->config['engagement_window_minutes'],
            $payload->vitals
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
    private function isTeamOrStaff(?array $viewer, AnalyticsPage $page): bool
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

    private function ownHost(Request $request): string
    {
        return (string) (parse_url((string) base_url(), PHP_URL_HOST) ?: $request->header('Host', ''));
    }
}
