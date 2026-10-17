<?php

declare(strict_types=1);

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Gate;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
use App\Services\Analytics\BlogInsightsPage;
use App\Services\Analytics\InsightsPages;
use App\Services\Analytics\InsightsSavedView;
use App\Services\BlogCacheInvalidator;
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
    /** Checkbox name => blog_settings column. */
    private const SETTING_TOGGLES = [
        'exclude_members' => 'analytics_exclude_members',
        'public_notice' => 'analytics_public_notice',
        'popular_posts' => 'analytics_popular_posts',
        'public_stats' => 'analytics_public_stats',
    ];

    /** Switches whose change shows on every public page of the blog. */
    private const PUBLIC_TOGGLES = ['analytics_public_notice', 'analytics_popular_posts'];

    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
        private BlogSettingsModel $blogSettings,
        private InsightsSavedView $savedView,
        private BlogInsightsPage $insightsPage,
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
            return $this->redirect(lurl(InsightsPages::path($this->basePath($blog), $last)));
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
        $frame = $this->frame($blog, $range, $canAll);

        return $this->view('insights.'.$page, $this->insightsPage->page(
            $page,
            $this->viewerScope($blogIdInt, $canAll),
            $this->request->get['f'] ?? null,
            $frame
        ));
    }

    public function post(string $blogId, string $postId): Response
    {
        $blog = $this->authorizedBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $canAll = Gate::allows('viewAllAnalytics', $blog, auth()->user());
        $post = $this->postInScope($blogIdInt, (int) $postId, $canAll);

        $range = $this->savedView->range((int) auth()->user()['id'], null, $this->request->get, blog_timezone($blogIdInt));

        return $this->view('insights.post', $this->insightsPage->post(
            $post,
            $this->request->get['f'] ?? null,
            $this->frame($blog, $range, $canAll)
        ));
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
        $dimension = (string) ($this->request->get['dimension'] ?? '');
        $range = AnalyticsRange::fromQuery($this->request->get, blog_timezone($blogIdInt));
        [$columns, $rows] = $this->insightsPage->export($blog, $scope, $dimension, $range);

        return $this->csv("insights-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv", $columns, $rows);
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

        return $this->redirect(lurl(InsightsPages::path($this->basePath($blog), 'overview')));
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
                $this->blogCache()->forgetBlog($blog->slug());

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
     * @return array<string, mixed>
     */
    private function frame(BlogResource $blog, AnalyticsRange $range, bool $canAll): array
    {
        return $this->insightsPage->frame(
            $blog,
            $range,
            $canAll,
            $this->basePath($blog),
            Gate::allows('manageUsers', $blog, auth()->user())
        );
    }

    private function basePath(BlogResource $blog): string
    {
        return '/dashboard/blog/'.(int) $blog->id().'/insights';
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

    private function blogCache(): BlogCacheInvalidator
    {
        return app(BlogCacheInvalidator::class);
    }
}
