<?php

declare(strict_types=1);

use App\Models\MailQueueModel;
use App\Models\UserModel;
use Tests\Factories\UserFactory;

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

describe('failedCountSince', function () {

    test('counts rows that gave up inside the window and ignores everything else', function () {
        $gaveUp = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($gaveUp, 'boom', 0);

        $stillRetrying = queueMail($this->model);
        $this->model->markFailed($stillRetrying, 'transient', 60);

        $sent = queueMail($this->model);
        $this->model->markSent($sent);

        expect($this->model->failedCountSince(60))->toBe(1);
    });

    test('ignores a failure that last updated before the window', function () {
        $id = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($id, 'boom', 0);

        $this->db->execute(
            'UPDATE mail_queue SET updated_at = DATE_SUB(NOW(), INTERVAL 90 MINUTE) WHERE id = ?',
            [$id]
        );

        expect($this->model->failedCountSince(60))->toBe(0);
    });
});

describe('cancel', function () {

    test('cancels a pending row and records who cancelled it', function () {
        $id = queueMail($this->model);

        expect($this->model->cancel($id, 7))->toBeTrue();

        $row = fetchQueueRow($this->db, $id);
        expect($row['status'])->toBe('cancelled')
            ->and((int) $row['cancelled_by'])->toBe(7)
            ->and($row['cancelled_at'])->not->toBeNull();
    });

    test('does not cancel a row that is sending, sent, or already cancelled', function () {
        $sending = queueMail($this->model);
        $this->model->claimBatch(1);

        $sent = queueMail($this->model);
        $this->model->markSent($sent);

        expect($this->model->cancel($sending, 7))->toBeFalse()
            ->and($this->model->cancel($sent, 7))->toBeFalse();

        expect(fetchQueueRow($this->db, $sending)['status'])->toBe('sending')
            ->and(fetchQueueRow($this->db, $sent)['status'])->toBe('sent');
    });

    test('cancelMany only cancels the ids that are still pending', function () {
        $pendingA = queueMail($this->model);
        $pendingB = queueMail($this->model);
        $sent = queueMail($this->model);
        $this->model->markSent($sent);

        $cancelled = $this->model->cancelMany([$pendingA, $pendingB, $sent], 7);

        expect($cancelled)->toBe(2)
            ->and(fetchQueueRow($this->db, $pendingA)['status'])->toBe('cancelled')
            ->and(fetchQueueRow($this->db, $pendingB)['status'])->toBe('cancelled')
            ->and(fetchQueueRow($this->db, $sent)['status'])->toBe('sent');
    });

    test('cancelMany returns zero for an empty selection', function () {
        expect($this->model->cancelMany([], 7))->toBe(0);
    });
});

describe('resend', function () {

    test('queues a fresh copy of a sent row and links it back', function () {
        $id = queueMail($this->model, ['to_email' => 'reader@example.test', 'subject' => 'Welcome']);
        $this->model->markSent($id);

        $newId = $this->model->resend($id);

        expect($newId)->not->toBeNull();

        $copy = fetchQueueRow($this->db, $newId);
        expect($copy['status'])->toBe('pending')
            ->and($copy['to_email'])->toBe('reader@example.test')
            ->and($copy['subject'])->toBe('Welcome')
            ->and((int) $copy['resent_from_id'])->toBe($id)
            ->and((int) $copy['attempts'])->toBe(0);
    });

    test('does not resend a row that has not been sent', function () {
        $pending = queueMail($this->model);

        expect($this->model->resend($pending))->toBeNull();

        $copyExists = (bool) $this->db->query('SELECT 1 FROM mail_queue WHERE resent_from_id = ?', [$pending])->fetchColumn();
        expect($copyExists)->toBeFalse();
    });

    test('resendMany only resends the ids that are actually sent', function () {
        $sent = queueMail($this->model);
        $this->model->markSent($sent);
        $pending = queueMail($this->model);

        $resent = $this->model->resendMany([$sent, $pending]);

        expect($resent)->toBe(1);

        $copies = (int) $this->db->query('SELECT COUNT(*) FROM mail_queue WHERE resent_from_id = ?', [$sent])->fetchColumn();
        expect($copies)->toBe(1);
    });
});

describe('retryMany', function () {

    test('requeues only the ids that are actually failed', function () {
        $failed = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($failed, 'boom', 0);
        $pending = queueMail($this->model);

        $requeued = $this->model->retryMany([$failed, $pending]);

        expect($requeued)->toBe(1);

        $row = fetchQueueRow($this->db, $failed);
        expect($row['status'])->toBe('pending')
            ->and((int) $row['attempts'])->toBe(0)
            ->and($row['last_error'])->toBeNull();
    });

    test('ignores duplicate and non-positive ids in the selection', function () {
        $failed = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($failed, 'boom', 0);

        $requeued = $this->model->retryMany([$failed, $failed, 0, -1]);

        expect($requeued)->toBe(1);
    });

    test('returns zero for an empty selection', function () {
        expect($this->model->retryMany([]))->toBe(0);
    });
});

