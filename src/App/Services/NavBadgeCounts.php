<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CommentModel;
use App\Models\ModerationCaseModel;
use App\Models\PostListingModel;
use App\Resources\BlogResource;

/**
 * Counts shown as badges beside sidebar links, such as comments waiting for approval.
 *
 * Only counters named by the links the person can see are queried, so nobody
 * gets a number for a page they cannot open.
 */
final class NavBadgeCounts
{
    public function __construct(
        private CommentModel $comments,
        private PostListingModel $posts,
        private ModerationCaseModel $cases,
    ) {}

    /**
     * Non-zero counts keyed by counter name, for the visible navigation items.
     *
     * @param  array<int, array<string, mixed>>  $items  Items from NavigationService::for()
     * @param  BlogResource|null  $blog  The active blog, if any
     * @return array<string, int>
     */
    public function for(array $items, ?BlogResource $blog): array
    {
        $counts = [];

        foreach (array_unique(array_filter(array_column($items, 'counter'))) as $counter) {
            $count = $this->count((string) $counter, $blog);
            if ($count > 0) {
                $counts[$counter] = $count;
            }
        }

        return $counts;
    }

    private function count(string $counter, ?BlogResource $blog): int
    {
        $blogId = $blog?->id();

        return match ($counter) {
            'pendingComments' => $blogId === null ? 0 : $this->comments->countByBlogIdAndStatus($blogId, 'pending'),
            'postsInReview' => $blogId === null ? 0 : $this->posts->countByBlogAndWorkflow($blogId, 'in_review'),
            'openReports' => array_sum(array_diff_key($this->cases->statusCounts(), ['resolved' => true])),
            default => 0,
        };
    }
}
