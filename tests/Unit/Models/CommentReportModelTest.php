<?php

declare(strict_types=1);

/**
 * Unit tests for comment reporting.
 *
 * The property that matters is that one annoyed reader cannot manufacture a
 * pile-on: a second report from the same person changes nothing.
 */

use App\Models\CommentReportModel;
use Framework\Database;

beforeEach(function () {
    $this->dbMock = Mockery::mock(Database::class);
    $this->stmtMock = Mockery::mock(PDOStatement::class);

    // Run the callback inline; transaction mechanics are the database's job.
    $this->dbMock->shouldReceive('transaction')
        ->andReturnUsing(fn (callable $callback) => $callback());

    $this->model = new CommentReportModel($this->dbMock);
});

test('a first report is stored and the comment counter resynced', function () {
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/INSERT IGNORE INTO comment_reports/'), [42, 7, 'spam'])
        ->andReturn(1);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/UPDATE comments s/'), [42])
        ->andReturn(1);

    expect($this->model->report(7, 42, 'spam'))->toBeTrue();
});

test('reporting the same comment twice changes nothing', function () {
    // The unique key swallows it, so INSERT IGNORE affects no rows.
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/INSERT IGNORE INTO comment_reports/'), [42, 7, 'spam'])
        ->andReturn(0);

    $this->dbMock->shouldNotReceive('execute')
        ->with(Mockery::pattern('/UPDATE comments s/'), Mockery::any());

    expect($this->model->report(7, 42, 'spam'))->toBeFalse();
});

test('an unrecognised reason is filed as other rather than rejected', function () {
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/INSERT IGNORE INTO comment_reports/'), [42, 7, 'other'])
        ->andReturn(1);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/UPDATE comments s/'), [42])
        ->andReturn(1);

    expect($this->model->report(7, 42, '<script>alert(1)</script>'))->toBeTrue();
});

test('clearing a comment drops its reports and zeroes the counter', function () {
    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/DELETE FROM comment_reports/'), [42])
        ->andReturn(3);

    $this->dbMock->shouldReceive('execute')
        ->once()
        ->with(Mockery::pattern('/UPDATE comments SET reports_count = 0/'), [42])
        ->andReturn(1);

    $this->model->clearFor(42);

    expect(true)->toBeTrue();
});

test('reasonsFor summarises what people objected to', function () {
    $this->dbMock->shouldReceive('query')
        ->once()
        ->with(Mockery::pattern('/GROUP BY reason/'), [42])
        ->andReturn($this->stmtMock);

    $this->stmtMock->shouldReceive('fetchAll')->once()->andReturn([
        ['reason' => 'spam', 'total' => '4'],
        ['reason' => 'other', 'total' => '1'],
    ]);

    expect($this->model->reasonsFor(42))->toBe([
        ['reason' => 'spam', 'total' => 4],
        ['reason' => 'other', 'total' => 1],
    ]);
});
