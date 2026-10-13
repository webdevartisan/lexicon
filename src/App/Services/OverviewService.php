<?php

declare(strict_types=1);

namespace App\Services;

use App\Gate;
use App\Models\BlogSettingsModel;
use App\Models\BlogSubscriberModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
use App\Services\Analytics\AnalyticsReportService;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;

/**
 * What the Overview shows for one blog and one person.
 *
 * Every section is decided by a permission, never by a role name, so a custom
 * role gets whichever sections its permissions open. A section the person may
 * not use is null and costs no query.
 */
final class OverviewService
{
    private const LIST_SIZE = 4;

    public function __construct(
        private PostModel $posts,
        private BlogSettingsModel $settings,
        private BlogSubscriberModel $subscribers,
        private AnalyticsReportService $analytics,
    ) {}

    /**
     * @param  array<string, mixed>  $user  Authenticated user record
     * @return array<string, mixed>
     */
    public function for(BlogResource $blog, array $user): array
    {
        $blogId = $blog->id();
        $userId = (int) $user['id'];
        $settings = $this->settings->findByBlogId($blogId) ?? [];
        $workflowEnabled = !empty($settings['workflow_enabled']);
        $managesAll = Gate::allows('managePosts', $blog, $user);
        $writes = Gate::allows('createPost', $blog, $user);

        return [
            'workflowEnabled' => $workflowEnabled,
            'logoPath' => (string) ($settings['logo_path'] ?? ''),
            'canCreate' => $writes,
            'blogPosts' => $managesAll ? $this->blogPosts($blogId, $workflowEnabled) : null,
            'myWork' => $writes && !$managesAll ? $this->myWork($blogId, $userId) : null,
            'comments' => Gate::allows('update', $blog, $user) ? [
                'approved' => $this->posts->countCommentsByBlogIdAndStatus($blogId, 'approved'),
                'pending' => $this->posts->countCommentsByBlogIdAndStatus($blogId, 'pending'),
            ] : null,
            'subscribers' => Gate::allows('manageUsers', $blog, $user) ? $this->subscribers->countForBlog($blogId) : null,
            'review' => $workflowEnabled && Gate::allows('reviewPosts', $blog, $user) ? $this->review($blogId) : null,
            'analytics' => $this->traffic($blog, $user),
        ];
    }

    /**
     * The whole blog's numbers and lists, for those who manage every post.
     *
     * @return array{counts: array<string, int>, recent: list<array<string, mixed>>, needsAttention: list<array<string, mixed>>}
     */
    private function blogPosts(int $blogId, bool $workflowEnabled): array
    {
        $drafts = $this->posts->findAllInBlogWithFilters($blogId, 1, self::LIST_SIZE, 'draft')['data'];
        $pending = $workflowEnabled
            ? $this->posts->findAllInBlogWithFilters($blogId, 1, self::LIST_SIZE, 'pending')['data']
            : [];

        return [
            'counts' => $this->posts->countsByStatusForAuthor(authorId: null, blogId: $blogId),
            'recent' => $this->posts->findAllInBlogWithFilters($blogId, 1, self::LIST_SIZE, 'published')['data'],
            'needsAttention' => array_slice(array_merge($drafts, $pending), 0, self::LIST_SIZE),
        ];
    }

    /**
     * A writer's own pipeline in this blog: drafts, submitted, sent back, published.
     *
     * @return array{counts: array<string, int>, drafts: list<array<string, mixed>>}
     */
    private function myWork(int $blogId, int $userId): array
    {
        return [
            'counts' => $this->posts->countsByStatusForAuthor(authorId: $userId, blogId: $blogId),
            'drafts' => $this->posts->findByAuthorWithFiltersPagination(
                authorId: $userId, perPage: self::LIST_SIZE, blogId: $blogId, status: 'draft'
            )['data'],
        ];
    }

    /**
     * The review queue at a glance. One query: the queue is short and the counts come from it.
     *
     * @return array{inReview: int, needsChanges: int, items: list<array<string, mixed>>}
     */
    private function review(int $blogId): array
    {
        $queue = $this->posts->findReviewQueueForBlog($blogId);
        $states = array_count_values(array_column($queue, 'workflow_state'));

        return [
            'inReview' => (int) ($states['in_review'] ?? 0),
            'needsChanges' => (int) ($states['needs_changes'] ?? 0),
            'items' => array_slice($queue, 0, 3),
        ];
    }

    /**
     * The last 7 days: the whole blog for those who see all of Insights, a
     * writer's own posts for them, nothing for anyone Insights is closed to.
     *
     * @param  array<string, mixed>  $user
     * @return array{metrics: array<string, mixed>, topPost: array<string, mixed>|null, range: AnalyticsRange}|null
     */
    private function traffic(BlogResource $blog, array $user): ?array
    {
        if (!Gate::allows('viewAnalytics', $blog, $user)) {
            return null;
        }

        $blogId = $blog->id();
        $userId = (int) $user['id'];
        $scope = Gate::allows('viewAllAnalytics', $blog, $user)
            ? AnalyticsScope::blog($blogId)
            : AnalyticsScope::authorPosts($blogId, $userId, $this->posts->idsByBlogAndAuthor($blogId, $userId));
        $range = AnalyticsRange::fromQuery(['range' => '7d'], blog_timezone($blogId));
        $report = $this->analytics->report($scope, $range);

        return ['metrics' => $report['metrics'], 'topPost' => $report['topPosts'][0] ?? null, 'range' => $range];
    }
}
