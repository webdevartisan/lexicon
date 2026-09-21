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
 * A moderator's decisions on a case: each one settles the reports, closes the
 * case and leaves the item in the state the decision promises.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);

    $this->authorId = UserFactory::new($this->users)->create();
    $this->moderatorId = UserFactory::new($this->users)->create();
    $blogId = BlogFactory::new(new BlogModel($this->db))->published()->create($this->authorId);
    $postId = PostFactory::new(new PostModel($this->db))
        ->withAttributes(['author_id' => $this->authorId, 'blog_id' => $blogId, 'visibility' => 'public'])
        ->published()->create();
    $this->commentId = CommentFactory::new(new CommentModel($this->db))
        ->withAttributes(['status' => 'approved'])
        ->create($postId, $this->authorId);

    $this->moderation = ModerationTestHelper::services($this->db, ['moderation.burst_window_minutes' => '0']);

    $this->reporters = [];
    foreach (range(1, 2) as $n) {
        $id = UserFactory::new($this->users)->create();
        ModerationTestHelper::season($this->db, $id);
        $this->reporters[] = $id;
    }

    // Harassment below its threshold, so no rule acts before the moderator does
    foreach ($this->reporters as $reporterId) {
        $this->caseId = (int) $this->moderation['intake']
            ->file($reporterId, 'comment', $this->commentId, 'harassment', null)['case_id'];
    }
});

afterEach(function () {
    Mockery::close();
});

function caseDetail(array $moderation, int $caseId): array
{
    return $moderation['cases']->findDetail($caseId);
}

function moderatedCommentRow(\Framework\Database $db, int $id): array
{
    return $db->query('SELECT hidden_at, hidden_reason, reports_count FROM comments WHERE id = ?', [$id])->fetch(\PDO::FETCH_ASSOC);
}

it('dismisses a case: restores the hidden comment, marks the chosen reports unfounded and closes it', function () {
    $this->moderation['actions']->hideContent(caseDetail($this->moderation, $this->caseId), $this->moderatorId, 'Checking it');
    $reports = $this->moderation['reports']->forCase($this->caseId);

    $restored = $this->moderation['moderation']->dismiss(
        caseDetail($this->moderation, $this->caseId),
        $this->moderatorId,
        'Robust disagreement, not harassment.',
        [(int) $reports[0]['id']]
    );

    $case = $this->moderation['cases']->findById($this->caseId);
    $outcomes = array_column($this->moderation['reports']->forCase($this->caseId), 'outcome', 'id');
    $comment = moderatedCommentRow($this->db, $this->commentId);

    expect($restored)->toBeTrue()
        ->and($comment['hidden_at'])->toBeNull()
        ->and((int) $comment['reports_count'])->toBe(0)
        ->and($case['status'])->toBe('resolved')
        ->and($case['resolution'])->toBe('dismissed')
        ->and($case['open_key'])->toBeNull()
        ->and($case['content_status'])->toBe('visible')
        ->and($outcomes[(int) $reports[0]['id']])->toBe('unfounded')
        ->and($outcomes[(int) $reports[1]['id']])->toBe('dismissed');
});

it('upholds a case: hides the comment, warns the author in app and by queued email', function () {
    $this->moderation['moderation']->uphold(
        caseDetail($this->moderation, $this->caseId),
        $this->moderatorId,
        'Targeted insults.',
        true,
        'Please keep replies about the post, not the person.'
    );

    $case = $this->moderation['cases']->findById($this->caseId);
    $notification = $this->db->query('SELECT type, data FROM notifications WHERE user_id = ?', [$this->authorId])->fetch(\PDO::FETCH_ASSOC);
    $mail = $this->db->query(
        "SELECT to_email, body_html FROM mail_queue WHERE related_type = 'moderation_case' AND related_id = ?",
        [$this->caseId]
    )->fetch(\PDO::FETCH_ASSOC);
    $author = $this->users->findById($this->authorId);
    $actions = array_column(ModerationTestHelper::caseAudit($this->db, $this->caseId), 'action');

    expect(moderatedCommentRow($this->db, $this->commentId)['hidden_reason'])->toBe('moderation')
        ->and($case['resolution'])->toBe('upheld')
        ->and($notification['type'])->toBe('moderation.warning')
        ->and(json_decode($notification['data'], true)['message'])->toBe('Please keep replies about the post, not the person.')
        ->and($mail['to_email'])->toBe($author['email'])
        ->and($mail['body_html'])->toContain('Please keep replies about the post')
        ->and(array_unique(array_column($this->moderation['reports']->forCase($this->caseId), 'outcome')))->toBe(['upheld'])
        ->and($actions)->toContain('moderation.content_hidden', 'moderation.author_warned', 'moderation.case_upheld');
});

