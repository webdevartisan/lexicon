<?php

declare(strict_types=1);

use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Services\PublicCacheInvalidator;
use App\Services\UserSuspensionService;
use Tests\Factories\BlogFactory;
use Tests\Factories\CommentFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * Suspension cascade: what it hides, what it leaves alone, and that lifting
 * puts every piece back exactly as it was rather than to a guessed default.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->comments = new CommentModel($this->db);

    $this->service = new UserSuspensionService(
        $this->db,
        Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing(),
        $this->comments,
        $this->blogs,
    );

    $this->adminId = UserFactory::new($this->users)->admin()->create();
    $this->personId = UserFactory::new($this->users)->create();
    $this->collabId = UserFactory::new($this->users)->create();

    // A blog they write alone, a draft they write alone, and one they share.
    $this->soloId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $this->draftId = BlogFactory::new($this->blogs)->draft()->create($this->personId);
    $this->sharedId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $this->blogs->addUserToBlog($this->sharedId, $this->collabId, 'editor', $this->personId);

    $this->soloPostId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->personId, 'blog_id' => $this->soloId, 'title' => 'Solo post', 'visibility' => 'public'])
        ->published()->create();
    $this->sharedPostId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->collabId, 'blog_id' => $this->sharedId, 'title' => 'Shared post', 'visibility' => 'public'])
        ->published()->create();

    $this->ownCommentId = CommentFactory::new($this->comments)->withAttributes(['status' => 'approved'])->create($this->sharedPostId, $this->personId);
    $this->otherCommentId = CommentFactory::new($this->comments)->withAttributes(['status' => 'approved'])->create($this->sharedPostId, $this->collabId);
});

afterEach(function () {
    Mockery::close();
});

function blogRow(\Framework\Database $db, int $id): array
{
    return $db->query('SELECT status, status_before_suspension FROM blogs WHERE id = ?', [$id])->fetch();
}

function commentHiddenReason(\Framework\Database $db, int $id): ?string
{
    $reason = $db->query('SELECT hidden_reason FROM comments WHERE id = ?', [$id])->fetchColumn();

    return $reason === false ? null : $reason;
}

it('hides only the blogs they run alone and remembers what each one was', function () {
    $hidden = $this->service->suspend($this->personId, null, 'Spam', $this->adminId);

    expect($hidden['blogs'])->toBe(2)
        ->and(blogRow($this->db, $this->soloId))->toBe(['status' => 'suspended', 'status_before_suspension' => 'published'])
        ->and(blogRow($this->db, $this->draftId))->toBe(['status' => 'suspended', 'status_before_suspension' => 'draft'])
        ->and(blogRow($this->db, $this->sharedId))->toBe(['status' => 'published', 'status_before_suspension' => null]);
});

it('hides their comments and nobody else\'s, without touching the moderation status', function () {
    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);

    $status = $this->db->query('SELECT status FROM comments WHERE id = ?', [$this->ownCommentId])->fetchColumn();

    expect(commentHiddenReason($this->db, $this->ownCommentId))->toBe('author_suspended')
        ->and($status)->toBe('approved')
        ->and(commentHiddenReason($this->db, $this->otherCommentId))->toBeNull();
});

it('blocks sign-in and records who suspended them and why', function () {
    $this->service->suspend($this->personId, '2030-01-01 00:00:00', 'Cooling off', $this->adminId);

    $user = $this->db->query('SELECT is_active, suspended_until, suspension_reason, suspended_by FROM users WHERE id = ?', [$this->personId])->fetch();
    $history = $this->service->current($this->personId);

    expect((int) $user['is_active'])->toBe(0)
        ->and($user['suspended_until'])->toBe('2030-01-01 00:00:00')
        ->and($user['suspension_reason'])->toBe('Cooling off')
        ->and((int) $user['suspended_by'])->toBe($this->adminId)
        ->and($history['type'])->toBe('temporary')
        ->and((int) $history['blogs_hidden'])->toBe(2);
});

it('drops a hidden blog\'s posts from the public feed and search, but not the shared blog\'s', function () {
    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);

    $feedTitles = array_column($this->posts->getRecentPublishedWithPagination(1, 100, null)['data'], 'title');
    $searchTitles = array_column($this->posts->searchPublishedPosts('post', 1, 100, null)['data'], 'title');

    expect($feedTitles)->not->toContain('Solo post')->toContain('Shared post')
        ->and($searchTitles)->not->toContain('Solo post')->toContain('Shared post');
});

