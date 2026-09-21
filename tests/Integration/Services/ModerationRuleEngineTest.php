<?php

declare(strict_types=1);

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
 * What happens when reports reach a category's threshold, and every
 * safeguard that stands between a pile of reports and an automatic action.
 *
 * Most tests zero the burst window so that reports filed a few milliseconds
 * apart do not look like a coordinated campaign; the burst test keeps it.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->comments = new CommentModel($this->db);
    $this->posts = new PostModel($this->db);

    $this->authorId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->authorId);
    $this->postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->authorId, 'blog_id' => $blogId, 'visibility' => 'public'])
        ->published()->create();
    $this->commentId = CommentFactory::new($this->comments)
        ->withAttributes(['status' => 'approved'])
        ->create($this->postId, $this->authorId);

    $this->reporters = [];
    foreach (range(1, 4) as $n) {
        $id = UserFactory::new($this->users)->create();
        ModerationTestHelper::season($this->db, $id);
        $this->reporters[] = $id;
    }

    $this->categories = ModerationTestHelper::snapshotCategories($this->db);
});

afterEach(function () {
    ModerationTestHelper::restoreCategories($this->db, $this->categories);
    Mockery::close();
});

/**
 * File one report per reporter and return the case id.
 */
function reportTimes(array $moderation, array $reporters, string $type, int $subjectId, string $category): int
{
    $caseId = 0;

    foreach ($reporters as $reporterId) {
        $caseId = (int) $moderation['intake']->file($reporterId, $type, $subjectId, $category, null)['case_id'];
    }

    return $caseId;
}

function noBurst(): array
{
    return ['moderation.burst_window_minutes' => '0'];
}

it('hides a comment on its own once spam reaches the threshold, and labels it a system action', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'spam');

    $comment = $this->db->query('SELECT hidden_at, hidden_reason FROM comments WHERE id = ?', [$this->commentId])->fetch(\PDO::FETCH_ASSOC);
    $case = $moderation['cases']->findById($caseId);
    $hidden = array_values(array_filter(ModerationTestHelper::caseAudit($this->db, $caseId), fn ($row) => $row['action'] === 'moderation.content_hidden'));

    expect($comment['hidden_at'])->not->toBeNull()
        ->and($comment['hidden_reason'])->toBe('moderation')
        ->and($case['content_status'])->toBe('hidden')
        ->and($case['status'])->toBe('open')
        ->and($moderation['cases']->firedRules($case))->toBe(['spam'])
        ->and($hidden)->toHaveCount(1)
        ->and($hidden[0]['user_id'])->toBeNull()
        ->and($hidden[0]['details']['actor_type'])->toBe('system')
        ->and($hidden[0]['details']['rule'])->toBe('spam');
});

it('does nothing below the threshold', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 2), 'comment', $this->commentId, 'spam');

    expect($moderation['cases']->findById($caseId)['content_status'])->toBe('visible')
        ->and(ModerationTestHelper::caseAudit($this->db, $caseId))->toBe([]);
});

it('hides a post by moving it to moderated and remembering its status', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    reportTimes($moderation, array_slice($this->reporters, 0, 3), 'post', $this->postId, 'spam');

    $post = $this->db->query('SELECT status, status_before_moderation FROM posts WHERE id = ?', [$this->postId])->fetch(\PDO::FETCH_ASSOC);

    expect($post)->toBe(['status' => 'moderated', 'status_before_moderation' => 'published']);
});

it('does not count reports from new accounts toward the threshold', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());
    $newcomers = [UserFactory::new($this->users)->create(), UserFactory::new($this->users)->create()];

    $caseId = reportTimes($moderation, [$this->reporters[0], ...$newcomers], 'comment', $this->commentId, 'spam');

    expect($moderation['cases']->findById($caseId)['content_status'])->toBe('visible');
});

it('fires a rule once per case, not again on every later report', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, $this->reporters, 'comment', $this->commentId, 'spam');

    $hides = array_filter(ModerationTestHelper::caseAudit($this->db, $caseId), fn ($row) => str_starts_with($row['action'], 'moderation.'));

    expect($hides)->toHaveCount(1);
});

it('proposes a suspension for harassment and suspends nobody', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'harassment');

    $case = $moderation['cases']->findById($caseId);
    $audit = ModerationTestHelper::caseAudit($this->db, $caseId);

    expect($case['status'])->toBe('escalated')
        ->and($case['pending_action'])->toBe('suspend')
        ->and($case['pending_rule'])->toBe('harassment')
        ->and($case['pending_note'])->not->toBeEmpty()
        ->and($moderation['suspensions']->current($this->authorId))->toBeNull()
        ->and($audit[0]['action'])->toBe('moderation.rule_proposed')
        ->and($audit[0]['details']['held_because'])->toBe('confirmation_required');
});

it('holds a high severity category set to automatic until an administrator accepts it', function () {
    $this->db->execute("UPDATE moderation_categories SET execution = 'automatic' WHERE slug = 'harassment'");
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'harassment');

    $audit = ModerationTestHelper::caseAudit($this->db, $caseId);

    expect($moderation['suspensions']->current($this->authorId))->toBeNull()
        ->and($audit[0]['details']['held_because'])->toBe('automation_not_acknowledged');
});