it('rolls the whole uphold back when the warning cannot be sent', function () {
    $this->db->execute('UPDATE moderation_cases SET subject_author_id = NULL WHERE id = ?', [$this->caseId]);

    expect(fn () => $this->moderation['moderation']->uphold(
        caseDetail($this->moderation, $this->caseId),
        $this->moderatorId,
        'Targeted insults.',
        true,
        'Please keep replies about the post, not the person.'
    ))->toThrow(RuntimeException::class, 'no account behind it to warn');

    expect(moderatedCommentRow($this->db, $this->commentId)['hidden_at'])->toBeNull()
        ->and($this->moderation['cases']->findById($this->caseId)['status'])->toBe('open');
});

it('suspends the author through the case, recorded as a moderator suspension tied to the case', function () {
    $hidden = $this->moderation['moderation']->suspendAuthor(
        caseDetail($this->moderation, $this->caseId),
        $this->moderatorId,
        null,
        'Repeated harassment.'
    );

    $suspension = $this->db->query('SELECT source, case_id, suspended_by FROM user_suspensions WHERE user_id = ?', [$this->authorId])->fetch(\PDO::FETCH_ASSOC);

    expect($hidden)->toHaveKeys(['blogs', 'comments'])
        ->and($suspension['source'])->toBe('moderator')
        ->and((int) $suspension['case_id'])->toBe($this->caseId)
        ->and((int) $suspension['suspended_by'])->toBe($this->moderatorId)
        ->and($this->moderation['cases']->findById($this->caseId)['resolution'])->toBe('upheld');
});

it('escalates with the note on the record', function () {
    $this->moderation['moderation']->escalate(caseDetail($this->moderation, $this->caseId), $this->moderatorId, 'Needs someone who can suspend.');

    $audit = ModerationTestHelper::caseAudit($this->db, $this->caseId);
    $escalated = array_values(array_filter($audit, fn ($row) => $row['action'] === 'moderation.escalated'));

    expect($this->moderation['cases']->findById($this->caseId)['status'])->toBe('escalated')
        ->and($escalated[0]['user_id'])->toBe($this->moderatorId)
        ->and($escalated[0]['details']['note'])->toBe('Needs someone who can suspend.');
});

it('refuses a decision on a case that is already closed', function () {
    $stale = caseDetail($this->moderation, $this->caseId);
    $this->moderation['moderation']->dismiss($stale, $this->moderatorId, 'Not a problem.', []);

    expect(fn () => $this->moderation['moderation']->uphold(
        caseDetail($this->moderation, $this->caseId), $this->moderatorId, 'Changed my mind.', true, null
    ))->toThrow(RuntimeException::class, 'already closed');

    // A second moderator acting on the page they loaded before the first one closed it
    expect(fn () => $this->moderation['moderation']->dismiss($stale, $this->moderatorId, 'Not a problem.', []))
        ->toThrow(RuntimeException::class, 'Someone else closed this case');
});

it('opens a fresh case when the item is reported again after a decision', function () {
    $this->moderation['moderation']->dismiss(caseDetail($this->moderation, $this->caseId), $this->moderatorId, 'Not a problem.', []);

    $late = UserFactory::new($this->users)->create();
    ModerationTestHelper::season($this->db, $late);
    $newCaseId = (int) $this->moderation['intake']->file($late, 'comment', $this->commentId, 'spam', null)['case_id'];

    expect($newCaseId)->not->toBe($this->caseId)
        ->and($this->moderation['cases']->findById($newCaseId)['status'])->toBe('open');
});

it('returns a comment to the suspension, not to readers, when its author is suspended at dismissal', function () {
    $this->moderation['actions']->hideContent(caseDetail($this->moderation, $this->caseId), $this->moderatorId, 'Checking it');
    $this->moderation['suspensions']->suspend($this->authorId, null, 'Unrelated spam run.', $this->moderatorId);

    $this->moderation['moderation']->dismiss(caseDetail($this->moderation, $this->caseId), $this->moderatorId, 'This one was fine.', []);

    $comment = moderatedCommentRow($this->db, $this->commentId);

    expect($comment['hidden_at'])->not->toBeNull()
        ->and($comment['hidden_reason'])->toBe(CommentModel::HIDDEN_BY_SUSPENSION);
});
