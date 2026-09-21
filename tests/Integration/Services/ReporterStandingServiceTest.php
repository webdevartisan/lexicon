<?php

declare(strict_types=1);

use App\Exceptions\ReportRejectedException;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\ReporterStandingService;
use Tests\Factories\BlogFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModerationTestHelper;

/**
 * Part D: someone whose reports keep proving unfounded is warned first, and
 * only then can a moderator pause their reporting (DSA Art. 23). While the
 * pause lasts, their reports are refused with the date it ends.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->standing = ModerationTestHelper::standing($this->db);
    $this->moderation = ModerationTestHelper::services($this->db, ['moderation.burst_window_minutes' => '0']);

    $this->moderatorId = UserFactory::new($this->users)->create();
    $this->reporterId = UserFactory::new($this->users)->create();
    ModerationTestHelper::season($this->db, $this->reporterId);

    $authorId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($authorId);
    $postId = PostFactory::new(new PostModel($this->db))
        ->withAttributes(['author_id' => $authorId, 'blog_id' => $blogId, 'visibility' => 'public'])
        ->published()->create();
    $this->commentIds = array_map(
        fn (): int => CommentFactory::new(new CommentModel($this->db))->withAttributes(['status' => 'approved'])->create($postId, $authorId),
        range(1, 3)
    );
});

afterEach(function () {
    Mockery::close();
});

/**
 * File a report on a comment and dismiss it with that report marked unfounded.
 */
function fileUnfounded(array $moderation, int $reporterId, int $commentId, int $moderatorId): void
{
    $caseId = (int) $moderation['intake']->file($reporterId, 'comment', $commentId, 'hate', null)['case_id'];
    $reportIds = array_map(static fn (array $r): int => (int) $r['id'], $moderation['reports']->forCase($caseId));

    $moderation['moderation']->dismiss($moderation['cases']->findDetail($caseId), $moderatorId, 'Not hateful.', $reportIds);
}

it('will not pause someone who has not been warned first', function () {
    expect(fn () => $this->standing->pause($this->reporterId, $this->moderatorId, 30, 'Too many bad reports.'))
        ->toThrow(RuntimeException::class, 'Warn this person');

    expect($this->users->findById($this->reporterId)['reports_paused_until'])->toBeNull();
});

it('warns in app and by queued email, and keeps it on their record', function () {
    fileUnfounded($this->moderation, $this->reporterId, $this->commentIds[0], $this->moderatorId);

    $this->standing->warn($this->reporterId, $this->moderatorId, 'Please only report posts that break the rules.');

    $notification = $this->db->query('SELECT type, data FROM notifications WHERE user_id = ?', [$this->reporterId])->fetch(\PDO::FETCH_ASSOC);
    $mail = $this->db->query(
        "SELECT to_email FROM mail_queue WHERE related_type = 'moderation_reporter' AND related_id = ?",
        [$this->reporterId]
    )->fetchColumn();
    $history = $this->standing->history($this->reporterId);

    expect($notification['type'])->toBe('moderation.reporter_warning')
        ->and(json_decode($notification['data'], true)['unfounded'])->toBe(1)
        ->and($mail)->toBe($this->users->findById($this->reporterId)['email'])
        ->and($history)->toHaveCount(1)
        ->and($history[0]['action'])->toBe(ReporterStandingService::WARNED)
        ->and($history[0]['details']['message'])->toBe('Please only report posts that break the rules.');
});

it('refuses their reports while paused, saying until when, and takes them again once the pause ends', function () {
    $this->standing->warn($this->reporterId, $this->moderatorId, 'Please only report posts that break the rules.');
    $until = $this->standing->pause($this->reporterId, $this->moderatorId, 30, 'Kept going after the warning.');

    expect(fn () => $this->moderation['intake']->file($this->reporterId, 'comment', $this->commentIds[1], 'spam', null))
        ->toThrow(ReportRejectedException::class, 'paused your reports until');

    expect($this->db->query('SELECT COUNT(*) FROM content_reports WHERE reporter_id = ?', [$this->reporterId])->fetchColumn())->toBe(0)
        ->and(strtotime($until.' UTC'))->toBeGreaterThan(time() + 29 * 86400);

    $this->standing->resume($this->reporterId, $this->moderatorId, 'Ended early after they got in touch.');

    $filed = $this->moderation['intake']->file($this->reporterId, 'comment', $this->commentIds[1], 'spam', null);

    expect($filed['recorded'])->toBeTrue()
        ->and(array_column($this->standing->history($this->reporterId), 'action'))
        ->toBe([ReporterStandingService::RESUMED, ReporterStandingService::PAUSED, ReporterStandingService::WARNED]);
});

