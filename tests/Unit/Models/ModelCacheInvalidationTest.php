<?php

declare(strict_types=1);

use App\Models\BlogSettingsModel;
use App\Models\CategoryModel;
use App\Models\CommentModel;
use App\Models\TagModel;
use App\Services\BlogCacheInvalidator;
use App\Services\CommentCacheInvalidator;
use App\Services\PostCacheInvalidator;
use Framework\Core\App;
use Framework\Database;

beforeEach(function () {
    $this->db = Mockery::mock(Database::class);
    $this->swapped = [];
});

afterEach(function () {
    foreach ($this->swapped as $class => $original) {
        App::container()->set($class, fn () => $original);
    }
});

/**
 * Put a mock invalidator in the container for the model to pick up, keeping
 * the real one so afterEach can put it back.
 *
 * @param  class-string  $class
 */
function mockInvalidator(object $test, string $class): Mockery\MockInterface
{
    $test->swapped[$class] ??= App::container()->get($class);

    $mock = Mockery::mock($class);
    App::container()->set($class, fn () => $mock);

    return $mock;
}

/**
 * Stub the find() lookup a model makes before writing, returning the given row.
 *
 * @param  array<string, mixed>|false  $row
 */
function stubFind(Mockery\MockInterface $db, array|false $row): void
{
    $stmt = Mockery::mock(PDOStatement::class);
    $stmt->shouldReceive('fetch')->andReturn($row);
    $db->shouldReceive('query')->andReturn($stmt);
}

test('attaching a new tag forgets the post\'s tag list', function () {
    mockInvalidator($this, PostCacheInvalidator::class)->shouldReceive('forgetTags')->once()->with(5);
    $this->db->shouldReceive('execute')->once()->andReturn(1);

    expect((new TagModel($this->db))->attachToPost(5, 9))->toBeTrue();
});

test('attaching a tag the post already has leaves the cache alone', function () {
    mockInvalidator($this, PostCacheInvalidator::class)->shouldNotReceive('forgetTags');
    $this->db->shouldReceive('execute')->once()->andReturn(0);

    expect((new TagModel($this->db))->attachToPost(5, 9))->toBeTrue();
});

test('detaching a tag forgets the post\'s tag list only when a row went', function (int $affected, int $forgets) {
    mockInvalidator($this, PostCacheInvalidator::class)->shouldReceive('forgetTags')->times($forgets)->with(5);
    $this->db->shouldReceive('execute')->once()->andReturn($affected);

    expect((new TagModel($this->db))->detachFromPost(5, 9))->toBe($affected > 0);
})->with([
    'removed' => [1, 1],
    'was not attached' => [0, 0],
]);

test('saving blog settings forgets the cached row only when it changed', function (int $affected, int $forgets) {
    mockInvalidator($this, BlogCacheInvalidator::class)->shouldReceive('forgetSettings')->times($forgets)->with(3);
    $this->db->shouldReceive('execute')->once()->andReturn($affected);

    expect((new BlogSettingsModel($this->db))->updateForBlog(3, ['tagline' => 'Notes']))->toBe($affected > 0);
})->with([
    'changed' => [1, 1],
    'unchanged' => [0, 0],
]);

test('editing a category tells the blog cache which blog it belongs to', function () {
    mockInvalidator($this, BlogCacheInvalidator::class)->shouldReceive('categoryChanged')->once()->with(2);
    stubFind($this->db, ['id' => 4, 'blog_id' => 2]);
    $this->db->shouldReceive('execute')->once()->andReturn(1);

    expect((new CategoryModel($this->db))->update(4, ['name' => 'Field notes']))->toBeTrue();
});

test('deleting a category tells the blog cache, and a miss leaves it alone', function (int $affected, int $calls) {
    mockInvalidator($this, BlogCacheInvalidator::class)->shouldReceive('categoryRemoved')->times($calls)->with(2);
    stubFind($this->db, ['id' => 4, 'blog_id' => 2]);
    $this->db->shouldReceive('execute')->once()->andReturn($affected);

    expect((new CategoryModel($this->db))->delete(4))->toBe($affected > 0);
})->with([
    'deleted' => [1, 1],
    'already gone' => [0, 0],
]);

test('a comment status change forgets the thread of its post', function () {
    mockInvalidator($this, CommentCacheInvalidator::class)->shouldReceive('forgetThread')->once()->with(30);
    stubFind($this->db, ['post_id' => 30]);
    $this->db->shouldReceive('execute')->once()->andReturn(1);

    expect((new CommentModel($this->db))->updateStatus(8, 'approved'))->toBeTrue();
});
