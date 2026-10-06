<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Gate;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsOutcomeModel;
use App\Models\AnalyticsStatsModel;
use App\Resources\SystemResource;
use App\Services\Analytics\AnalyticsReportService;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\Analytics\CountryLookup;
use App\Services\Analytics\InsightsPageReports;
use App\Services\Analytics\InsightsPages;
use App\Services\Analytics\InsightsSavedView;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * Insights in the control panel, one page per entry of InsightsPages::ADMIN
 * except Sign-ups, which has its own controller. Most pages switch between the
 * whole site and the platform's own pages with ?scope=platform.
 *
 * Days are UTC here. Each blog's own row uses the blog's timezone.
 */
class InsightsController extends AppController
{
    protected ?string $areaAbility = 'viewPlatformAnalytics';

    /** Pages that can show the platform's own pages instead of the whole site. */
    private const SCOPED_PAGES = ['overview', 'content', 'audience', 'acquisition', 'engagement', 'seo', 'technical'];

    private const RIGHT_NOW_MINUTES = 30;

    private const RECENT_LIMIT = 5;

    /** A Discover search shows once this many different visitors made it. */
    private const SEARCH_MIN_VISITORS = 3;

    private const BASE_PATH = '/admin/insights';

    private const BLOG_COLUMNS = ['blog', 'url', 'status', 'views', 'visitors', 'read_rate_percent', 'avg_read_seconds', 'engaged_visits_percent'];

    private const POST_COLUMNS = ['post', 'blog', 'url', 'views', 'visitors', 'read_rate_percent', 'avg_read_seconds'];

    public function __construct(
        private AnalyticsReportService $reports,
        private InsightsPageReports $pageReports,
        private AnalyticsStatsModel $stats,
        private AnalyticsEventModel $events,
        private AnalyticsSettings $settings,
        private CountryLookup $countries,
        private AnalyticsOutcomeModel $outcomes,
        private InsightsSavedView $savedView,
    ) {}

    /**
     * The Insights link: opens the page looked at last. A link that carries its
     * own range, scope or filters means that view.
     */
    public function index(): Response
    {
        $last = $this->savedView->lastPage((int) auth()->user()['id']);

        if ($this->request->get === [] && $last !== null && $last !== 'overview' && InsightsPages::isAdminPage($last)) {
            return $this->redirect(lurl(InsightsPages::path(self::BASE_PATH, $last)));
        }

        return $this->show('overview');
    }

    public function page(string $page): Response
    {
        if (!InsightsPages::isAdminPage($page)) {
            throw new PageNotFoundException("There is no Insights page called '{$page}'.");
        }

        return $this->show($page);
    }

    /**
     * Every blog, every post, or one breakdown for the chosen range as CSV.
     */
    public function export(): Response
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');
        $scope = $this->scope('overview');
        $range = AnalyticsRange::fromQuery($this->request->get, 'UTC');
        $prefix = $scope->type === AnalyticsScope::PLATFORM ? 'platform-pages' : 'site';
        $filename = "{$prefix}-insights-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv";

        if ($scope->type === AnalyticsScope::SITE && $dimension === 'blogs') {
            return $this->csv($filename, self::BLOG_COLUMNS, $this->blogRows($range));
        }

        if ($scope->type === AnalyticsScope::SITE && $dimension === 'posts') {
            return $this->csv($filename, self::POST_COLUMNS, $this->postRows($range));
        }

