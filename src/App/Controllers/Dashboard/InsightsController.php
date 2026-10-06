<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
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
use Framework\Exceptions\UnauthorizedException;

/**
 * A blog's Insights pages, listed in InsightsPages, and the page of one post.
 *
 * Owners and editors see the whole blog. Authors see only their own posts,
 * on every page they can open, the post page and the CSV export.
 */
final class InsightsController extends AppController
{
    private const RIGHT_NOW_MINUTES = 30;

    /** The Top posts export. Not a breakdown dimension. */
    private const POSTS_EXPORT = 'posts';

    private const POSTS_COLUMNS = ['post', 'url', 'views', 'visitors', 'read_rate_percent', 'avg_read_seconds'];

    /** Checkbox name => blog_settings column. */
    private const SETTING_TOGGLES = [
        'exclude_members' => 'analytics_exclude_members',
        'public_notice' => 'analytics_public_notice',
        'popular_posts' => 'analytics_popular_posts',
        'public_stats' => 'analytics_public_stats',
    ];

    /** Switches whose change shows on every public page of the blog. */
    private const PUBLIC_TOGGLES = ['analytics_public_notice', 'analytics_popular_posts'];

    private const RECENT_LIMIT = 5;

    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
        private AnalyticsStatsModel $stats,
        private AnalyticsEventModel $events,
        private AnalyticsReportService $reports,
        private InsightsPageReports $pageReports,
        private AnalyticsSettings $settings,
        private CountryLookup $countries,
        private BlogSettingsModel $blogSettings,
        private InsightsSavedView $savedView,
    ) {}

    /**
     * The Insights link: opens the page the user looked at last, when they may
     * still see it here. A link that carries its own range or filters means that view.
     */
    public function index(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $last = $this->savedView->lastPage((int) auth()->user()['id']);
        $resumes = $this->request->get === [] && $last !== null && $last !== 'overview'
            && InsightsPages::isBlogPage($last) && Gate::allows(InsightsPages::BLOG[$last], $blog, auth()->user());

        if ($resumes) {
            return $this->redirect(lurl(InsightsPages::path('/dashboard/blog/'.(int) $blog->id().'/insights', $last)));
        }

        return $this->show($blogId, 'overview');
    }

    public function page(string $blogId, string $page): Response
    {
        if (!InsightsPages::isBlogPage($page)) {
            throw new PageNotFoundException("There is no Insights page called '{$page}'.");
        }

        return $this->show($blogId, $page);
    }

    private function show(string $blogId, string $page): Response
    {
        $blog = $this->authorizedBlog($blogId);
        Gate::authorize(InsightsPages::BLOG[$page], $blog, auth()->user());

        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllAnalytics', $blog, auth()->user());
        $range = $this->savedView->range((int) auth()->user()['id'], $page, $this->request->get, blog_timezone($blogIdInt));
        $scope = $this->viewerScope($blogIdInt, $canAll);
        $data = $this->pageData($blog, $range, $canAll);
        [$filters, $filtersDropped] = $this->filters($scope, $range, $data['today']);
        $report = $this->pageReports->page($page, $scope, $range, $filters);
        $showsRightNow = $canAll && $page === 'overview';

        return $this->view('insights.'.$page, $data + [
            'page' => $page,
            'pagePath' => InsightsPages::path($data['basePath'], $page),
            'scope' => $canAll ? 'blog' : 'author',
            'post' => null,
            'report' => $report,
            'filters' => $filters,
            'filtersDropped' => $filtersDropped,
            'performance' => $page === 'content' ? $this->performance($blogIdInt, $report['topPosts'], $data) : null,
            'rightNow' => $showsRightNow ? $this->events->countRecent($blogIdInt, self::RIGHT_NOW_MINUTES) : null,
            'recent' => $showsRightNow ? $this->events->recentBreakdown($blogIdInt, self::RIGHT_NOW_MINUTES, self::RECENT_LIMIT) : null,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $topPosts
     * @param  array<string, mixed>  $data
     * @return array{posts: array<int, array<string, mixed>>, usualFirstWeek: ?int}
     */
    private function performance(int $blogId, array $topPosts, array $data): array
    {
        $postIds = array_map(static fn (array $row): int => (int) $row['post_id'], $topPosts);

        return $this->reports->performance($blogId, $postIds, $data['today'], $data['collectingSince']);
    }

    public function post(string $blogId, string $postId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllAnalytics', $blog, auth()->user());
        $post = $this->postInScope($blogIdInt, (int) $postId, $canAll);

        $range = $this->savedView->range((int) auth()->user()['id'], null, $this->request->get, blog_timezone($blogIdInt));
        $scope = AnalyticsScope::post($blogIdInt, (int) $post['id']);
        $page = $this->pageData($blog, $range, $canAll);
        [$filters, $filtersDropped] = $this->filters($scope, $range, $page['today']);

        return $this->view('insights.post', $page + [
            'page' => 'post',
            'pagePath' => $page['basePath'].'/posts/'.(int) $post['id'],
            'scope' => 'post',
            'post' => $post,
            'report' => $this->reports->report($scope, $range, $filters),
            'filters' => $filters,
            'filtersDropped' => $filtersDropped,
            'performance' => $this->reports->performance($blogIdInt, [(int) $post['id']], $page['today'], $page['collectingSince']),
            'rightNow' => null,
            'recent' => null,
        ]);
    }

    /**
     * The filters asked for, or none when the range reaches past raw retention,
     * which is the only place a narrowed page can be read from.
     *
     * @return array{0: array<string, string>, 1: bool} Filters, and whether some were dropped for that reason
     */
    private function filters(AnalyticsScope $scope, AnalyticsRange $range, string $today): array
    {
        $filters = AnalyticsReportService::filtersFrom($this->request->get['f'] ?? null, $scope);

        if ($filters === [] || $range->withinRaw($this->settings->rawRetentionDays(), new \DateTimeImmutable($today))) {
            return [$filters, false];
        }

        return [[], true];
    }

    /**
     * One breakdown, or every post with its numbers, for the chosen range as CSV.
     */
    public function export(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllAnalytics', $blog, auth()->user());

        $scope = $this->exportScope($blogIdInt, $canAll);
        $dimension = $this->exportDimension($scope);
        $range = AnalyticsRange::fromQuery($this->request->get, blog_timezone($blogIdInt));
        $filename = "insights-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv";

        if ($dimension === self::POSTS_EXPORT) {
            return $this->csv($filename, self::POSTS_COLUMNS, $this->postRows($blog, $scope, $range));
        }

        $rows = array_map(
            static fn (array $row): array => [$row['name'] ?? $row['value'], $row['views'], $row['visitors']],
            $this->reports->fullBreakdown($scope, $dimension, $range)
        );

        return $this->csv($filename, [$dimension, 'views', 'visitors'], $rows);
    }

    /**
     * The owner's counting choices for this blog: the team, excluded paths, the reader notice.
     */
    public function updateSettings(string $blogId): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $blog = $this->authorizedBlog($blogId);
        Gate::authorize('manageUsers', $blog, auth()->user());

        $this->validateOrFail(
            ['excluded_paths' => 'max:2000|regex:/^(\s*\/[A-Za-z0-9_\/-]{0,100}\s*)*$/'],
            ['excluded_paths.regex' => chrome_translate('analytics.settings.invalidPaths')]
        );

        $paths = $this->excludedPaths();
        $data = ['analytics_excluded_paths' => $paths === [] ? null : implode("\n", $paths)];
        foreach (self::SETTING_TOGGLES as $field => $column) {
            $data[$column] = $this->request->postParam($field) !== null ? 1 : 0;
        }

        $before = $this->blogSettings->findByBlogId((int) $blog->id()) ?? [];
        $this->blogSettings->updateForBlog((int) $blog->id(), $data);
        $this->forgetPublicPagesIfShownChanged($blog, $before, $data);
        $this->auditSettings($blog, $data, count($paths));

        $this->flash('success', chrome_translate('analytics.settings.saved'));

        return $this->redirect(lurl(InsightsPages::path('/dashboard/blog/'.(int) $blog->id().'/insights', 'overview')));
    }

    /**
     * The notice and the popular posts are part of every cached public page of the blog.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function forgetPublicPagesIfShownChanged(BlogResource $blog, array $before, array $after): void
    {
        foreach (self::PUBLIC_TOGGLES as $column) {
            if ((int) ($before[$column] ?? 0) !== $after[$column]) {
                $this->blogs->forgetPublicCaches($blog->slug());

                return;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function auditSettings(BlogResource $blog, array $data, int $pathCount): void
    {
        $details = ['excluded_paths' => $pathCount];
        foreach (self::SETTING_TOGGLES as $field => $column) {
            $details[$field] = $data[$column];
        }

        audit()->log(
            (int) auth()->user()['id'],
            'blog.analytics_settings_updated',
            'blog',
            (int) $blog->id(),
            $details,
            $this->request->ip()
        );
    }

    /**
     * @return list<string>
     */
    private function excludedPaths(): array
    {
        $lines = preg_split('/\R/', (string) $this->request->postParam('excluded_paths', '')) ?: [];
        $paths = array_filter(array_map('trim', $lines), static fn (string $path): bool => $path !== '');

        return array_values(array_unique($paths));
    }

    private function exportDimension(AnalyticsScope $scope): string
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');

        if ($dimension === self::POSTS_EXPORT) {
            return $dimension;
        }

        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new PageNotFoundException('Unknown analytics breakdown.');
        }

        return $dimension;
    }

    /**
     * One post when the export asks for it, otherwise what this viewer sees on the Insights pages.
     */
    private function exportScope(int $blogId, bool $canAll): AnalyticsScope
    {
        $postId = (int) ($this->request->get['post'] ?? 0);

        if ($postId > 0) {
            return AnalyticsScope::post($blogId, (int) $this->postInScope($blogId, $postId, $canAll)['id']);
        }

        return $this->viewerScope($blogId, $canAll);
    }

    /**
     * The whole blog, or only the viewer's own posts added together.
     */
    private function viewerScope(int $blogId, bool $canAll): AnalyticsScope
    {
        if ($canAll) {
            return AnalyticsScope::blog($blogId);
        }

        $userId = (int) auth()->user()['id'];

        return AnalyticsScope::authorPosts($blogId, $userId, $this->posts->idsByBlogAndAuthor($blogId, $userId));
    }

    /**
     * What every Insights page shows around the numbers themselves.
     *
     * @return array<string, mixed>
     */
    private function pageData(BlogResource $blog, AnalyticsRange $range, bool $canAll): array
    {
        $aggregatedAt = $this->settings->aggregatedAt();
        $blogSettings = $this->blogSettings->findByBlogId((int) $blog->id()) ?? [];
        $zone = new \DateTimeZone(blog_timezone((int) $blog->id()));

        return [
            'blog' => $blog->toArray(),
            'canAll' => $canAll,
            'range' => $range,
            'today' => (new \DateTimeImmutable('today', $zone))->format('Y-m-d'),
            'collectingSince' => $this->stats->firstDay(AnalyticsScope::blog((int) $blog->id())),
            'aggregatedAt' => $aggregatedAt,
            'delayed' => $this->settings->aggregationDelayed(),
            'trackingEnabled' => $this->settings->enabled(),
            'aggregationEnabled' => $this->settings->aggregationEnabled(),
            'countriesAvailable' => $this->countries->available(),
            'basePath' => '/dashboard/blog/'.(int) $blog->id().'/insights',
            'canConfigure' => Gate::allows('manageUsers', $blog, auth()->user()),
            'blogSettings' => $blogSettings,
            'blogUrl' => rtrim(base_url(), '/').lurl('/blog/'.rawurlencode($blog->slug())),
            'rawRetentionDays' => $this->settings->rawRetentionDays(),
        ];
    }

    private function authorizedBlog(string $blogId): BlogResource
    {
        $blog = $this->blogs->getBlog($blogId);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$blogId}' not found.");
        }

        Gate::authorize('viewAnalytics', $blog, auth()->user());

        return $blog;
    }

    /**
     * @return array<string, mixed>
     */
    private function postInScope(int $blogId, int $postId, bool $canAll): array
    {
        $post = $this->posts->find($postId);

        if ($post === null || (int) $post['blog_id'] !== $blogId) {
            throw new PageNotFoundException('Post not found in this blog.');
        }

        if (!$canAll && (int) $post['author_id'] !== (int) auth()->user()['id']) {
            throw new UnauthorizedException('You can only see the numbers for your own posts.');
        }

        return $post;
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
