<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\AnalyticsNoticeModel;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Services\Analytics\AnalyticsReportService;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\NotificationService;
use App\ValueObjects\AnalyticsScope;

/**
 * Tells authors when a post passes a view milestone, and owners when their
 * blog is far busier than usual today. Each notice goes out once.
 *
 * The first run ever only notes which milestones posts had already passed, so
 * switching this on doesn't send a backlog.
 *
 * Usage: php cli analytics:notify
 */
class AnalyticsNotifyCommand implements SchedulableCommandInterface
{
    /** Views a post's author hears about, all-time. */
    public const MILESTONES = [100, 1000, 10000, 100000];

    public function __construct(
        private AnalyticsSettings $settings,
        private AnalyticsNoticeModel $notices,
        private AnalyticsStatsModel $stats,
        private AnalyticsReportService $reports,
        private PostModel $posts,
        private BlogModel $blogs,
        private NotificationService $notifications,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Insights milestones and spikes';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return int Exit code, 0 for success
     */
    public function handle(array $arguments = []): int
    {
        if (!$this->settings->enabled()) {
            echo "Visit counting is switched off. No Insights notices were sent.\n";

            return 0;
        }

        $milestones = $this->milestones();
        $spikes = $this->spikes();

        echo "Sent {$milestones} milestone notice(s) and {$spikes} spike notice(s).\n";

        return 0;
    }

    private function milestones(): int
    {
        $firstRun = !$this->notices->hasAnyMilestone();
        $reached = $this->notices->reachedMilestones();
        $sent = 0;

        foreach ($this->notices->postsWithViews(min(self::MILESTONES)) as $row) {
            $passed = array_filter(self::MILESTONES, static fn (int $threshold): bool => $row['views'] >= $threshold);
            $new = array_diff($passed, $reached[$row['post_id']] ?? []);

            if ($new === []) {
                continue;
            }

            foreach ($new as $threshold) {
                $this->notices->recordMilestone($row['post_id'], $threshold);
            }

            $post = $firstRun ? null : $this->posts->find($row['post_id']);
            if ($post === null || ($post['status'] ?? '') !== 'published') {
                continue;
            }

            // A post that jumped past several at once is announced at the highest.
            $this->notifications->dispatch((int) $post['author_id'], 'analytics.milestone', [
                'post_id' => (int) $post['id'],
                'blog_id' => $row['blog_id'],
                'post_title' => (string) $post['title'],
                'threshold' => max($new),
            ]);
            $sent++;
        }

        return $sent;
    }

    private function spikes(): int
    {
        $sent = 0;
        $since = (new \DateTimeImmutable('yesterday', new \DateTimeZone('UTC')))->format('Y-m-d');

        foreach ($this->notices->busyBlogsSince($since, AnalyticsReportService::SPIKE_MIN_VIEWS) as $blogId) {
            $blog = $this->blogs->find($blogId);
            if ($blog === null || ($blog['status'] ?? '') !== 'published') {
                continue;
            }

            $scope = AnalyticsScope::blog($blogId);
            $today = (new \DateTimeImmutable('today', new \DateTimeZone(blog_timezone($blogId))))->format('Y-m-d');
            $pace = $this->stats->pace($scope, $today, AnalyticsReportService::USUAL_DAYS);

            if (!AnalyticsReportService::isSpike($pace['today'], $pace['usual']) || !$this->notices->claimSpike($blogId, $today)) {
                continue;
            }

            $this->notifications->dispatch((int) $blog['owner_id'], 'analytics.spike', [
                'blog_id' => $blogId,
                'blog_name' => (string) $blog['blog_name'],
                'views' => $pace['today'],
                'usual' => round($pace['usual'], 1),
                'source' => $this->reports->topSourceName($scope, $today, $today),
            ]);
            $sent++;
        }

        return $sent;
    }
}
