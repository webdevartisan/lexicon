<?php

declare(strict_types=1);

/**
 * Unit tests for CommentModel thread assembly.
 *
 * Mocks the database so tree building, ordering and the depth cap are verified
 * in isolation. Assembly renders whatever depth the rows describe; the cap is
 * enforced on the way in, by parentForReply.
 */

use App\Models\CommentModel;
use Framework\Database;

beforeEach(function () {
    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    $this->model = new CommentModel($this->dbMock);
});

/** @param array<int, array<string, mixed>> $rows */
function stubThreadRows(object $test, array $rows): void
{
    $test->dbMock->shouldReceive('query')->once()->andReturn($test->stmtMock);
    $test->stmtMock->shouldReceive('fetchAll')->once()->andReturn($rows);
}

function row(int $id, ?int $parent, string $created, int $up = 0, int $down = 0, ?string $pinned = null): array
{
    return [
        'id' => $id,
        'post_id' => 5,
        'parent_comment_id' => $parent,
        'content' => 'c'.$id,
        'created_at' => $created,
        'upvotes' => $up,
        'downvotes' => $down,
        'pinned_at' => $pinned,
        'user_name' => 'u'.$id,
    ];
}

/** Flatten a built thread to "id:depth" pairs in render order. */
function flatten(array $nodes, int $depth = 0): array
{
    $out = [];

    foreach ($nodes as $node) {
        $out[] = $node['id'].':'.$depth;
        $out = array_merge($out, flatten($node['replies'] ?? [], $depth + 1));
    }

    return $out;
}

test('parentForReply leaves a shallow target alone', function () {
    // Two lookups walking up: the target's parent, then the root's null.
    $this->dbMock->shouldReceive('query')->twice()->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetch')->twice()->andReturn(
        ['parent_comment_id' => 1],
        ['parent_comment_id' => null]
    );

    expect($this->model->parentForReply(2))->toBe(2);
});

test('parentForReply makes a reply at the cap a sibling of its target', function () {
    // Target sits at depth 3: 3 -> 2 -> 1 -> root.
    $this->dbMock->shouldReceive('query')->times(4)->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetch')->times(4)->andReturn(
        ['parent_comment_id' => 3],
        ['parent_comment_id' => 2],
        ['parent_comment_id' => 1],
        ['parent_comment_id' => null]
    );

    // Hangs off the target's own parent, so it lands back at the cap.
    expect($this->model->parentForReply(4))->toBe(3);
});

test('parentForReply stops walking a parent cycle instead of hanging', function () {
    $this->dbMock->shouldReceive('query')->twice()->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetch')->twice()->andReturn(
        ['parent_comment_id' => 9],
        ['parent_comment_id' => 8]
    );

    expect($this->model->parentForReply(8))->toBe(8);
});

test('replies nest to whatever depth they were written at', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00'),
        row(2, 1, '2026-01-01 10:01:00'),
        row(3, 2, '2026-01-01 10:02:00'),
        row(4, 3, '2026-01-01 10:03:00'),
        row(5, 4, '2026-01-01 10:04:00'),
    ]);

    expect(flatten($this->model->forPostThreaded(5)))
        ->toBe(['1:0', '2:1', '3:2', '4:3', '5:4']);
});

test('siblings at the same depth keep their chronological order', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00'),
        row(2, 1, '2026-01-01 10:01:00'),
        row(3, 1, '2026-01-01 10:02:00'),
        row(4, 2, '2026-01-01 10:03:00'),
    ]);

    expect(flatten($this->model->forPostThreaded(5)))
        ->toBe(['1:0', '2:1', '4:2', '3:1']);
});

test('a reply whose parent is not visible stays hidden rather than surfacing as a root', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00'),
        // Parent 99 is held for moderation, so it is absent from the set
        row(2, 99, '2026-01-01 10:01:00'),
    ]);

    expect(flatten($this->model->forPostThreaded(5)))->toBe(['1:0']);
});

test('top sort ranks roots by score and leaves replies chronological', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00', 1),
        row(2, null, '2026-01-01 10:01:00', 9),
        row(3, null, '2026-01-01 10:02:00', 5, 4),
        row(4, 2, '2026-01-01 10:03:00'),
        row(5, 2, '2026-01-01 10:04:00'),
    ]);

    expect(flatten($this->model->forPostThreaded(5, CommentModel::SORT_TOP)))
        ->toBe(['2:0', '4:1', '5:1', '3:0', '1:0']);
});

test('a downvoted root sinks below an untouched one', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00', 0, 6),
        row(2, null, '2026-01-01 10:01:00'),
    ]);

    expect(flatten($this->model->forPostThreaded(5, CommentModel::SORT_TOP)))->toBe(['2:0', '1:0']);
});

test('newest sort ignores score and orders roots by recency', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00', 99),
        row(2, null, '2026-01-01 10:05:00'),
    ]);

    expect(flatten($this->model->forPostThreaded(5, CommentModel::SORT_NEW)))->toBe(['2:0', '1:0']);
});

test('a pinned comment leads whatever the sort and whatever its score', function () {
    $rows = [
        row(1, null, '2026-01-01 10:00:00', 500),
        row(2, null, '2026-01-01 10:09:00'),
        row(3, null, '2026-01-01 10:01:00', 0, 0, '2026-01-02 08:00:00'),
    ];

    stubThreadRows($this, $rows);
    expect(flatten($this->model->forPostThreaded(5, CommentModel::SORT_TOP))[0])->toBe('3:0');

    stubThreadRows($this, $rows);
    expect(flatten($this->model->forPostThreaded(5, CommentModel::SORT_NEW))[0])->toBe('3:0');
});

test('an unknown sort falls back to top rather than erroring', function () {
    stubThreadRows($this, [
        row(1, null, '2026-01-01 10:00:00'),
        row(2, null, '2026-01-01 10:01:00', 3),
    ]);

    expect(flatten($this->model->forPostThreaded(5, 'nonsense')))->toBe(['2:0', '1:0']);
});
