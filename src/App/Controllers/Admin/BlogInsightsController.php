<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
use App\Services\Analytics\BlogInsightsPage;
use App\Services\Analytics\InsightsPages;
use App\Services\Analytics\InsightsSavedView;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;
use Framework\Core\Response;
use Framework\Exceptions\PageNotFoundException;

/**
 * One blog's Insights in the control panel, for staff who see the platform's
 * numbers. The same pages the blog's team sees, always the whole blog, with
 * nothing to change. Staff stay in the control panel, so the dashboard keeps
 * meaning the blogs a person works in.
 */
final class BlogInsightsController extends AppController
{
    protected ?string $areaAbility = 'viewPlatformAnalytics';

    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
        private InsightsSavedView $savedView,
        private BlogInsightsPage $insightsPage,
    ) {}

    public function index(string $blogId): Response
    {
        return $this->page($blogId, 'overview');
    }

    public function page(string $blogId, string $page): Response
    {
        $blog = $this->findBlog($blogId);
        $pages = $this->pages($blog);

        if (!in_array($page, $pages, true)) {
            throw new PageNotFoundException("There is no Insights page called '{$page}' for this blog.");
        }

        $data = $this->insightsPage->page(
            $page,
            AnalyticsScope::blog((int) $blog->id()),
            $this->request->get['f'] ?? null,
            $this->frame($blog)
        );

        return $this->view('dashboard.insights.'.$page, $data + ['staffPages' => $pages]);
    }

    public function post(string $blogId, string $postId): Response
    {
        $blog = $this->findBlog($blogId);
        $post = $this->postInBlog($blog, (int) $postId);
        $data = $this->insightsPage->post($post, $this->request->get['f'] ?? null, $this->frame($blog));

        return $this->view('dashboard.insights.post', $data + ['staffPages' => $this->pages($blog)]);
    }

    /**
     * One breakdown, or every post with its numbers, for the chosen range as CSV.
     */
    public function export(string $blogId): Response
    {
        $blog = $this->findBlog($blogId);
        $blogIdInt = (int) $blog->id();
        $postId = (int) ($this->request->get['post'] ?? 0);
        $scope = $postId > 0
            ? AnalyticsScope::post($blogIdInt, (int) $this->postInBlog($blog, $postId)['id'])
            : AnalyticsScope::blog($blogIdInt);
        $dimension = (string) ($this->request->get['dimension'] ?? '');
        $range = AnalyticsRange::fromQuery($this->request->get, blog_timezone($blogIdInt));
        [$columns, $rows] = $this->insightsPage->export($blog, $scope, $dimension, $range);

        return $this->csv("insights-{$dimension}-{$range->fromDate()}-{$range->toDate()}.csv", $columns, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function frame(BlogResource $blog): array
    {
        $blogId = (int) $blog->id();
        // No page name: looking at someone's blog must not change where the control panel's Insights link resumes.
        $range = $this->savedView->range((int) auth()->user()['id'], null, $this->request->get, blog_timezone($blogId));

        return $this->insightsPage->frame($blog, $range, true, '/admin/insights/blogs/'.$blogId, false);
    }

    /**
     * The blog's pages in sidebar order. Authors needs two people who published,
     * the same rule the blog's own team gets.
     *
     * @return list<string>
     */
    private function pages(BlogResource $blog): array
    {
        $pages = array_keys(InsightsPages::BLOG);

        if ($blog->publishedAuthorCount() < 2) {
            $pages = array_values(array_diff($pages, ['authors']));
        }

        return $pages;
    }

    private function findBlog(string $blogId): BlogResource
    {
        $blog = $this->blogs->getBlog($blogId);

        if (!$blog) {
            throw new PageNotFoundException("Blog with ID '{$blogId}' not found.");
        }

        return $blog;
    }

    /**
     * @return array<string, mixed>
     */
    private function postInBlog(BlogResource $blog, int $postId): array
    {
        $post = $this->posts->find($postId);

        if ($post === null || (int) $post['blog_id'] !== (int) $blog->id()) {
            throw new PageNotFoundException('Post not found in this blog.');
        }

        return $post;
    }
}