describe('restore', function () {

    test('puts a cancelled row back in line and clears the cancel stamp', function () {
        $id = queueMail($this->model);
        $this->model->cancel($id, 7);

        expect($this->model->restore($id))->toBeTrue();

        $row = fetchQueueRow($this->db, $id);
        expect($row['status'])->toBe('pending')
            ->and($row['cancelled_at'])->toBeNull()
            ->and($row['cancelled_by'])->toBeNull()
            ->and($row['claim_token'])->toBeNull();
    });

    test('leaves the attempts already spent and the error that caused them', function () {
        $id = queueMail($this->model, ['max_attempts' => 3]);
        // One soft failure: still pending, one attempt gone, reason recorded.
        $this->model->markFailed($id, 'greylisted', 60);
        $this->model->cancel($id, 7);

        $this->model->restore($id);

        $row = fetchQueueRow($this->db, $id);
        expect((int) $row['attempts'])->toBe(1)
            ->and($row['last_error'])->toBe('greylisted')
            ->and($row['status'])->toBe('pending');
    });

    test('will not touch a row that was never cancelled', function () {
        $pending = queueMail($this->model);
        $sent = queueMail($this->model);
        $this->model->markSent($sent);

        expect($this->model->restore($pending))->toBeFalse()
            ->and($this->model->restore($sent))->toBeFalse()
            ->and($this->model->restore(999999))->toBeFalse()
            ->and(fetchQueueRow($this->db, $sent)['status'])->toBe('sent');
    });
});

describe('restoreMany', function () {

    test('restores only the selected rows that are cancelled', function () {
        $cancelled = queueMail($this->model);
        $this->model->cancel($cancelled, 7);
        $pending = queueMail($this->model);

        expect($this->model->restoreMany([$cancelled, $pending]))->toBe(1)
            ->and(fetchQueueRow($this->db, $cancelled)['status'])->toBe('pending')
            ->and(fetchQueueRow($this->db, $pending)['status'])->toBe('pending');
    });

    test('counts each id once however many times it is sent', function () {
        $id = queueMail($this->model);
        $this->model->cancel($id, 7);

        expect($this->model->restoreMany([$id, $id, $id]))->toBe(1);
    });

    test('returns zero for an empty selection', function () {
        expect($this->model->restoreMany([]))->toBe(0);
    });
});

describe('whole filter actions', function () {

    test('cancelAllMatching reaches every pending row the filter covers, and nothing else', function () {
        $mine = queueMail($this->model, ['to_email' => 'keep@example.test']);
        $alsoMine = queueMail($this->model, ['to_email' => 'keep@example.test']);
        $other = queueMail($this->model, ['to_email' => 'other@example.test']);
        $sent = queueMail($this->model, ['to_email' => 'keep@example.test']);
        $this->model->markSent($sent);

        $affected = $this->model->cancelAllMatching(['search' => 'keep@'], 7);

        expect($affected)->toBe(2)
            ->and(fetchQueueRow($this->db, $mine)['status'])->toBe('cancelled')
            ->and(fetchQueueRow($this->db, $alsoMine)['status'])->toBe('cancelled')
            ->and(fetchQueueRow($this->db, $other)['status'])->toBe('pending')
            // The filter widens which rows are considered, never which statuses.
            ->and(fetchQueueRow($this->db, $sent)['status'])->toBe('sent');
    });

    test('a tier filter narrows it the same way the listing does', function () {
        $bulk = queueMail($this->model, ['tier' => 'bulk']);
        $critical = queueMail($this->model, ['tier' => 'critical']);

        expect($this->model->cancelAllMatching(['tier' => 'bulk'], 7))->toBe(1)
            ->and(fetchQueueRow($this->db, $bulk)['status'])->toBe('cancelled')
            ->and(fetchQueueRow($this->db, $critical)['status'])->toBe('pending');
    });

    test('restoreAllMatching and retryAllMatching each stay on their own status', function () {
        $cancelled = queueMail($this->model, ['to_email' => 'mix@example.test']);
        $this->model->cancel($cancelled, 7);
        $failed = queueMail($this->model, ['to_email' => 'mix@example.test', 'max_attempts' => 1]);
        $this->model->markFailed($failed, 'boom', 0);

        expect($this->model->retryAllMatching(['search' => 'mix@']))->toBe(1)
            ->and(fetchQueueRow($this->db, $failed)['status'])->toBe('pending')
            ->and(fetchQueueRow($this->db, $cancelled)['status'])->toBe('cancelled')
            ->and($this->model->restoreAllMatching(['search' => 'mix@']))->toBe(1)
            ->and(fetchQueueRow($this->db, $cancelled)['status'])->toBe('pending');
    });

    test('resendAllMatching queues one copy per sent row, remembering the original', function () {
        $first = queueMail($this->model, ['to_email' => 'copy@example.test', 'subject' => 'One']);
        $second = queueMail($this->model, ['to_email' => 'copy@example.test', 'subject' => 'Two']);
        $this->model->markSent($first);
        $this->model->markSent($second);
        $untouched = queueMail($this->model, ['to_email' => 'copy@example.test', 'subject' => 'Still pending']);

        $queued = $this->model->resendAllMatching(['search' => 'copy@']);

        $copies = $this->db->query(
            'SELECT subject, status, resent_from_id FROM mail_queue WHERE resent_from_id IN (?, ?, ?) ORDER BY id',
            [$first, $second, $untouched]
        )->fetchAll(PDO::FETCH_ASSOC);

        expect($queued)->toBe(2)
            ->and($copies)->toHaveCount(2)
            ->and(array_column($copies, 'subject'))->toBe(['One', 'Two'])
            ->and($copies[0]['status'])->toBe('pending');
    });

    test('an empty filter means the whole queue, which is what retryAllFailed is', function () {
        $failed = queueMail($this->model, ['max_attempts' => 1]);
        $this->model->markFailed($failed, 'boom', 0);
        $pending = queueMail($this->model);

        expect($this->model->retryAllFailed())->toBe(1)
            ->and(fetchQueueRow($this->db, $failed)['status'])->toBe('pending')
            ->and(fetchQueueRow($this->db, $pending)['status'])->toBe('pending');
    });

    test('statusCountsMatching counts the same rows the filter lists', function () {
        queueMail($this->model, ['to_email' => 'tally@example.test']);
        queueMail($this->model, ['to_email' => 'tally@example.test']);
        $sent = queueMail($this->model, ['to_email' => 'tally@example.test']);
        $this->model->markSent($sent);
        queueMail($this->model, ['to_email' => 'elsewhere@example.test']);

        $counts = $this->model->statusCountsMatching(['search' => 'tally@']);
        $listed = $this->model->findWithFilters('', 'tally@');

        expect($counts['pending'])->toBe(2)
            ->and($counts['sent'])->toBe(1)
            ->and($counts['pending'] + $counts['sent'])->toBe($listed['pagination']['total_records']);
    });
});