it('treats a pause that has run out as over', function () {
    $this->db->execute('UPDATE users SET reports_paused_until = UTC_TIMESTAMP() - INTERVAL 1 DAY WHERE id = ?', [$this->reporterId]);

    $filed = $this->moderation['intake']->file($this->reporterId, 'comment', $this->commentIds[0], 'spam', null);

    expect($filed['recorded'])->toBeTrue()
        ->and(ReporterStandingService::pausedUntil($this->users->findById($this->reporterId)))->toBeNull();
});

it('refuses a pause outside 1 to 365 days, and ending a pause that is not there', function () {
    $this->standing->warn($this->reporterId, $this->moderatorId, 'Please only report posts that break the rules.');

    expect(fn () => $this->standing->pause($this->reporterId, $this->moderatorId, 366, 'Too long.'))
        ->toThrow(RuntimeException::class, 'from 1 to 365 days')
        ->and(fn () => $this->standing->resume($this->reporterId, $this->moderatorId, 'Nothing to end.'))
        ->toThrow(RuntimeException::class, 'not paused');
});

it('sums up how their reports turned out, and lists them with their cases', function () {
    fileUnfounded($this->moderation, $this->reporterId, $this->commentIds[0], $this->moderatorId);
    $this->moderation['intake']->file($this->reporterId, 'comment', $this->commentIds[1], 'spam', null);

    $summary = $this->moderation['reports']->reporterSummary($this->reporterId);
    $history = $this->moderation['reports']->reporterHistory($this->reporterId);

    expect($summary)->toBe(['filed' => 2, 'pending' => 1, 'upheld' => 0, 'dismissed' => 0, 'unfounded' => 1])
        ->and($history)->toHaveCount(2)
        ->and(array_column($history, 'outcome'))->toContain('unfounded', 'pending')
        ->and($history[0]['case_status'])->not->toBeNull();
});

it('points the queue at reporters over the unfounded limit until they are paused', function () {
    foreach ($this->commentIds as $commentId) {
        fileUnfounded($this->moderation, $this->reporterId, $commentId, $this->moderatorId);
    }

    $over = $this->moderation['reports']->reportersOverLimit(3, 90);

    expect($over['total'])->toBe(1)
        ->and($over['reporters'][0]['unfounded'])->toBe(3)
        ->and($over['reporters'][0]['warned_at'])->not->toBeNull()
        ->and($this->moderation['reports']->reportersOverLimit(4, 90)['total'])->toBe(0);

    $this->standing->pause($this->reporterId, $this->moderatorId, 30, 'Kept going after the warning.');
    expect($this->moderation['reports']->reportersOverLimit(3, 90)['total'])->toBe(0);
});

it('warns someone by itself, once, when a dismissal takes them to the unfounded limit', function () {
    fileUnfounded($this->moderation, $this->reporterId, $this->commentIds[0], $this->moderatorId);
    fileUnfounded($this->moderation, $this->reporterId, $this->commentIds[1], $this->moderatorId);

    expect($this->standing->history($this->reporterId))->toBe([]);

    fileUnfounded($this->moderation, $this->reporterId, $this->commentIds[2], $this->moderatorId);
    $history = $this->standing->history($this->reporterId);
    $actor = $this->db->query(
        'SELECT user_id FROM activity_log WHERE action = ? AND resource_id = ?',
        [ReporterStandingService::WARNED, $this->reporterId]
    )->fetchColumn();

    expect($history)->toHaveCount(1)
        ->and($history[0]['details']['rule'])->toBe('unfounded_limit')
        ->and($history[0]['details']['message'])->toBe(ReporterStandingService::STANDARD_WARNING)
        ->and($actor)->toBeNull()
        ->and($this->db->query("SELECT COUNT(*) FROM mail_queue WHERE related_type = 'moderation_reporter' AND related_id = ?", [$this->reporterId])->fetchColumn())->toBe(1)
        ->and($this->standing->warnIfOverLimit($this->reporterId))->toBeFalse();
});

it('leaves the warning to a moderator when automatic warnings are off', function () {
    (new App\Models\SettingModel($this->db))->set('moderation.auto_warn_reporters', '0');

    foreach ($this->commentIds as $commentId) {
        fileUnfounded($this->moderation, $this->reporterId, $commentId, $this->moderatorId);
    }

    expect($this->standing->history($this->reporterId))->toBe([]);
});

it('shows open cases and unfounded reports on the admin user list', function () {
    fileUnfounded($this->moderation, $this->reporterId, $this->commentIds[0], $this->moderatorId);
    $handle = (string) $this->users->findById($this->reporterId)['handle'];

    $row = $this->users->findAllForAdmin(1, 20, $handle)['data'][0];

    expect((int) $row['unfounded_reports'])->toBe(1)
        ->and((int) $row['open_cases'])->toBe(0)
        ->and($row)->toHaveKey('reports_paused_until');
});
