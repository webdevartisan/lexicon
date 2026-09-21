<?php

declare(strict_types=1);

use App\Interfaces\UploadServiceInterface;
use App\Models\AccountErasureRecordModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\CommentModel;
use App\Models\ModerationCaseModel;
use App\Models\PendingErasureModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\AccountErasureSchedulerService;
use App\Services\AccountErasureService;
use App\Services\BlogDeletionService;
use App\Services\MediaUsageResolver;
use App\Services\PublicCacheInvalidator;
use Tests\Factories\BlogFactory;
use Tests\Factories\PostFactory;
use Tests\Factories\UserFactory;

/**
 * The upload service is a mock: storage/uploads/ is shared with development, so
 * the real one would delete real files.
 */
beforeEach(function () {
    $this->users = new UserModel($this->db);
    $this->blogs = new BlogModel($this->db);
    $this->posts = new PostModel($this->db);
    $this->comments = new CommentModel($this->db);
    $this->pending = new PendingErasureModel($this->db);

    $this->db->execute(
        "INSERT INTO users (handle, email, password, display_name_cached, is_active)
         VALUES ('deleted-user', 'deleted-user@lexicon.invalid', '', 'Deleted user', 0)"
    );

    $uploader = Mockery::mock(UploadServiceInterface::class)->shouldIgnoreMissing();
    $uploader->shouldReceive('blogUploadsBy')->andReturn([])->byDefault();
    $cacheInvalidator = Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing();

    $this->erasure = new AccountErasureService(
        $this->db,
        $this->users,
        new BlogDeletionService(
            $this->blogs,
            $this->posts,
            new BlogSettingsModel($this->db),
            new UserPreferencesModel($this->db),
            $uploader,
            $cacheInvalidator
        ),
        $uploader,
        new MediaUsageResolver($this->db),
        $cacheInvalidator,
        new AccountErasureRecordModel($this->db),
        'deleted-user'
    );

    $this->personId = UserFactory::new($this->users)
        ->withAttributes(['handle' => 'leaving-person', 'email' => 'leaving@example.test'])
        ->create();
});

afterEach(function () {
    Mockery::close();
});

function makeScheduler(Framework\Database $db, AccountErasureService $erasure, UserModel $users, PendingErasureModel $pending, int $graceDays): AccountErasureSchedulerService
{
    return new AccountErasureSchedulerService($users, $pending, $erasure, $graceDays);
}

test('scheduling deactivates the account without erasing it yet', function () {
    $scheduler = makeScheduler($this->db, $this->erasure, $this->users, $this->pending, 7);

    $scheduler->schedule($this->personId, $this->personId, '203.0.113.5');

    $user = $this->users->find($this->personId);
    $row = $this->db->query('SELECT * FROM pending_erasures WHERE user_id = ?', [$this->personId])->fetch(PDO::FETCH_ASSOC);

    expect($user)->not->toBeNull()
        ->and((int) $user['is_active'])->toBe(0)
        ->and($row)->not->toBeFalse()
        ->and($row['erased_by_ip'])->toBe('203.0.113.5');
});

test('scheduling refuses when the account is already blocked', function () {
    $blogId = BlogFactory::new($this->blogs)->published()->create($this->personId);
    $this->blogs->addUserToBlog($blogId, UserFactory::new($this->users)->create(), 'author', $this->personId);

    $scheduler = makeScheduler($this->db, $this->erasure, $this->users, $this->pending, 7);

    expect(fn () => $scheduler->schedule($this->personId, $this->personId, null))
        ->toThrow(RuntimeException::class);

    expect((int) $this->users->find($this->personId)['is_active'])->toBe(1);
});

test('processDue erases accounts whose grace period has passed', function () {
    $postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->personId, 'blog_id' => BlogFactory::new($this->blogs)->published()->create(UserFactory::new($this->users)->create())])
        ->published()
        ->create();

    $scheduler = makeScheduler($this->db, $this->erasure, $this->users, $this->pending, 0);
    $scheduler->schedule($this->personId, $this->personId, null);

    // 0-day grace still schedules for "now", not the past, so push it back to make it due.
    $this->db->execute('UPDATE pending_erasures SET scheduled_for = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE user_id = ?', [$this->personId]);

    $counts = $scheduler->processDue();

    expect($counts)->toBe(['erased' => 1, 'cancelled' => 0, 'still_blocked' => 0])
        ->and($this->users->find($this->personId))->toBeNull()
        ->and($this->posts->find($postId))->toBeNull()
        ->and($this->db->query('SELECT COUNT(*) FROM pending_erasures WHERE user_id = ?', [$this->personId])->fetchColumn())->toBe(0);
});

test('processDue leaves accounts inside their grace period alone', function () {
    $scheduler = makeScheduler($this->db, $this->erasure, $this->users, $this->pending, 7);
    $scheduler->schedule($this->personId, $this->personId, null);

    $counts = $scheduler->processDue();

    expect($counts)->toBe(['erased' => 0, 'cancelled' => 0, 'still_blocked' => 0])
        ->and($this->users->find($this->personId))->not->toBeNull();
});

test('processDue cancels the erasure if an administrator reactivated the account', function () {
    $scheduler = makeScheduler($this->db, $this->erasure, $this->users, $this->pending, 0);
    $scheduler->schedule($this->personId, $this->personId, null);
    $this->db->execute('UPDATE pending_erasures SET scheduled_for = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE user_id = ?', [$this->personId]);

    $this->users->update($this->personId, ['is_active' => 1]);

    $counts = $scheduler->processDue();

    expect($counts)->toBe(['erased' => 0, 'cancelled' => 1, 'still_blocked' => 0])
        ->and($this->users->find($this->personId))->not->toBeNull()
        ->and($this->db->query('SELECT COUNT(*) FROM pending_erasures WHERE user_id = ?', [$this->personId])->fetchColumn())->toBe(0);
});

test('processDue leaves it queued when a report landed during the grace period', function () {
    $postId = PostFactory::new($this->posts)
        ->withAttributes(['author_id' => $this->personId, 'blog_id' => BlogFactory::new($this->blogs)->published()->create(UserFactory::new($this->users)->create())])
        ->published()
        ->create();

    $scheduler = makeScheduler($this->db, $this->erasure, $this->users, $this->pending, 0);
    $scheduler->schedule($this->personId, $this->personId, null);
    $this->db->execute('UPDATE pending_erasures SET scheduled_for = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE) WHERE user_id = ?', [$this->personId]);

    (new ModerationCaseModel($this->db))->openFor('post', $postId, $this->personId, null, null, null);

    $counts = $scheduler->processDue();

    expect($counts)->toBe(['erased' => 0, 'cancelled' => 0, 'still_blocked' => 1])
        ->and($this->users->find($this->personId))->not->toBeNull()
        ->and($this->db->query('SELECT COUNT(*) FROM pending_erasures WHERE user_id = ?', [$this->personId])->fetchColumn())->toBe(1);
});
