<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogSettingsModel;
use App\Resources\BlogResource;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;
use Framework\Exceptions\PageNotFoundException;

/**
 * What one blog's Insights pages show, whoever is looking: the blog's own team
 * in the dashboard, or platform staff in the control panel. Callers decide who
 * may see what and pass the scope; this builds the page from it.
 */
final class BlogInsightsPage
{
    private const RIGHT_NOW_MINUTES = 30;

    private const RECENT_LIMIT = 5;

    /** The Top posts export. Not a breakdown dimension. */
    private const POSTS_EXPORT = 'posts';

    private const POSTS_COLUMNS = ['post', 'url', 'views', 'visitors', 'read_rate_percent', 'avg_read_seconds'];

    public function __construct(
        private AnalyticsStatsModel $stats,
        private AnalyticsEventModel $events,
        private AnalyticsReportService $reports,
        private InsightsPageReports $pageReports,
        private AnalyticsSettings $settings,
        private CountryLookup $countries,
        private BlogSettingsModel $blogSettings,
    ) {}

    /**
     * What every Insights page shows around the numbers themselves.
     *
     * @param  string  $basePath  Where this blog's Insights pages live for this viewer
     * @param  bool  $canConfigure  Whether the counting settings can be changed from here
     * @return array<string, mixed>
     */
    public function frame(BlogResource $blog, AnalyticsRange $range, bool $canAll, string $basePath, bool $canConfigure): array
    {
        $zone = new \DateTimeZone(blog_timezone((int) $blog->id()));

        return [
            'blog' => $blog->toArray(),
            'canAll' => $canAll,
            'range' => $range,
            'today' => (new \DateTimeImmutable('today', $zone))->format('Y-m-d'),
            'collectingSince' => $this->stats->firstDay(AnalyticsScope::blog((int) $blog->id())),
            'aggregatedAt' => $this->settings->aggregatedAt(),
            'delayed' => $this->settings->aggregationDelayed(),
            'trackingEnabled' => $this->settings->enabled(),
            'aggregationEnabled' => $this->settings->aggregationEnabled(),
            'countriesAvailable' => $this->countries->available(),
            'basePath' => $basePath,
            'canConfigure' => $canConfigure,
            'blogSettings' => $this->blogSettings->findByBlogId((int) $blog->id()) ?? [],
            'blogUrl' => rtrim(base_url(), '/').lurl('/blog/'.rawurlencode($blog->slug())),
            'rawRetentionDays' => $this->settings->rawRetentionDays(),
        ];
    }

    /**
     * One of the pages in InsightsPages::BLOG.
     *
     * @param  mixed  $rawFilters  The request's `f` query value
     * @param  array<string, mixed>  $frame  From frame()
     * @return array<string, mixed> View data
     */
    public function page(string $page, AnalyticsScope $scope, mixed $rawFilters, array $frame): array
    {
        $blogId = (int) $scope->blogId;
        [$filters, $filtersDropped] = $this->filters($scope, $rawFilters, $frame['range'], $frame['today']);
        $report = $this->pageReports->page($page, $scope, $frame['range'], $filters);
        $showsRightNow = $frame['canAll'] && $page === 'overview';

        return $frame + [
            'page' => $page,
            'pagePath' => InsightsPages::path($frame['basePath'], $page),
            'scope' => $frame['canAll'] ? 'blog' : 'author',
            'post' => null,
            'report' => $report,
            'filters' => $filters,
            'filtersDropped' => $filtersDropped,
            'performance' => $page === 'content' ? $this->performance($blogId, $report['topPosts'], $frame) : null,
            'rightNow' => $showsRightNow ? $this->events->countRecent($blogId, self::RIGHT_NOW_MINUTES) : null,
            'recent' => $showsRightNow ? $this->events->recentBreakdown($blogId, self::RIGHT_NOW_MINUTES, self::RECENT_LIMIT) : null,
        ];
    }

