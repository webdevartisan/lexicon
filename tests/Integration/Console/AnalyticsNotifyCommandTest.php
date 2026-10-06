<?php

declare(strict_types=1);

use App\Console\Commands\AnalyticsNotifyCommand;
use App\Models\ActivityLogModel;
use App\Models\AnalyticsDailyEventModel;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsNoticeModel;
use App\Models\AnalyticsRawStatsModel;
use App\Models\AnalyticsStatsModel;
use App\Models\BlogModel;
use App\Models\PostModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\Analytics\AnalyticsReportService;
use App\Services\Analytics\AnalyticsSettings;
use App\Services\NotificationService;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

beforeEach(function () {
    $this->ownerId = UserFactory::new(new UserModel($this->db))->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->ownerId);
    $this->postId = PostFactory::new(new PostModel($this->db))
        ->withAttributes(['blog_id' => $this->blogId, 'author_id' => $this->ownerId, 'title' => 'Field notes'])
        ->published()->create();

    // Runs the command once with these notifications, and returns what it printed.
    $this->run = function (NotificationService $notifications): string {
        $stats = new AnalyticsStatsModel($this->db);
        $command = new AnalyticsNotifyCommand(
            new AnalyticsSettings(new SettingModel($this->db)),
            new AnalyticsNoticeModel($this->db),
            $stats,
            new AnalyticsReportService($stats, new AnalyticsRawStatsModel($this->db), new AnalyticsDailyEventModel($this->db), new AnalyticsEventModel($this->db), new ActivityLogModel($this->db), 30, 10),
            new PostModel($this->db),
            new BlogModel($this->db),
            $notifications,
        );

        ob_start();
        $command->handle();

        return (string) ob_get_clean();
    };

    // One day of totals for the post and its blog.
    $this->day = function (string $day, int $postViews, int $blogViews) {
        $this->db->execute(
            "INSERT INTO analytics_daily (scope, scope_id, blog_id, day, views, visitors) VALUES
             ('post', ?, ?, ?, ?, ?), ('blog', ?, ?, ?, ?, ?)",
            [$this->postId, $this->blogId, $day, $postViews, $postViews, $this->blogId, $this->blogId, $day, $blogViews, $blogViews]
        );
    };
});

test('the first run only notes milestones already passed, and later ones are sent once', function () {
    ($this->day)('2026-01-10', 150, 150);
    $quiet = Mockery::mock(NotificationService::class);
    $quiet->shouldNotReceive('dispatch');
    ($this->run)($quiet);

    $this->db->execute("UPDATE analytics_daily SET views = 1200 WHERE scope = 'post'");
    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldReceive('dispatch')->once()->with(
        $this->ownerId,
        'analytics.milestone',
        Mockery::on(static fn (array $data): bool => $data['threshold'] === 1000 && $data['post_title'] === 'Field notes')
    );

    ($this->run)($notifications);
    ($this->run)($notifications);

    expect($this->db->query("SELECT notice_key FROM analytics_notices_sent WHERE kind = 'milestone' ORDER BY CAST(notice_key AS UNSIGNED)")->fetchAll(PDO::FETCH_COLUMN))->toEqual([100, 1000]);
});

test('a blog far busier than usual tells its owner once that day', function () {
    $today = new DateTimeImmutable('today', new DateTimeZone(blog_timezone($this->blogId)));
    for ($i = 1; $i <= 28; $i++) {
        ($this->day)($today->modify("-{$i} days")->format('Y-m-d'), 0, 10);
    }
    ($this->day)($today->format('Y-m-d'), 0, 400);

    $notifications = Mockery::mock(NotificationService::class);
    $notifications->shouldReceive('dispatch')->once()->with(
        $this->ownerId,
        'analytics.spike',
        Mockery::on(static fn (array $data): bool => $data['views'] === 400 && $data['usual'] === 10.0)
    );

    ($this->run)($notifications);
    ($this->run)($notifications);

    expect((int) $this->db->query("SELECT COUNT(*) FROM analytics_notices_sent WHERE kind = 'spike'")->fetchColumn())->toBe(1);
});
