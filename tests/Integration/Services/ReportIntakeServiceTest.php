<?php

declare(strict_types=1);

use App\Exceptions\ReportRejectedException;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\UserModel;
use Tests\Factories\BlogFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;
use Tests\Helpers\ModerationTestHelper;

/**
 * Filing a report: every report on an item joins that item's one open case,
 * nothing the client says is taken on trust, and a reporter's standing decides
 * whether their report counts toward an automatic action.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->comments = new CommentModel($this->db);

    $this->authorId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->authorId);
    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->authorId, 'blog_id' => $blogId, 'title' => 'Reported post', 'visibility' => 'public'])
        ->published()->create();
    $this->commentId = CommentFactory::new($this->comments)
        ->withAttributes(['status' => 'approved', 'content' => 'A comment someone objected to'])
        ->create($this->postId, $this->authorId);

    $this->reporters = [];
    foreach (range(1, 3) as $n) {
        $id = UserFactory::new($this->users)->create();
        ModerationTestHelper::season($this->db, $id);
        $this->reporters[] = $id;
    }

    // Other reasons would fire rules here; this file is about intake only.
    $this->categories = ModerationTestHelper::snapshotCategories($this->db);
    $this->db->execute("UPDATE moderation_categories SET auto_action = 'none', threshold = NULL");

    $this->moderation = ModerationTestHelper::services($this->db);
    $this->intake = $this->moderation['intake'];
});

afterEach(function () {
    ModerationTestHelper::restoreCategories($this->db, $this->categories);
    Mockery::close();
});

function caseRow(\Framework\Database $db, int $caseId): array
{
    return $db->query('SELECT * FROM moderation_cases WHERE id = ?', [$caseId])->fetch(\PDO::FETCH_ASSOC);
}

it('opens a case for the first report and records who, why, and the snapshot', function () {
    $result = $this->intake->file($this->reporters[0], 'comment', $this->commentId, 'spam', 'Posted the same link twice');

    $case = caseRow($this->db, $result['case_id']);
    $report = $this->db->query('SELECT * FROM content_reports WHERE case_id = ?', [$result['case_id']])->fetch(\PDO::FETCH_ASSOC);

    expect($result['recorded'])->toBeTrue()
        ->and($case['open_key'])->toBe('comment:'.$this->commentId)
        ->and((int) $case['subject_author_id'])->toBe($this->authorId)
        ->and($case['subject_snapshot'])->toBe('A comment someone objected to')
        ->and((int) $case['report_count'])->toBe(1)
        ->and($case['top_category'])->toBe('spam')
        ->and((int) $case['priority'])->toBeGreaterThan(0)
        ->and($report['details'])->toBe('Posted the same link twice')
        ->and((int) $report['counts_toward_threshold'])->toBe(1);
});

it('merges reports from different people on one item into a single case', function () {
    $first = $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', null);
    $second = $this->intake->file($this->reporters[1], 'post', $this->postId, 'harassment', null);
    $third = $this->intake->file($this->reporters[2], 'post', $this->postId, 'spam', null);

    $cases = (int) $this->db->query("SELECT COUNT(*) FROM moderation_cases WHERE subject_type = 'post' AND subject_id = ?", [$this->postId])->fetchColumn();
    $case = caseRow($this->db, $first['case_id']);

    expect([$second['case_id'], $third['case_id']])->toBe([$first['case_id'], $first['case_id']])
        ->and($cases)->toBe(1)
        ->and((int) $case['report_count'])->toBe(3)
        ->and((int) $case['counted_report_count'])->toBe(3)
        // Harassment outranks spam even though spam has more reports.
        ->and($case['top_category'])->toBe('harassment');
});

it('counts a repeat report from the same person once, and says so', function () {
    $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', null);
    $again = $this->intake->file($this->reporters[0], 'post', $this->postId, 'hate', null);

    $reports = (int) $this->db->query('SELECT COUNT(*) FROM content_reports WHERE subject_id = ?', [$this->postId])->fetchColumn();
    $badge = (int) $this->db->query('SELECT reports_count FROM posts WHERE id = ?', [$this->postId])->fetchColumn();

    expect($again['recorded'])->toBeFalse()
        ->and($reports)->toBe(1)
        ->and($badge)->toBe(1);
});

it('opens a fresh case when the item is reported again after a resolution', function () {
    $first = $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', null);
    $this->db->execute("UPDATE moderation_cases SET status = 'resolved', open_key = NULL WHERE id = ?", [$first['case_id']]);

    $second = $this->intake->file($this->reporters[1], 'post', $this->postId, 'spam', null);

    expect($second['case_id'])->not->toBe($first['case_id'])
        ->and((int) caseRow($this->db, $second['case_id'])['report_count'])->toBe(1);
});

it('rejects a category that does not exist or has been retired, and writes nothing', function (string $category) {
    $this->db->execute("UPDATE moderation_categories SET is_active = 0 WHERE slug = 'misinformation'");

    expect(fn () => $this->intake->file($this->reporters[0], 'post', $this->postId, $category, null))
        ->toThrow(ReportRejectedException::class, 'Choose a reason from the list.');

    expect((int) $this->db->query('SELECT COUNT(*) FROM moderation_cases')->fetchColumn())->toBe(0)
        ->and((int) $this->db->query('SELECT COUNT(*) FROM content_reports')->fetchColumn())->toBe(0);
})->with(['made-up' => 'nonsense', 'retired' => 'misinformation', 'empty' => '']);

it('rejects details over the limit and strips control characters from the rest', function () {
    expect(fn () => $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', str_repeat('a', 1001)))
        ->toThrow(ReportRejectedException::class);

    $result = $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', "Line one\nLine\x07 two\x00");
    $details = $this->db->query('SELECT details FROM content_reports WHERE case_id = ?', [$result['case_id']])->fetchColumn();

    expect($details)->toBe("Line one\nLine two");
});

it('rejects a report on something that does not exist', function () {
    expect(fn () => $this->intake->file($this->reporters[0], 'post', 999999, 'spam', null))
        ->toThrow(ReportRejectedException::class);
});

it('accepts a report from a new account but does not count it toward a rule', function () {
    $newcomer = UserFactory::new($this->users)->create();

    $result = $this->intake->file($newcomer, 'post', $this->postId, 'spam', null);
    $report = $this->db->query('SELECT counts_toward_threshold, not_counted_reason FROM content_reports WHERE case_id = ?', [$result['case_id']])->fetch(\PDO::FETCH_ASSOC);

    expect($result['recorded'])->toBeTrue()
        ->and((int) $report['counts_toward_threshold'])->toBe(0)
        ->and($report['not_counted_reason'])->toBe('new_account')
        ->and((int) caseRow($this->db, $result['case_id'])['counted_report_count'])->toBe(0);
});

it('stops counting reports from someone with a record of unfounded ones', function () {
    $reporter = $this->reporters[0];
    $otherPosts = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->authorId, 'blog_id' => (int) $this->db->query('SELECT blog_id FROM posts WHERE id = ?', [$this->postId])->fetchColumn(), 'visibility' => 'public'])
        ->published()->count(3);

    foreach ($otherPosts as $postId) {
        $this->intake->file($reporter, 'post', $postId, 'spam', null);
    }
    $this->db->execute("UPDATE content_reports SET outcome = 'unfounded', outcome_at = NOW() WHERE reporter_id = ?", [$reporter]);

    $result = $this->intake->file($reporter, 'post', $this->postId, 'spam', null);
    $reason = $this->db->query('SELECT not_counted_reason FROM content_reports WHERE case_id = ? AND reporter_id = ?', [$result['case_id'], $reporter])->fetchColumn();

    expect($reason)->toBe('unfounded_history');
});

it('lets the blog team clear their badge without closing the platform case', function () {
    $result = $this->intake->file($this->reporters[0], 'comment', $this->commentId, 'harassment', null);

    $this->moderation['reports']->markBlogReviewed('comment', $this->commentId);

    $badge = (int) $this->db->query('SELECT reports_count FROM comments WHERE id = ?', [$this->commentId])->fetchColumn();
    $case = caseRow($this->db, $result['case_id']);

    expect($badge)->toBe(0)
        ->and($case['status'])->toBe('open')
        ->and((int) $case['report_count'])->toBe(1);
});

it('keeps the reports after the reported comment is removed', function () {
    $result = $this->intake->file($this->reporters[0], 'comment', $this->commentId, 'spam', null);

    $this->db->execute('DELETE FROM comments WHERE id = ?', [$this->commentId]);

    $reports = (int) $this->db->query('SELECT COUNT(*) FROM content_reports WHERE case_id = ?', [$result['case_id']])->fetchColumn();
    $case = caseRow($this->db, $result['case_id']);

    expect($reports)->toBe(1)
        ->and($case['subject_snapshot'])->toBe('A comment someone objected to');
});

it('records no author for content handed to the shared erased-accounts account', function () {
    $this->db->execute('UPDATE users SET handle = ? WHERE id = ?', [ModerationTestHelper::DELETED_USER_HANDLE, $this->authorId]);

    $caseId = (int) $this->intake->file($this->reporters[0], 'comment', $this->commentId, 'harassment', null)['case_id'];
    $case = caseRow($this->db, $caseId);

    expect($case['subject_author_id'])->toBeNull()
        ->and($case['subject_author_handle'])->toBeNull();
});

// ============================================================================
// Admin alert: filing a report can push a case over the platform-wide
// threshold, which is a separate concern from the per-item rules engine above.
// ============================================================================

it('notifies administrators once counted reports on a case cross the alert threshold', function () {
    $adminId = UserFactory::new($this->users)->withRoles(roleIds($this->db, ['administrator']))->create();

    $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', null);
    $this->intake->file($this->reporters[1], 'post', $this->postId, 'spam', null);
    $result = $this->intake->file($this->reporters[2], 'post', $this->postId, 'spam', null);

    $notified = $this->db->query(
        "SELECT data FROM notifications WHERE user_id = ? AND scope = 'admin' AND type = 'admin.report_threshold'",
        [$adminId]
    )->fetch(\PDO::FETCH_ASSOC);

    expect($notified)->not->toBeFalse();

    $data = json_decode($notified['data'], true);
    expect($data['case_id'])->toBe($result['case_id'])
        ->and($data['report_count'])->toBe(3);
});

it('does not notify administrators before the case reaches the threshold', function () {
    $adminId = UserFactory::new($this->users)->withRoles(roleIds($this->db, ['administrator']))->create();

    $this->intake->file($this->reporters[0], 'post', $this->postId, 'spam', null);
    $this->intake->file($this->reporters[1], 'post', $this->postId, 'spam', null);

    $count = (int) $this->db->query(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND scope = 'admin'",
        [$adminId]
    )->fetchColumn();

    expect($count)->toBe(0);
});

it('does not re-notify the same case again inside the cooldown window', function () {
    $adminId = UserFactory::new($this->users)->withRoles(roleIds($this->db, ['administrator']))->create();

    foreach ($this->reporters as $reporter) {
        $this->intake->file($reporter, 'post', $this->postId, 'spam', null);
    }

    $newcomer = UserFactory::new($this->users)->create();
    ModerationTestHelper::season($this->db, $newcomer);
    $this->intake->file($newcomer, 'post', $this->postId, 'spam', null);

    $count = (int) $this->db->query(
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND scope = 'admin' AND type = 'admin.report_threshold'",
        [$adminId]
    )->fetchColumn();

    expect($count)->toBe(1);
});
