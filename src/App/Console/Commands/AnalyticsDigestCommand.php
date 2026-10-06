<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Mail\InsightsDigestMail;
use App\Mail\Mailable;
use App\Models\AnalyticsDailyEventModel;
use App\Models\AnalyticsStatsModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\Analytics\AnalyticsReportService;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\MailQueueService;
use App\Services\RecipientLocale;
use App\ValueObjects\AnalyticsScope;

/**
 * Emails each blog owner a summary of last week's Insights, on the bulk tier.
 *
 * Scheduled daily and sends only on Mondays (UTC), for the seven days before.
 * A week is sent once, however often this runs. Owners who switched the digest
 * off, and blogs nobody read that week, are skipped.
 *
 * Usage: php cli analytics:digest [--send=on-monday|now]
 */
class AnalyticsDigestCommand implements SchedulableCommandInterface
{
    private const TOP_POSTS = 3;

    public function __construct(
        private AnalyticsSettings $settings,
        private AnalyticsStatsModel $stats,
        private AnalyticsDailyEventModel $events,
        private AnalyticsReportService $reports,
        private UserModel $users,
        private UserPreferencesModel $preferences,
        private MailQueueService $mail,
        private RecipientLocale $locales,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Weekly Insights digest';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [
            'send' => [
                'type' => 'enum',
                'label' => 'When to send',
                'values' => ['on-monday', 'now'],
                'default' => 'on-monday',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return int Exit code, 0 for success
     */
    public function handle(array $arguments = []): int
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $now = ($arguments['send'] ?? 'on-monday') === 'now';

        if (!$now && $today->format('N') !== '1') {
            echo "The digest goes out on Mondays. Nothing to send today.\n";

            return 0;
        }

        $week = $today->modify('-1 day')->format('o-\WW');
        if (!$now && $this->settings->digestWeek() === $week) {
            echo "The digest for {$week} was already sent.\n";

            return 0;
        }

        $from = $today->modify('-7 days');
        $to = $today->modify('-1 day');
        $sent = 0;

        foreach ($this->stats->publishedBlogsByOwner() as $ownerId => $blogs) {
            if ($this->sendTo($ownerId, $blogs, $from, $to)) {
                $sent++;
            }
        }

        $this->settings->markDigestSent($week);
        echo "Queued {$sent} weekly digest(s) for {$week}.\n";

        return 0;
    }

    /**
     * @param  list<array{id: int, name: string, slug: string}>  $blogs
     */
    private function sendTo(int $ownerId, array $blogs, \DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        if (!$this->preferences->notificationPreference($ownerId, 'notify_insights_digest')) {
            return false;
        }

        $owner = $this->users->findById($ownerId);
        if (!$owner || empty($owner['email'])) {
            return false;
        }

        $summaries = [];
        foreach ($blogs as $blog) {
            $summary = $this->summary($blog, $from);
            if ($summary !== null) {
                $summaries[] = $summary;
            }
        }

        if ($summaries === []) {
            return false;
        }

        $mail = Mailable::inLocale(
            $this->locales->forUser($ownerId),
            fn (): InsightsDigestMail => new InsightsDigestMail((string) $owner['email'], $from->format('Y-m-d'), $to->format('Y-m-d'), $summaries)
        );

        $this->mail->enqueue($mail, 'user', $ownerId);

        return true;
    }

    /**
     * Last week for one blog, or null when nobody read it.
     *
     * @param  array{id: int, name: string, slug: string}  $blog
     * @return array{name: string, id: int, slug: string, from: string, to: string, views: int, previous: int, visitors: int,
     *     source: ?string, posts: list<array{title: string, views: int}>, goals: array<string, int>}|null
     */
    private function summary(array $blog, \DateTimeImmutable $from): ?array
    {
        $scope = AnalyticsScope::blog($blog['id']);
        $start = $from->format('Y-m-d');
        $end = $from->modify('+6 days')->format('Y-m-d');
        $totals = $this->stats->totals($scope, $start, $end);

        if ($totals['views'] === 0) {
            return null;
        }

        $before = $this->stats->totals($scope, $from->modify('-7 days')->format('Y-m-d'), $from->modify('-1 day')->format('Y-m-d'));
        $posts = array_values(array_filter(array_map(
            static fn (array $row): ?array => $row['title'] === null ? null : ['title' => (string) $row['title'], 'views' => (int) $row['views']],
            $this->stats->topPosts($scope, $start, $end, self::TOP_POSTS)
        )));

        return $blog + [
            'from' => $start,
            'to' => $end,
            'views' => $totals['views'],
            'previous' => $before['views'],
            'visitors' => $totals['visitors'],
            'source' => $this->reports->topSourceName($scope, $start, $end),
            'posts' => $posts,
            'goals' => array_filter($this->events->goalCounts($scope, $start, $end)),
        ];
    }
}