it('suspends through the regular cascade once automation is accepted, recorded as a rule and not a person', function () {
    $this->db->execute(
        "UPDATE moderation_categories SET execution = 'automatic', automation_acknowledged_at = NOW() WHERE slug = 'harassment'"
    );
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'harassment');

    $suspension = $moderation['suspensions']->current($this->authorId);
    $user = $this->db->query('SELECT is_active, suspended_by FROM users WHERE id = ?', [$this->authorId])->fetch(\PDO::FETCH_ASSOC);
    $suspended = array_values(array_filter(ModerationTestHelper::caseAudit($this->db, $caseId), fn ($row) => $row['action'] === 'moderation.author_suspended'));

    expect($suspension)->not->toBeNull()
        ->and($suspension['source'])->toBe('rule')
        ->and($suspension['rule'])->toBe('harassment')
        ->and((int) $suspension['case_id'])->toBe($caseId)
        ->and($suspension['suspended_by'])->toBeNull()
        ->and($suspension['type'])->toBe('temporary')
        ->and((int) $user['is_active'])->toBe(0)
        ->and($suspended)->toHaveCount(1)
        ->and($suspended[0]['details']['actor_type'])->toBe('system');
});

it('never lets illegal content act on its own, even when the row says automatic', function () {
    $this->db->execute("UPDATE moderation_categories SET execution = 'automatic', auto_action = 'hide_content' WHERE slug = 'illegal'");
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, [$this->reporters[0]], 'comment', $this->commentId, 'illegal');

    $case = $moderation['cases']->findById($caseId);

    expect($case['content_status'])->toBe('visible')
        ->and($case['status'])->toBe('escalated')
        ->and(ModerationTestHelper::caseAudit($this->db, $caseId)[0]['details']['held_because'])->toBe('critical');
});

it('escalates illegal content to a person on the first report', function () {
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, [$this->reporters[0]], 'post', $this->postId, 'illegal');

    $case = $moderation['cases']->findById($caseId);

    expect($case['status'])->toBe('escalated')
        ->and($case['content_status'])->toBe('visible')
        ->and(ModerationTestHelper::caseAudit($this->db, $caseId)[0]['action'])->toBe('moderation.rule_escalated');
});

it('holds an automatic action when the reports arrive in a burst', function () {
    $moderation = ModerationTestHelper::services($this->db);

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'spam');

    $case = $moderation['cases']->findById($caseId);

    expect($case['content_status'])->toBe('visible')
        ->and($case['pending_action'])->toBe('hide_content')
        ->and(ModerationTestHelper::caseAudit($this->db, $caseId)[0]['details']['held_because'])->toBe('report_burst');
});

it('never acts on its own against a staff account', function () {
    $staffId = UserFactory::new($this->users)->admin()->create();
    $staffComment = CommentFactory::new($this->comments)->withAttributes(['status' => 'approved'])->create($this->postId, $staffId);
    $moderation = ModerationTestHelper::services($this->db, noBurst());

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $staffComment, 'spam');

    expect($moderation['cases']->findById($caseId)['content_status'])->toBe('visible')
        ->and(ModerationTestHelper::caseAudit($this->db, $caseId)[0]['details']['held_because'])->toBe('protected_account');
});

it('will not suspend on its own again inside the cooldown', function () {
    $this->db->execute(
        "UPDATE moderation_categories SET execution = 'automatic', automation_acknowledged_at = NOW() WHERE slug = 'harassment'"
    );
    $moderation = ModerationTestHelper::services($this->db, noBurst());
    $this->db->execute(
        "INSERT INTO user_suspensions (user_id, type, source, rule, suspended_at, expires_at, lifted_at, lift_kind)
         VALUES (?, 'temporary', 'rule', 'harassment', UTC_TIMESTAMP() - INTERVAL 5 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY, 'automatic')",
        [$this->authorId]
    );

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'harassment');

    expect($moderation['suspensions']->current($this->authorId))->toBeNull()
        ->and(ModerationTestHelper::caseAudit($this->db, $caseId)[0]['details']['held_because'])->toBe('suspension_cooldown');
});

it('records a failed automatic action on the case and in the audit log instead of claiming it happened', function () {
    $actions = ModerationTestHelper::actions($this->db, partial: true);
    $actions->shouldReceive('hideContent')->andThrow(new RuntimeException('Disk full'));
    $moderation = ModerationTestHelper::services($this->db, noBurst(), $actions);

    $caseId = reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'spam');

    $case = $moderation['cases']->findById($caseId);
    $audit = ModerationTestHelper::caseAudit($this->db, $caseId);
    $hidden = $this->db->query('SELECT hidden_at FROM comments WHERE id = ?', [$this->commentId])->fetchColumn();

    expect($hidden)->toBeNull()
        ->and($case['content_status'])->toBe('visible')
        ->and($case['last_error'])->toBe('Disk full')
        ->and($case['pending_action'])->toBe('hide_content')
        ->and($case['status'])->toBe('escalated')
        ->and(array_column($audit, 'action'))->toBe(['moderation.action_failed'])
        ->and($audit[0]['details']['error'])->toBe('Disk full');
});

it('keeps a stronger proposal in place when a weaker rule is held too', function () {
    $moderation = ModerationTestHelper::services($this->db);
    $spamReporters = [];
    foreach (range(1, 3) as $n) {
        $id = UserFactory::new($this->users)->create();
        ModerationTestHelper::season($this->db, $id);
        $spamReporters[] = $id;
    }

    reportTimes($moderation, array_slice($this->reporters, 0, 3), 'comment', $this->commentId, 'harassment');
    $caseId = reportTimes($moderation, $spamReporters, 'comment', $this->commentId, 'spam');

    $case = $moderation['cases']->findById($caseId);
    $spam = array_values(array_filter(ModerationTestHelper::caseAudit($this->db, $caseId), fn ($row) => $row['details']['rule'] === 'spam'));

    expect($case['pending_rule'])->toBe('harassment')
        ->and($case['pending_action'])->toBe('suspend')
        ->and($spam[0]['details']['behind'])->toBe('harassment');
});
