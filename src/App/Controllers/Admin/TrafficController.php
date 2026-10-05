<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\TrafficHitModel;
use App\Models\TrafficOutcomeModel;
use App\Models\TrafficStatsModel;
use App\Services\Traffic\CountryLookup;
use App\Services\Traffic\TrafficReportService;
use App\Services\Traffic\TrafficSettings;
use App\ValueObjects\TrafficRange;
use App\ValueObjects\TrafficScope;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Insights > Traffic in the control panel: the whole site at a glance, the
 * busiest blogs and posts, where readers come from, and the platform's own pages.
 *
 * Days are UTC here. Each blog's own row uses the blog's timezone.
 */
class TrafficController extends AppController
{
    protected ?string $areaAbility = 'viewPlatformTraffic';

    private const RIGHT_NOW_MINUTES = 30;

    private const RECENT_LIMIT = 5;

    /** A Discover search shows once this many different visitors made it. */
    private const SEARCH_MIN_VISITORS = 3;

    private const BASE_PATH = '/admin/traffic';

    private const BLOG_COLUMNS = ['blog', 'url', 'status', 'views', 'visitors', 'read_ratio_percent', 'avg_read_seconds', 'bounce_rate_percent'];

    private const POST_COLUMNS = ['post', 'blog', 'url', 'views', 'visitors', 'read_ratio_percent', 'avg_read_seconds'];

    public function __construct(
        private TrafficReportService $reports,
        private TrafficStatsModel $stats,
        private TrafficHitModel $hits,
        private TrafficSettings $settings,
        private CountryLookup $countries,
        private TrafficOutcomeModel $outcomes,
    ) {}

    public function index(): Response
    {
        return $this->page(TrafficScope::site(), self::BASE_PATH);
    }

    /**
     * The platform's own pages: home, Discover, the guides, profiles, sign-in and sign-up.
     */
    public function platform(): Response
    {
        return $this->page(TrafficScope::platform(), self::BASE_PATH.'/platform');
    }

    /**
     * Every blog, every post, or one breakdown for the chosen range as CSV.
     */
    public function export(): Response
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');
        $range = TrafficRange::fromQuery($this->request->get, 'UTC');
        $filename = "site-traffic-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv";

        if ($dimension === 'blogs') {
            return $this->csv($filename, self::BLOG_COLUMNS, $this->blogRows($range));
        }

        if ($dimension === 'posts') {
            return $this->csv($filename, self::POST_COLUMNS, $this->postRows($range));
        }

        return $this->breakdownCsv(TrafficScope::site(), $dimension, $range, $filename);
    }

    /**
     * One breakdown of the platform's own pages as CSV.
     */
    public function exportPlatform(): Response
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');
        $range = TrafficRange::fromQuery($this->request->get, 'UTC');
        $filename = "platform-pages-traffic-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv";

        return $this->breakdownCsv(TrafficScope::platform(), $dimension, $range, $filename);
    }

    private function page(TrafficScope $scope, string $basePath): Response
    {
        $range = TrafficRange::fromQuery($this->request->get, 'UTC');
        $isSite = $scope->type === TrafficScope::SITE;
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $withinRaw = $range->withinRaw($this->settings->rawRetentionDays(), $today);
        $filters = TrafficReportService::filtersFrom($this->request->get['f'] ?? null, $scope);
        $filtersDropped = $filters !== [] && !$withinRaw;

        return $this->view('traffic.index', [
            'range' => $range,
            'report' => $this->reports->report($scope, $range, $filtersDropped ? [] : $filters),
            'filters' => $filtersDropped ? [] : $filters,
            'filtersDropped' => $filtersDropped,
            'rawRetentionDays' => $this->settings->rawRetentionDays(),
            'rightNow' => $isSite ? $this->hits->countRecentEverywhere(self::RIGHT_NOW_MINUTES) : null,
            'recent' => $isSite ? $this->hits->recentBreakdown(null, self::RIGHT_NOW_MINUTES, self::RECENT_LIMIT) : null,
            'outcomes' => $isSite ? $this->outcomes->totals($range->fromDate(), $range->toDate()) : [],
            'scriptViews' => $isSite ? $this->hits->countSuspect($range->fromDate(), $range->toDate()) : 0,
            'scriptMinViews' => (int) (require ROOT_PATH.'/config/traffic.php')['script_min_views'],
            'searchTerms' => !$isSite && $withinRaw
                ? $this->hits->searchTerms($range->fromDate(), $range->toDate(), self::SEARCH_MIN_VISITORS, 10)
                : null,
            'today' => $today->format('Y-m-d'),
            'collectingSince' => $this->stats->firstDay($scope),
            'aggregatedAt' => $this->settings->aggregatedAt(),
            'delayed' => $this->settings->aggregationDelayed(),
            'trackingEnabled' => $this->settings->enabled(),
            'aggregationEnabled' => $this->settings->aggregationEnabled(),
            'countriesAvailable' => $this->countries->available(),
            'basePath' => $basePath,
            'scope' => $scope->type,
            'post' => null,
        ]);
    }

    private function breakdownCsv(TrafficScope $scope, string $dimension, TrafficRange $range, string $filename): Response
    {
        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new PageNotFoundException('Unknown traffic breakdown.');
        }

        $rows = array_map(
            static fn (array $row): array => [$row['name'] ?? $row['value'], $row['views'], $row['visitors']],
            $this->reports->fullBreakdown($scope, $dimension, $range)
        );

        return $this->csv($filename, [$dimension, 'views', 'visitors'], $rows);
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function blogRows(TrafficRange $range): array
    {
        $rows = [];

        foreach ($this->reports->allBlogs($range) as $row) {
            $views = (int) $row['views'];
            $visitors = (int) $row['visitors'];
            $slug = $row['blog_slug'] === null ? null : (string) $row['blog_slug'];

            $rows[] = [
                (string) ($row['blog_name'] ?? '#'.(int) $row['blog_id']),
                $slug !== null ? $this->siteUrl('/blog/'.rawurlencode($slug)) : '',
                (string) ($row['status'] ?? ''),
                $views,
                $visitors,
                self::percentOf((int) $row['read_views'], $views),
                self::averageOf((int) $row['engaged_seconds'], (int) $row['engaged_views']),
                self::percentOf((int) $row['bounces'], $visitors),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function postRows(TrafficRange $range): array
    {
        $rows = [];

        foreach ($this->reports->allPosts(TrafficScope::site(), $range) as $row) {
            $views = (int) $row['views'];
            $url = '';
            if ($row['title'] !== null && $row['status'] === 'published' && $row['blog_slug'] !== null) {
                $url = $this->siteUrl('/blog/'.rawurlencode((string) $row['blog_slug']).'/'.rawurlencode((string) $row['slug']));
            }

            $rows[] = [
                (string) ($row['title'] ?? chrome_translate('traffic.topPosts.deleted')),
                (string) ($row['blog_name'] ?? ''),
                $url,
                $views,
                (int) $row['visitors'],
                self::percentOf((int) $row['read_views'], $views),
                self::averageOf((int) $row['engaged_seconds'], (int) $row['engaged_views']),
            ];
        }

        return $rows;
    }

    private function siteUrl(string $path): string
    {
        return rtrim(base_url(), '/').lurl($path);
    }

    private static function percentOf(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    private static function averageOf(int $total, int $count): int
    {
        return $count > 0 ? (int) round($total / $count) : 0;
    }
}
