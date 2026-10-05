<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\TrafficHitModel;
use App\Models\TrafficStatsModel;
use App\Resources\BlogResource;
use App\Services\Traffic\CountryLookup;
use App\Services\Traffic\TrafficReportService;
use App\Services\Traffic\TrafficSettings;
use App\ValueObjects\TrafficRange;
use App\ValueObjects\TrafficScope;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\UnauthorizedException;

/**
 * Insights > Traffic for a blog and for one post.
 *
 * Owners and editors see the whole blog. Authors see only their own posts,
 * on the page, the post page and the CSV export.
 */
final class TrafficController extends AppController
{
    private const RIGHT_NOW_MINUTES = 30;

    /** The Top posts export. Not a breakdown dimension. */
    private const POSTS_EXPORT = 'posts';

    private const POSTS_COLUMNS = ['post', 'url', 'views', 'visitors', 'read_ratio_percent', 'avg_read_seconds'];

    /** Checkbox name => blog_settings column. */
    private const SETTING_TOGGLES = [
        'exclude_members' => 'traffic_exclude_members',
        'public_notice' => 'traffic_public_notice',
    ];

    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
        private TrafficStatsModel $stats,
        private TrafficHitModel $hits,
        private TrafficReportService $reports,
        private TrafficSettings $settings,
        private CountryLookup $countries,
        private BlogSettingsModel $blogSettings,
    ) {}

    public function index(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllTraffic', $blog, auth()->user());
        $range = TrafficRange::fromQuery($this->request->get, blog_timezone($blogIdInt));

        return $this->view($this->pageData($blog, $range, $canAll) + [
            'scope' => $canAll ? 'blog' : 'author',
            'post' => null,
            'report' => $this->reports->report($this->viewerScope($blogIdInt, $canAll), $range),
            'rightNow' => $canAll ? $this->hits->countRecent($blogIdInt, self::RIGHT_NOW_MINUTES) : null,
        ]);
    }

    public function post(string $blogId, string $postId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllTraffic', $blog, auth()->user());
        $post = $this->postInScope($blogIdInt, (int) $postId, $canAll);

        $range = TrafficRange::fromQuery($this->request->get, blog_timezone($blogIdInt));

        return $this->view('traffic.index', $this->pageData($blog, $range, $canAll) + [
            'scope' => 'post',
            'post' => $post,
            'report' => $this->reports->report(TrafficScope::post($blogIdInt, (int) $post['id']), $range),
            'rightNow' => null,
        ]);
    }

    /**
     * One breakdown, or every post with its numbers, for the chosen range as CSV.
     */
    public function export(string $blogId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllTraffic', $blog, auth()->user());

        $scope = $this->exportScope($blogIdInt, $canAll);
        $dimension = $this->exportDimension($scope);
        $range = TrafficRange::fromQuery($this->request->get, blog_timezone($blogIdInt));
        $filename = "traffic-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv";

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
            ['excluded_paths.regex' => chrome_translate('traffic.settings.invalidPaths')]
        );

        $paths = $this->excludedPaths();
        $data = ['traffic_excluded_paths' => $paths === [] ? null : implode("\n", $paths)];
        foreach (self::SETTING_TOGGLES as $field => $column) {
            $data[$column] = $this->request->postParam($field) !== null ? 1 : 0;
        }

        $before = $this->blogSettings->findByBlogId((int) $blog->id()) ?? [];
        $this->blogSettings->updateForBlog((int) $blog->id(), $data);
        $this->forgetPublicPagesIfNoticeChanged($blog, $before, $data);
        $this->auditSettings($blog, $data, count($paths));

        $this->flash('success', chrome_translate('traffic.settings.saved'));

        return $this->redirect(lurl('/dashboard/blog/'.(int) $blog->id().'/analytics/traffic'));
    }

    /**
     * The notice is part of every cached public page of the blog.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function forgetPublicPagesIfNoticeChanged(BlogResource $blog, array $before, array $after): void
    {
        if ((int) ($before['traffic_public_notice'] ?? 0) !== $after['traffic_public_notice']) {
            $this->blogs->forgetPublicCaches($blog->slug());
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
            'blog.traffic_settings_updated',
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

    private function exportDimension(TrafficScope $scope): string
    {
        $dimension = (string) ($this->request->get['dimension'] ?? '');

        if ($dimension === self::POSTS_EXPORT) {
            return $dimension;
        }

        if (!in_array($dimension, $scope->breakdowns(), true)) {
            throw new PageNotFoundException('Unknown traffic breakdown.');
        }

        return $dimension;
    }

    /**
     * One post when the export asks for it, otherwise what this viewer sees on the Traffic page.
     */
    private function exportScope(int $blogId, bool $canAll): TrafficScope
    {
        $postId = (int) ($this->request->get['post'] ?? 0);

        if ($postId > 0) {
            return TrafficScope::post($blogId, (int) $this->postInScope($blogId, $postId, $canAll)['id']);
        }

        return $this->viewerScope($blogId, $canAll);
    }

    /**
     * The whole blog, or only the viewer's own posts added together.
     */
    private function viewerScope(int $blogId, bool $canAll): TrafficScope
    {
        if ($canAll) {
            return TrafficScope::blog($blogId);
        }

        $userId = (int) auth()->user()['id'];

        return TrafficScope::authorPosts($blogId, $userId, $this->posts->idsByBlogAndAuthor($blogId, $userId));
    }

    /**
     * What every Traffic page shows around the numbers themselves.
     *
     * @return array<string, mixed>
     */
    private function pageData(BlogResource $blog, TrafficRange $range, bool $canAll): array
    {
        $aggregatedAt = $this->settings->aggregatedAt();
        $blogSettings = $this->blogSettings->findByBlogId((int) $blog->id()) ?? [];
        $zone = new \DateTimeZone(blog_timezone((int) $blog->id()));

        return [
            'blog' => $blog->toArray(),
            'canAll' => $canAll,
            'range' => $range,
            'today' => (new \DateTimeImmutable('today', $zone))->format('Y-m-d'),
            'collectingSince' => $this->stats->firstDay(TrafficScope::blog((int) $blog->id())),
            'aggregatedAt' => $aggregatedAt,
            'delayed' => $this->settings->aggregationDelayed(),
            'trackingEnabled' => $this->settings->enabled(),
            'aggregationEnabled' => $this->settings->aggregationEnabled(),
            'countriesAvailable' => $this->countries->available(),
            'basePath' => '/dashboard/blog/'.(int) $blog->id().'/analytics/traffic',
            'canConfigure' => Gate::allows('manageUsers', $blog, auth()->user()),
            'blogSettings' => $blogSettings,
        ];
    }

    private function authorizedBlog(string $blogId): BlogResource
    {
        $blog = $this->blogs->getBlog($blogId);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$blogId}' not found.");
        }

        Gate::authorize('viewTraffic', $blog, auth()->user());

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
            throw new UnauthorizedException('You can only see traffic for your own posts.');
        }

        return $post;
    }

    /**
     * Every post in scope that had views in the range, busiest first, as CSV rows.
     *
     * @return list<list<string|int|float>>
     */
    private function postRows(BlogResource $blog, TrafficScope $scope, TrafficRange $range): array
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
                $row['title'] ?? chrome_translate('traffic.topPosts.deleted'),
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