        return $this->breakdownCsv($scope, $dimension, $range, $filename);
    }

    private function show(string $page): Response
    {
        $scope = $this->scope($page);
        $isSite = $scope->type === AnalyticsScope::SITE;
        $range = $this->savedView->range((int) auth()->user()['id'], $page, $this->request->get, 'UTC');
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $withinRaw = $range->withinRaw($this->settings->rawRetentionDays(), $today);
        $filters = AnalyticsReportService::filtersFrom($this->request->get['f'] ?? null, $scope);
        $filtersDropped = $filters !== [] && !$withinRaw;
        if ($filtersDropped) {
            $filters = [];
        }

        $showsRightNow = $isSite && $page === 'overview';
        $showsOutcomes = $isSite && $page === 'technical';

        return $this->view('insights.'.$page, [
            'page' => $page,
            'range' => $range,
            'report' => $this->pageReports->page($page, $scope, $range, $filters),
            'filters' => $filters,
            'filtersDropped' => $filtersDropped,
            'rawRetentionDays' => $this->settings->rawRetentionDays(),
            'rightNow' => $showsRightNow ? $this->events->countRecentEverywhere(self::RIGHT_NOW_MINUTES) : null,
            'recent' => $showsRightNow ? $this->events->recentBreakdown(null, self::RIGHT_NOW_MINUTES, self::RECENT_LIMIT) : null,
            'outcomes' => $showsOutcomes ? $this->outcomes->totals($range->fromDate(), $range->toDate()) : [],
            'scriptViews' => $showsOutcomes ? $this->events->countSuspect($range->fromDate(), $range->toDate()) : 0,
            'scriptMinViews' => (int) (require ROOT_PATH.'/config/analytics.php')['script_min_views'],
            'searchTerms' => $page === 'content' && $withinRaw
                ? $this->events->searchTerms($range->fromDate(), $range->toDate(), self::SEARCH_MIN_VISITORS, 10)
                : null,
            'emptySearches' => $page === 'content' && $withinRaw
                ? $this->events->searchTerms($range->fromDate(), $range->toDate(), self::SEARCH_MIN_VISITORS, 10, true)
                : null,
            'today' => $today->format('Y-m-d'),
            'collectingSince' => $this->stats->firstDay($scope),
            'aggregatedAt' => $this->settings->aggregatedAt(),
            'delayed' => $this->settings->aggregationDelayed(),
            'trackingEnabled' => $this->settings->enabled(),
            'aggregationEnabled' => $this->settings->aggregationEnabled(),
            'countriesAvailable' => $this->countries->available(),
            'canConfigure' => Gate::allows('manageSettings', SystemResource::class, auth()->user() ?? []),
            'basePath' => self::BASE_PATH,
            'pagePath' => InsightsPages::path(self::BASE_PATH, $page),
            'scopeSwitch' => in_array($page, self::SCOPED_PAGES, true),
            'extraQuery' => $isSite ? [] : ['scope' => 'platform'],
            'scope' => $scope->type,
            'post' => null,
        ]);
    }

    /**
     * The platform's own pages when asked for on a page that has them, otherwise the whole site.
     */
    private function scope(string $page): AnalyticsScope
    {
        $wantsPlatform = ($this->request->get['scope'] ?? null) === 'platform';

        return $wantsPlatform && in_array($page, self::SCOPED_PAGES, true)
            ? AnalyticsScope::platform()
            : AnalyticsScope::site();
    }

    private function breakdownCsv(AnalyticsScope $scope, string $dimension, AnalyticsRange $range, string $filename): Response
    {
        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new PageNotFoundException('Unknown analytics breakdown.');
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
    private function blogRows(AnalyticsRange $range): array
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
                self::percentOf((int) $row['engaged_visits'], (int) $row['visits']),
            ];
        }

        return $rows;
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function postRows(AnalyticsRange $range): array
    {
        $rows = [];

        foreach ($this->reports->allPosts(AnalyticsScope::site(), $range) as $row) {
            $views = (int) $row['views'];
            $url = '';
            if ($row['title'] !== null && $row['status'] === 'published' && $row['blog_slug'] !== null) {
                $url = $this->siteUrl('/blog/'.rawurlencode((string) $row['blog_slug']).'/'.rawurlencode((string) $row['slug']));
            }

            $rows[] = [
                (string) ($row['title'] ?? chrome_translate('analytics.topPosts.deleted')),
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