describe('findWithFilters', function () {

    test('each filter narrows the list and the total agrees with it', function () {
        queueMail($this->model, ['to_email' => 'ann@example.test', 'tier' => 'bulk']);
        queueMail($this->model, ['to_email' => 'ann@example.test', 'tier' => 'critical']);
        queueMail($this->model, ['to_email' => 'bob@example.test', 'tier' => 'bulk']);
        $sent = queueMail($this->model, ['to_email' => 'ann@example.test', 'tier' => 'bulk']);
        $this->model->markSent($sent);

        $byRecipient = $this->model->findWithFilters('', 'ann@');
        $byTier = $this->model->findWithFilters('', '', 1, 25, 'bulk');
        $bothAndStatus = $this->model->findWithFilters('pending', 'ann@', 1, 25, 'bulk');

        expect($byRecipient['pagination']['total_records'])->toBe(3)
            ->and($byRecipient['data'])->toHaveCount(3)
            ->and($byTier['pagination']['total_records'])->toBe(3)
            // pending + ann + bulk leaves exactly one of the four.
            ->and($bothAndStatus['pagination']['total_records'])->toBe(1)
            ->and($bothAndStatus['data'][0]['to_email'])->toBe('ann@example.test');
    });

    test('paging a filtered list keeps the filter and moves the window', function () {
        foreach (range(1, 7) as $i) {
            queueMail($this->model, ['to_email' => 'page@example.test', 'subject' => 'Mail '.$i]);
        }
        queueMail($this->model, ['to_email' => 'elsewhere@example.test']);

        $first = $this->model->findWithFilters('', 'page@', 1, 5);
        $second = $this->model->findWithFilters('', 'page@', 2, 5);

        expect($first['data'])->toHaveCount(5)
            ->and($second['data'])->toHaveCount(2)
            ->and($first['pagination']['total_records'])->toBe(7)
            ->and($second['pagination']['has_next'])->toBeFalse()
            // No row may appear on both pages.
            ->and(array_intersect(array_column($first['data'], 'id'), array_column($second['data'], 'id')))->toBe([]);
    });

    test('a cancelled row carries who stopped it into the listing', function () {
        $canceller = UserFactory::new(new UserModel($this->db))
            ->withAttributes(['display_name_cached' => 'Dana Stopper'])
            ->create();

        $id = queueMail($this->model, ['to_email' => 'stamp@example.test']);
        $this->model->cancel($id, $canceller);

        $row = $this->model->findWithFilters('cancelled', 'stamp@')['data'][0];

        expect($row['cancelled_at'])->not->toBeNull()
            ->and($row['cancelled_by_name'])->toBe('Dana Stopper');
    });
});
