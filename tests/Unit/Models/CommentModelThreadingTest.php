<?php

declare(strict_types=1);

/**
 * Unit tests for CommentModel reply threading.
 *
 * Mocks the database so forPostThreaded grouping logic is verified in isolation.
 */

use App\Models\CommentModel;
use Framework\Database;

beforeEach(function () {
    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    $this->model = new CommentModel($this->dbMock);
});

function threadingRows(): array
{
    return [
        ['id' => 1, 'post_id' => 5, 'parent_comment_id' => null, 'content' => 'first', 'user_name' => 'a'],
        ['id' => 2, 'post_id' => 5, 'parent_comment_id' => null, 'content' => 'second', 'user_name' => 'b'],
        ['id' => 3, 'post_id' => 5, 'parent_comment_id' => 1, 'content' => 'reply to first', 'user_name' => 'c'],
        ['id' => 4, 'post_id' => 5, 'parent_comment_id' => 1, 'content' => 'another reply', 'user_name' => 'd'],
        // Orphan: parent 99 is not in the approved set
        ['id' => 5, 'post_id' => 5, 'parent_comment_id' => 99, 'content' => 'orphan', 'user_name' => 'e'],
    ];
}

test('forPostThreaded groups replies under their parents', function () {
    $this->dbMock->shouldReceive('query')->once()->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchAll')->once()->andReturn(threadingRows());

    $threads = $this->model->forPostThreaded(5);

    expect($threads)->toHaveCount(2)
        ->and($threads[0]['id'])->toBe(1)
        ->and($threads[0]['replies'])->toHaveCount(2)
        ->and($threads[0]['replies'][0]['id'])->toBe(3)
        ->and($threads[1]['id'])->toBe(2)
        ->and($threads[1]['replies'])->toBeEmpty();
});

test('forPostThreaded hides replies whose parent is not visible', function () {
    $this->dbMock->shouldReceive('query')->once()->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchAll')->once()->andReturn(threadingRows());

    $threads = $this->model->forPostThreaded(5);
    $allIds = [];

    foreach ($threads as $thread) {
        $allIds[] = $thread['id'];
        foreach ($thread['replies'] as $reply) {
            $allIds[] = $reply['id'];
        }
    }

    expect($allIds)->not->toContain(5);
});
