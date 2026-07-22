<?php

declare(strict_types=1);

use App\Models\MailQueueModel;

/**
 * Integration tests for MailQueueModel.
 *
 * Exercises the queue state machine against a real database: enqueue, the
 * claim that stops two workers taking the same row, retry backoff, the
 * give-up threshold, and reclaiming abandoned claims.
 */
beforeEach(function () {
    $this->model = new MailQueueModel($this->db);
});

/** Helper: queue one row with sensible defaults. */
function queueMail(MailQueueModel $model, array $overrides = []): int
{
    return $model->enqueue(array_merge([
        'to_email' => 'reader@example.test',
        'subject' => 'New post',
        'body_html' => '<p>Hello</p>',
        'body_text' => 'Hello',
        'related_type' => 'post',
        'related_id' => 1,
    ], $overrides));
}

/** Helper: read one row straight from the table. */
function fetchQueueRow(Framework\Database $db, int $id): array
{
    return $db->query('SELECT * FROM mail_queue WHERE id = ?', [$id])
        ->fetch(PDO::FETCH_ASSOC);
}

/**
 * Helper: current time as the database sees it.
 *
 * MySQL runs on system time here while PHP runs on UTC, so scheduling
 * assertions have to read the clock the queue itself compares against.
 */
function queueNow(Framework\Database $db): int
{
    return (int) strtotime((string) $db->query('SELECT NOW()')->fetchColumn());
}

describe('enqueue', function () {

    test('stores the rendered email as pending and immediately due', function () {
        $id = queueMail($this->model);
        $row = fetchQueueRow($this->db, $id);

        expect($row['status'])->toBe('pending')
            ->and($row['to_email'])->toBe('reader@example.test')
            ->and($row['body_html'])->toBe('<p>Hello</p>')
            ->and((int) $row['attempts'])->toBe(0)
            ->and($row['sent_at'])->toBeNull()
            ->and(strtotime($row['next_attempt_at']))->toBeLessThanOrEqual(queueNow($this->db) + 1);
    });
});

describe('claimBatch', function () {

    test('claims up to the limit and flips them to sending', function () {
        queueMail($this->model);
        queueMail($this->model);
        queueMail($this->model);

        $claimed = $this->model->claimBatch(2);

        expect($claimed)->toHaveCount(2);

        $counts = $this->model->statusCounts();
        expect($counts['sending'])->toBe(2)
            ->and($counts['pending'])->toBe(1);
    });

    test('a second worker cannot claim rows the first already took', function () {
        queueMail($this->model);
        queueMail($this->model);

        $first = $this->model->claimBatch(10);
        $second = $this->model->claimBatch(10);

        expect($first)->toHaveCount(2)
            ->and($second)->toBeEmpty();
    });

    test('ignores rows whose backoff has not elapsed', function () {
        $id = queueMail($this->model);
        $this->db->execute(
            'UPDATE mail_queue SET next_attempt_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = ?',
            [$id]
        );

        expect($this->model->claimBatch(10))->toBeEmpty();
    });

    test('returns nothing for a non-positive limit', function () {
        queueMail($this->model);

        expect($this->model->claimBatch(0))->toBeEmpty();
    });
});

describe('markSent', function () {

    test('records delivery and clears the claim', function () {
        $id = queueMail($this->model);
        $this->model->claimBatch(1);

        expect($this->model->markSent($id))->toBeTrue();

        $row = fetchQueueRow($this->db, $id);
        expect($row['status'])->toBe('sent')
            ->and((int) $row['attempts'])->toBe(1)
            ->and($row['sent_at'])->not->toBeNull()
            ->and($row['claim_token'])->toBeNull();
    });
});

