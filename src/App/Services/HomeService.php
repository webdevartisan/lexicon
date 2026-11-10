<?php

declare(strict_types=1);

namespace App\Services;

use App\Gate;
use App\Models\BlogActivityModel;
use App\Models\BlogSettingsModel;
use App\Models\BlogSubscriberModel;
use App\Models\PostListingModel;
use App\Models\PostModel;
use App\Resources\BlogResource;
use App\Services\Analytics\AnalyticsReportService;
use App\ValueObjects\AnalyticsRange;
use App\ValueObjects\AnalyticsScope;

/**
 * What Home shows for one blog and one person.
 *
 * Every section is decided by a permission, never by a role name, so a custom
 * role gets whichever sections its permissions open. A section the person may
 * not use is null and costs no query.
 */
final class HomeService
{
    private const LIST_SIZE = 4;

    private const ACTIVITY_SIZE = 15;

    private const RHYTHM_WEEKS = 12;

    public function __construct(
        private PostModel $posts,
        private BlogSettingsModel $settings,
        private BlogSubscriberModel $subscribers,
        private AnalyticsReportService $analytics,
        private PostListingModel $postListing,
        private BlogActivityModel $activity,
        private BlogRoleLabels $roleLabels,
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
        $moderates = Gate::allows('update', $blog, $user);
        $managesUsers = Gate::allows('manageUsers', $blog, $user);
        // Whose posts the publishing figures count: everyone's for those who manage them all, else the writer's own
        $authorId = $managesAll ? null : $userId;
        $showsPosts = $managesAll || $writes;

        return [
            'workflowEnabled' => $workflowEnabled,
            'canCreate' => $writes,
            'blogPosts' => $managesAll ? $this->blogPosts($blogId, $workflowEnabled) : null,
            'myWork' => $writes && !$managesAll ? $this->myWork($blogId, $userId) : null,
            'comments' => $moderates ? [
                'approved' => $this->posts->countCommentsByBlogIdAndStatus($blogId, 'approved'),
                'pending' => $this->posts->countCommentsByBlogIdAndStatus($blogId, 'pending'),
                'recent' => $this->posts->countApprovedCommentsSince($blogId, 7),
            ] : null,
            'subscribers' => $managesUsers ? $this->subscribers->countForBlog($blogId) : null,
            'newSubscribers' => $managesUsers ? $this->subscribers->countConfirmedSince($blogId, 7) : null,
            'review' => $workflowEnabled && Gate::allows('reviewPosts', $blog, $user) ? $this->review($blogId) : null,
            'analytics' => $this->traffic($blog, $user),
            'publishedRecently' => $showsPosts ? $this->postListing->countPublishedSince($blogId, $authorId, 30) : null,
            'rhythm' => $showsPosts
                ? $this->postListing->publishedPerWeek($blogId, $authorId, self::RHYTHM_WEEKS)
                : null,
            'upNext' => $showsPosts ? $this->postListing->nextScheduled($blogId, $authorId) : null,
            'activity' => $this->activityFor($blogId, $managesAll, $moderates, $managesUsers),
        ];
    }

    /**
     * The whole blog's numbers and lists, for those who manage every post.
     *
     * @return array{counts: array<string, int>, needsAttention: list<array<string, mixed>>}
     */
    private function blogPosts(int $blogId, bool $workflowEnabled): array
    {
        $drafts = $this->postListing->findAllInBlogWithFilters($blogId, 1, self::LIST_SIZE, 'draft')['data'];
        $pending = $workflowEnabled
            ? $this->postListing->findAllInBlogWithFilters($blogId, 1, self::LIST_SIZE, 'pending')['data']
            : [];

        return [
            'counts' => $this->postListing->countsByStatusForAuthor(authorId: null, blogId: $blogId),
            'needsAttention' => array_slice(array_merge($drafts, $pending), 0, self::LIST_SIZE),
        ];
    }

    /**
     * Latest happenings on the blog, limited to the kinds this person's permissions already open.
     *
     * @return list<array<string, mixed>>|null Null when none of the kinds are open to them
     */
    private function activityFor(int $blogId, bool $managesPosts, bool $moderates, bool $managesUsers): ?array
    {
        $kinds = array_merge(
            $managesPosts ? [BlogActivityModel::PUBLISHED] : [],
            $moderates ? [BlogActivityModel::COMMENT] : [],
            $managesUsers ? [BlogActivityModel::SUBSCRIBER, BlogActivityModel::MEMBER] : [],
        );

        if ($kinds === []) {
            return null;
        }

        return array_map(function (array $row): array {
            if ($row['kind'] === BlogActivityModel::MEMBER && $row['detail'] !== null) {
                $row['detail'] = $this->roleLabels->label($row['detail']);
            }

            return $row;
        }, $this->activity->recent($blogId, $kinds, self::ACTIVITY_SIZE));
    }

    /**
     * A writer's own pipeline in this blog: drafts, submitted, sent back, published.
     *
     * @return array{counts: array<string, int>, drafts: list<array<string, mixed>>}
     */
    private function myWork(int $blogId, int $userId): array
    {
        return [
            'counts' => $this->postListing->countsByStatusForAuthor(authorId: $userId, blogId: $blogId),
            'drafts' => $this->postListing->findByAuthorWithFiltersPagination(
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
        $queue = $this->postListing->findReviewQueueForBlog($blogId);
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
        $range = AnalyticsRange::fromQuery(['range' => '7d'], 'UTC');
        $report = $this->analytics->report($scope, $range);

        return ['metrics' => $report['metrics'], 'topPost' => $report['topPosts'][0] ?? null, 'range' => $range];
    }
}
