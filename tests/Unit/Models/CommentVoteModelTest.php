<?php

declare(strict_types=1);

/**
 * Unit tests for comment vote toggling.
 *
 * Mocks the database so the three-state behaviour readers expect from a
 * thumbs pair is pinned down without a connection: pressing the direction you
 * already chose clears it, pressing the other one flips it.
 */

use App\Models\CommentVoteModel;
use Framework\Database;

beforeEach(function () {
    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    // Run the callback inline; transaction mechanics are the database's job.
    $this->dbMock->shouldReceive('transaction')
        ->andReturnUsing(fn (callable $callback) => $callback());

    $this->model = new CommentVoteModel($this->dbMock);
});

/** Stub the recount that follows every vote change. */
function expectCounterSync(object $test, int $up, int $down): void
{
    $test->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/UPDATE comments c SET/'), [42])
        ->andReturn(1);

    $test->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT upvotes, downvotes FROM comments/'), [42])
        ->andReturn($test->stmtMock);

    $test->stmtMock->shouldReceive('fetch')
        ->once()
        ->andReturn(['upvotes' => $up, 'downvotes' => $down]);
}

test('a first vote is stored and reported back as the viewer’s own', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT value FROM comment_votes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(false);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/INSERT INTO comment_votes/'), [42, 7, 1])
        ->andReturn(1);

    expectCounterSync($this, 1, 0);

    expect($this->model->apply(7, 42, CommentVoteModel::UP))
        ->toBe(['up' => 1, 'down' => 0, 'mine' => 1]);
});

test('pressing the same direction again clears the vote', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT value FROM comment_votes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(1);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/DELETE FROM comment_votes/'), [42, 7])
        ->andReturn(1);

    expectCounterSync($this, 0, 0);

    expect($this->model->apply(7, 42, CommentVoteModel::UP))
        ->toBe(['up' => 0, 'down' => 0, 'mine' => 0]);
});

test('pressing the other direction flips the existing vote in place', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT value FROM comment_votes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(1);

    // ON DUPLICATE KEY UPDATE, so the flip cannot leave a second row behind.
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/ON DUPLICATE KEY UPDATE/'), [42, 7, -1])
        ->andReturn(1);

    expectCounterSync($this, 0, 1);

    expect($this->model->apply(7, 42, CommentVoteModel::DOWN))
        ->toBe(['up' => 0, 'down' => 1, 'mine' => -1]);
});

test('votesForPost returns the viewer’s votes keyed by comment', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/FROM comment_votes v/'), [7, 5])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchAll')->once()->andReturn([
        ['comment_id' => '14', 'value' => '1'],
        ['comment_id' => '16', 'value' => '-1'],
    ]);

    expect($this->model->votesForPost(7, 5))->toBe([14 => 1, 16 => -1]);
});
