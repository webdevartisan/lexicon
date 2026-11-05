<?php

declare(strict_types=1);

use App\Interfaces\UploadServiceInterface;
use App\Models\AccountErasureRecordModel;
use App\Models\AnalyticsEventModel;
use App\Models\AnalyticsSaltModel;
use App\Models\AnalyticsVisitModel;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\ModuleModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Services\AccountErasureService;
use App\Services\Analytics\VisitorIdentity;
use App\Services\BlogDeletionService;
use App\Services\MediaUsageResolver;
use App\Services\PublicCacheInvalidator;
use Framework\Modules\ModuleDuties;
use Tests\Factories\BlogFactory;
use Tests\Factories\UserFactory;
use Tests\Fixtures\Modules\SampleExtra\SampleExtraModule;
use Tests\Helpers\AnalyticsFixture;
use Tests\Helpers\DatabaseHelper;
use Tests\Helpers\ModuleFixtures;

/**
 * The fixture table has no foreign keys on purpose: a cascade would remove the
 * rows on its own, and these tests are about the module erasers being called.
 */
function notesFor(Framework\Database $db, string $column, int $id): int
{
    return (int) $db->query("SELECT COUNT(*) FROM sample_extra_notes WHERE {$column} = ?", [$id])->fetchColumn();
}

beforeEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    $this->duties = ModuleFixtures::installSampleExtra($this->db);
    SampleExtraModule::$failErase = false;

    $users = new UserModel($this->db);
    $uploader = Mockery::mock(UploadServiceInterface::class)->shouldIgnoreMissing();
    $cache = Mockery::mock(PublicCacheInvalidator::class)->shouldIgnoreMissing();
    $this->deletion = new BlogDeletionService(
        new BlogModel($this->db),
        new PostModel($this->db),
        new BlogSettingsModel($this->db),
        new UserPreferencesModel($this->db),
        $uploader,
        $cache,
        new AnalyticsEventModel($this->db),
        new AnalyticsVisitModel($this->db),
        AnalyticsFixture::rollups($this->db),
        $this->duties
    );
    $this->erasure = new AccountErasureService(
        $this->db,
        $users,
        $this->deletion,
        $uploader,
        new MediaUsageResolver($this->db),
        $cache,
        new AccountErasureRecordModel($this->db),
        new AnalyticsEventModel($this->db),
        new AnalyticsVisitModel($this->db),
        new VisitorIdentity(new AnalyticsSaltModel($this->db), 'test-key'),
        'deleted-user',
        $this->duties
    );

    DatabaseHelper::deletedUser($this->db);
    $this->ownerId = UserFactory::new($users)->create();
    $this->blogId = BlogFactory::new(new BlogModel($this->db))->create($this->ownerId);
});

afterEach(function () {
    ModuleFixtures::dropSampleExtra($this->db);
    ModuleFixtures::cleanUp();
});

test('deleting a blog removes module rows even when the module is switched off for it', function () {
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [$this->blogId, 'trip']);

    $this->deletion->deleteBlog($this->blogId, $this->ownerId);

    expect(notesFor($this->db, 'blog_id', $this->blogId))->toBe(0);
});

test('a paused module still has its blog data erased', function () {
    (new ModuleModel($this->db))->pause('sample-extra', 'testing');
    $this->db->execute('INSERT INTO sample_extra_notes (blog_id, note) VALUES (?, ?)', [$this->blogId, 'trip']);

    $this->deletion->deleteBlog($this->blogId, $this->ownerId);

    expect(notesFor($this->db, 'blog_id', $this->blogId))->toBe(0);
});

test('erasing an account removes module rows tied to the user', function () {
    $readerId = UserFactory::new(new UserModel($this->db))->create();
    $this->db->execute(
        'INSERT INTO sample_extra_notes (blog_id, user_id, note) VALUES (?, ?, ?)',
        [$this->blogId, $readerId, 'mine']
    );

    $this->erasure->erase($readerId);

    expect(notesFor($this->db, 'user_id', $readerId))->toBe(0)
        ->and(notesFor($this->db, 'blog_id', $this->blogId))->toBe(0);
});

test('a module eraser that throws rolls the blog deletion back', function () {
    SampleExtraModule::$failErase = true;

    expect(fn () => $this->deletion->deleteBlog($this->blogId, $this->ownerId))->toThrow(RuntimeException::class);
    expect((new BlogModel($this->db))->getBlog($this->blogId))->not->toBeFalse();
});

test('with no modules present the duties do nothing', function () {
    $none = new ModuleDuties(ModuleFixtures::registry([]), ModuleFixtures::instances($this->db));

    $none->eraseBlog($this->blogId);
    $none->eraseUser($this->ownerId);

    expect(true)->toBeTrue();
});