    /**
     * The page of one post.
     *
     * @param  array<string, mixed>  $post  The post row, already checked to be in this blog
     * @param  mixed  $rawFilters  The request's `f` query value
     * @param  array<string, mixed>  $frame  From frame()
     * @return array<string, mixed> View data
     */
    public function post(array $post, mixed $rawFilters, array $frame): array
    {
        $blogId = (int) $post['blog_id'];
        $scope = AnalyticsScope::post($blogId, (int) $post['id']);
        [$filters, $filtersDropped] = $this->filters($scope, $rawFilters, $frame['range'], $frame['today']);

        return $frame + [
            'page' => 'post',
            'pagePath' => $frame['basePath'].'/posts/'.(int) $post['id'],
            'scope' => 'post',
            'post' => $post,
            'report' => $this->reports->report($scope, $frame['range'], $filters),
            'filters' => $filters,
            'filtersDropped' => $filtersDropped,
            'performance' => $this->reports->performance($blogId, [(int) $post['id']], $frame['today'], $frame['collectingSince']),
            'rightNow' => null,
            'recent' => null,
        ];
    }

    /**
     * One breakdown, or every post with its numbers, as CSV columns and rows.
     *
     * @return array{0: list<string>, 1: list<list<string|int|float>>}
     */
    public function export(BlogResource $blog, AnalyticsScope $scope, string $dimension, AnalyticsRange $range): array
    {
        if ($dimension === self::POSTS_EXPORT) {
            return [self::POSTS_COLUMNS, $this->postRows($blog, $scope, $range)];
        }

        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new PageNotFoundException('Unknown analytics breakdown.');
        }

        $rows = array_map(
            static fn (array $row): array => [$row['name'] ?? $row['value'], $row['views'], $row['visitors']],
            $this->reports->fullBreakdown($scope, $dimension, $range)
        );

        return [[$dimension, 'views', 'visitors'], $rows];
    }

    /**
     * The filters asked for, or none when the range reaches past raw retention,
     * which is the only place a narrowed page can be read from.
     *
     * @return array{0: array<string, string>, 1: bool} Filters, and whether some were dropped for that reason
     */
    private function filters(AnalyticsScope $scope, mixed $rawFilters, AnalyticsRange $range, string $today): array
    {
        $filters = AnalyticsReportService::filtersFrom($rawFilters, $scope);

        if ($filters === [] || $range->withinRaw($this->settings->rawRetentionDays(), new \DateTimeImmutable($today))) {
            return [$filters, false];
        }

        return [[], true];
    }

    /**
     * @param  list<array<string, mixed>>  $topPosts
     * @param  array<string, mixed>  $frame
     * @return array{posts: array<int, array<string, mixed>>, usualFirstWeek: ?int}
     */
    private function performance(int $blogId, array $topPosts, array $frame): array
    {
        $postIds = array_map(static fn (array $row): int => (int) $row['post_id'], $topPosts);

        return $this->reports->performance($blogId, $postIds, $frame['today'], $frame['collectingSince']);
    }

    /**
     * Every post in scope that had views in the range, busiest first, as CSV rows.
     *
     * @return list<list<string|int|float>>
     */
    private function postRows(BlogResource $blog, AnalyticsScope $scope, AnalyticsRange $range): array
    {
        $rows = [];
        $siteUrl = rtrim(base_url(), '/');

        foreach ($this->reports->allPosts($scope, $range) as $row) {
            $views = (int) $row['views'];
            $engaged = (int) $row['engaged_views'];
            $url = '';
            if ($row['title'] !== null && $row['status'] === 'published') {
                $url = $siteUrl.lurl('/blog/'.rawurlencode($blog->slug()).'/'.rawurlencode((string) $row['slug']));
            }

            $rows[] = [
                $row['title'] ?? chrome_translate('analytics.topPosts.deleted'),
                $url,
                $views,
                (int) $row['visitors'],
                $views > 0 ? round((int) $row['read_views'] / $views * 100, 1) : 0,
                $engaged > 0 ? (int) round((int) $row['engaged_seconds'] / $engaged) : 0,
            ];
        }

        return $rows;
    }
}
