<?php

declare(strict_types=1);

/**
 * Unit tests for PostLikeModel toggle behavior.
 *
 * Mocks the database layer to verify SQL construction and toggle semantics
 * without a real connection.
 */

use App\Models\PostLikeModel;
use Framework\Database;

beforeEach(function () {
    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    $this->model = new PostLikeModel($this->dbMock);
});

test('toggle inserts a like when none exists and reports liked', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT 1 FROM post_likes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(false);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/INSERT INTO post_likes/'), [42, 7])
        ->andReturn($this->stmtMock);

    expect($this->model->toggle(7, 42))->toBeTrue();
});

test('toggle deletes an existing like and reports unliked', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT 1 FROM post_likes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(1);

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/DELETE FROM post_likes/'), [42, 7])
        ->andReturn($this->stmtMock);

    expect($this->model->toggle(7, 42))->toBeFalse();
});

test('toggle treats a duplicate-key insert as already liked', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT 1 FROM post_likes/'), [7, 42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn(false);

    // PDO reports the SQLSTATE as the exception code, but that property cannot
    // be set from outside: PHP refuses to bind a closure to the scope of an
    // internal class, so the old ->call($duplicate) trick silently left the
    // code at 0 and this test could never pass. A subclass can assign the
    // inherited protected property directly.
    $duplicate = new class('Duplicate entry') extends PDOException
    {
        public function __construct(string $message)
        {
            parent::__construct($message);
            $this->code = '23000';
            $this->errorInfo = ['23000', 1062, 'Duplicate entry'];
        }
    };

    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/INSERT INTO post_likes/'), [42, 7])
        ->andThrow($duplicate);

    expect($this->model->toggle(7, 42))->toBeTrue();
});

test('countByPost returns the like total', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/SELECT COUNT\(\*\) FROM post_likes/'), [42])
        ->andReturn($this->stmtMock);
    $this->stmtMock->shouldReceive('fetchColumn')->once()->andReturn('3');

    expect($this->model->countByPost(42))->toBe(3);
});
