<?php

declare(strict_types=1);

/**
 * Unit tests for PostVoteModel toggle behaviour.
 *
 * Mocks the database layer to verify SQL construction and the three-state
 * toggle without a real connection. Deliberately the same contract as
 * CommentVoteModel, because a reader should not have to learn two.
 */

use App\Models\PostVoteModel;
use Framework\Database;

beforeEach(function () {
    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    // Run the callback inline; transaction mechanics are the database's job.
    $this->dbMock->shouldReceive('transaction')
        ->andReturnUsing(fn (callable $callback) => $callback());

    $this->model = new PostVoteModel($this->dbMock);
});

/** Stub the two counts read back after every vote change. */
function stubPostCounts(object $test, int $up, int $down): void
{
    $test->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT COUNT\(\*\) FROM post_votes/'), [42, 1])
        ->andReturn($test->stmtMock);

    $test->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT COUNT\(\*\) FROM post_votes/'), [42, -1])
        ->andReturn($test->stmtMock);

    $test->stmtMock->shouldReceive('fetchColumn')->once()->andReturn($up);
    $test->stmtMock->shouldReceive('fetchColumn')->once()->andReturn($down);
}

test('a first up vote is stored and reported back as the viewer’s own', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT value FROM post_votes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(false);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/INSERT INTO post_votes/'), [42, 7, 1])
        ->andReturn(1);

    stubPostCounts($this, 1, 0);

    expect($this->model->apply(7, 42, PostVoteModel::UP))
        ->toBe(['up' => 1, 'down' => 0, 'mine' => 1]);
});

test('pressing the same direction again clears the vote', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT value FROM post_votes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(1);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/DELETE FROM post_votes/'), [42, 7])
        ->andReturn(1);

    stubPostCounts($this, 0, 0);

    expect($this->model->apply(7, 42, PostVoteModel::UP))
        ->toBe(['up' => 0, 'down' => 0, 'mine' => 0]);
});

test('pressing the other direction flips the existing vote in place', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT value FROM post_votes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(1);

    // ON DUPLICATE KEY UPDATE, so the flip cannot leave a second row behind.
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/ON DUPLICATE KEY UPDATE/'), [42, 7, -1])
        ->andReturn(1);

    stubPostCounts($this, 0, 1);

    expect($this->model->apply(7, 42, PostVoteModel::DOWN))
        ->toBe(['up' => 0, 'down' => 1, 'mine' => -1]);
});

test('the liked library only counts up votes', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/l\.value = 1/'), [7])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchAll')->once()->andReturn([]);

    expect($this->model->likedPosts(7))->toBe([]);
});