describe('markFailed', function () {

    test('requeues with backoff while attempts remain', function () {
        $id = queueMail($this->model);
        $this->model->claimBatch(1);

        $status = $this->model->markFailed($id, 'SMTP Error: data not accepted.', 60);

        expect($status)->toBe('pending');

        $row = fetchQueueRow($this->db, $id);
        // First failure: attempts becomes 1 and the row waits one backoff unit.
        expect((int) $row['attempts'])->toBe(1)
            ->and($row['last_error'])->toContain('data not accepted')
            ->and($row['claim_token'])->toBeNull()
            ->and(strtotime($row['next_attempt_at']))->toBeGreaterThan(queueNow($this->db) + 30);
    });

    test('backoff grows with each attempt', function () {
        $id = queueMail($this->model);

        $this->model->markFailed($id, 'first', 60);
        $firstWait = strtotime(fetchQueueRow($this->db, $id)['next_attempt_at']) - queueNow($this->db);

        $this->model->markFailed($id, 'second', 60);
        $secondWait = strtotime(fetchQueueRow($this->db, $id)['next_attempt_at']) - queueNow($this->db);

        expect($secondWait)->toBeGreaterThan($firstWait);
    });

    test('gives up once max_attempts is reached', function () {
        $id = queueMail($this->model, ['max_attempts' => 2]);

        expect($this->model->markFailed($id, 'boom', 1))->toBe('pending')
            ->and($this->model->markFailed($id, 'boom', 1))->toBe('failed');

        expect((int) fetchQueueRow($this->db, $id)['attempts'])->toBe(2);
    });

    test('a failed row is never claimed again', function () {
        $id = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($id, 'boom', 0);

        expect($this->model->claimBatch(10))->toBeEmpty();
    });
});

describe('releaseStuck', function () {

    test('reclaims sending rows abandoned by an interrupted worker', function () {
        $id = queueMail($this->model);
        $this->model->claimBatch(1);

        // Backdate the claim past the staleness window
        $this->db->execute(
            'UPDATE mail_queue SET updated_at = DATE_SUB(NOW(), INTERVAL 30 MINUTE) WHERE id = ?',
            [$id]
        );

        expect($this->model->releaseStuck(15))->toBe(1)
            ->and($this->model->claimBatch(10))->toHaveCount(1);
    });

    test('leaves a fresh claim alone', function () {
        queueMail($this->model);
        $this->model->claimBatch(1);

        expect($this->model->releaseStuck(15))->toBe(0);
    });
});

describe('retry', function () {

    test('puts a failed row back in line with a clean slate', function () {
        $id = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($id, 'boom', 0);

        expect($this->model->retry($id))->toBeTrue();

        $row = fetchQueueRow($this->db, $id);
        expect($row['status'])->toBe('pending')
            ->and((int) $row['attempts'])->toBe(0)
            ->and($row['last_error'])->toBeNull();
    });

    test('does nothing to a row that has not failed', function () {
        $id = queueMail($this->model);

        expect($this->model->retry($id))->toBeFalse();
    });
});

describe('forRelated', function () {

    test('returns only the rows for that source', function () {
        queueMail($this->model, ['related_id' => 1]);
        queueMail($this->model, ['related_id' => 1]);
        queueMail($this->model, ['related_id' => 2]);

        expect($this->model->forRelated('post', 1))->toHaveCount(2);
    });
});

describe('pruneSent', function () {

    test('deletes delivered rows past the retention window but keeps recent ones', function () {
        $old = queueMail($this->model);
        $recent = queueMail($this->model);

        $this->model->markSent($old);
        $this->model->markSent($recent);

        $this->db->execute(
            'UPDATE mail_queue SET sent_at = DATE_SUB(NOW(), INTERVAL 60 DAY) WHERE id = ?',
            [$old]
        );

        expect($this->model->pruneSent(30))->toBe(1)
            ->and(fetchQueueRow($this->db, $recent))->not->toBeEmpty();
    });

    test('never deletes mail that is still pending', function () {
        $id = queueMail($this->model);
        $this->db->execute(
            'UPDATE mail_queue SET created_at = DATE_SUB(NOW(), INTERVAL 60 DAY) WHERE id = ?',
            [$id]
        );

        expect($this->model->pruneSent(30))->toBe(0);
    });
});