it('drops their comment from the public thread', function () {
    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);

    $ids = array_map('intval', array_column($this->comments->forPost($this->sharedPostId), 'id'));

    expect($ids)->not->toContain($this->ownCommentId)->toContain($this->otherCommentId);
});

it('puts everything back exactly as it was when lifted', function () {
    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);
    $restored = $this->service->lift($this->personId, $this->adminId);

    $user = $this->db->query('SELECT is_active, suspended_at FROM users WHERE id = ?', [$this->personId])->fetch();

    expect($restored)->toBe(['blogs' => 2, 'comments' => 1])
        ->and(blogRow($this->db, $this->soloId))->toBe(['status' => 'published', 'status_before_suspension' => null])
        ->and(blogRow($this->db, $this->draftId))->toBe(['status' => 'draft', 'status_before_suspension' => null])
        ->and(commentHiddenReason($this->db, $this->ownCommentId))->toBeNull()
        ->and((int) $user['is_active'])->toBe(1)
        ->and($user['suspended_at'])->toBeNull()
        ->and($this->service->current($this->personId))->toBeNull();
});

it('leaves a comment hidden for some other reason hidden after the lift', function () {
    $this->db->execute("UPDATE comments SET hidden_at = UTC_TIMESTAMP(), hidden_reason = 'other' WHERE id = ?", [$this->ownCommentId]);

    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);
    $this->service->lift($this->personId, $this->adminId);

    expect(commentHiddenReason($this->db, $this->ownCommentId))->toBe('other');
});

it('refuses to lift, and changes nothing, when a hidden blog has no status to go back to', function () {
    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);
    $this->db->execute('UPDATE blogs SET status_before_suspension = NULL WHERE id = ?', [$this->soloId]);

    expect(fn () => $this->service->lift($this->personId, $this->adminId))
        ->toThrow(RuntimeException::class, 'no recorded previous status');

    $user = $this->db->query('SELECT is_active, suspended_at FROM users WHERE id = ?', [$this->personId])->fetch();

    expect((int) $user['is_active'])->toBe(0)
        ->and($user['suspended_at'])->not->toBeNull()
        ->and(blogRow($this->db, $this->draftId)['status'])->toBe('suspended');
});

it('refuses to suspend an account that is already suspended', function () {
    $this->service->suspend($this->personId, null, 'Spam', $this->adminId);

    expect(fn () => $this->service->suspend($this->personId, null, 'Again', $this->adminId))
        ->toThrow(RuntimeException::class, 'already suspended');
});

it('refuses to lift an account that is not suspended', function () {
    expect(fn () => $this->service->lift($this->personId, $this->adminId))
        ->toThrow(RuntimeException::class, 'not currently suspended');
});

it('finds and lifts a temporary suspension once its time is up, and never a permanent one', function () {
    $permanentId = UserFactory::new($this->users)->create();
    $this->service->suspend($this->personId, '2030-01-01 00:00:00', 'Temp', $this->adminId);
    $this->service->suspend($permanentId, null, 'Perm', $this->adminId);

    expect($this->service->dueForLift())->toBe([]);

    $this->db->execute('UPDATE users SET suspended_until = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE id = ?', [$this->personId]);

    expect($this->service->dueForLift())->toBe([$this->personId])
        ->and($this->service->liftIfExpired($permanentId))->toBeFalse()
        ->and($this->service->liftIfExpired($this->personId))->toBeTrue();

    $lift = $this->db->query('SELECT lift_kind, lifted_by FROM user_suspensions WHERE user_id = ?', [$this->personId])->fetch();

    expect($lift)->toBe(['lift_kind' => 'automatic', 'lifted_by' => null])
        ->and(blogRow($this->db, $this->soloId)['status'])->toBe('published');
});

it('previews the same blogs and comments the cascade then hides', function () {
    $impact = $this->service->impact($this->personId);

    expect(array_column($impact['solo_blogs'], 'id'))->toEqualCanonicalizing([$this->soloId, $this->draftId])
        ->and(array_column($impact['shared_blogs'], 'id'))->toBe([$this->sharedId])
        ->and($impact['comments'])->toBe(1);

    $hidden = $this->service->suspend($this->personId, null, 'Spam', $this->adminId);

    expect($hidden)->toBe(['blogs' => count($impact['solo_blogs']), 'comments' => $impact['comments']]);
});
