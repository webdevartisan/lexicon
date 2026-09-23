<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Data layer for the outbound mail queue.
 *
 * Rows move pending -> sending -> sent, or back to pending with a pushed-out
 * next_attempt_at while retries remain, and finally to failed. Nothing here
 * talks to a mail transport; that is the worker's job.
 */
class MailQueueModel extends AppModel
{
    protected ?string $table = 'mail_queue';

    /**
     * Add a rendered email to the queue.
     *
     * @param  array{to_email: string, to_name?: string|null, subject: string, body_html: string, body_text?: string|null, tier?: string, related_type?: string|null, related_id?: int|null, max_attempts?: int}  $mail
     * @return int The new queue row ID
     */
    public function enqueue(array $mail): int
    {
        $this->database->query(
            "INSERT INTO {$this->getTable()}
                (to_email, to_name, subject, body_html, body_text, tier, related_type, related_id, max_attempts)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $mail['to_email'],
                $mail['to_name'] ?? null,
                $mail['subject'],
                $mail['body_html'],
                $mail['body_text'] ?? null,
                $mail['tier'] ?? 'standard',
                $mail['related_type'] ?? null,
                $mail['related_id'] ?? null,
                $mail['max_attempts'] ?? 3,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * Take ownership of up to $limit due rows.
     *
     * Claims by flipping to 'sending' first and only then reading the rows
     * back, so two overlapping worker runs can never pick up the same email.
     * The UPDATE is the lock; without it a slow SMTP round trip would leave a
     * row visible to the next cron tick and send it twice.
     *
     * @param  int  $limit  Maximum rows to claim
     * @param  string  $tier  Restrict to one tier, '' for any
     * @return array<int, array<string, mixed>> Claimed rows, oldest first
     */
    public function claimBatch(int $limit, string $tier = ''): array
    {
        if ($limit < 1) {
            return [];
        }

        // Scoped so a large bulk send can never be picked up by the worker
        // that exists to get password resets out in seconds.
        $tierFilter = $tier !== '' ? 'AND tier = ?' : '';
        $tierParams = $tier !== '' ? [$tier] : [];

        $claimToken = bin2hex(random_bytes(16));

        // $limit is interpolated because MySQL will not accept a placeholder in
        // LIMIT on an UPDATE. The int type hint plus the guard above is what
        // keeps that safe; never widen this parameter to a string.
        $claimed = $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'sending', claim_token = ?
             WHERE status = 'pending' AND next_attempt_at <= NOW() {$tierFilter}
             ORDER BY next_attempt_at, id
             LIMIT {$limit}",
            array_merge([$claimToken], $tierParams)
        );

        if ($claimed === 0) {
            return [];
        }

        return $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE claim_token = ? ORDER BY id",
            [$claimToken]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark a claimed row as delivered.
     */
    public function markSent(int $id): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'sent', attempts = attempts + 1, last_error = NULL,
                 claim_token = NULL, sent_at = NOW()
             WHERE id = ?",
            [$id]
        ) > 0;
    }

    /**
     * Record a failed attempt, requeueing it when retries remain.
     *
     * Backoff is attempts * $backoffSeconds, so a provider that refused for
     * being hit too fast gets progressively more room before the next try.
     *
     * @param  int  $id  Queue row ID
     * @param  string  $error  Transport's reason, stored for the admin view
     * @param  int  $backoffSeconds  Base delay multiplied by the attempt count
     * @return string The resulting status, 'pending' (will retry) or 'failed'
     */
    public function markFailed(int $id, string $error, int $backoffSeconds = 60): string
    {
        // Assignment order matters: MySQL evaluates these left to right, so
        // every expression that reads `attempts` has to come before the
        // increment or it would see the already-bumped value.
        $this->database->execute(
            "UPDATE {$this->getTable()}
             SET last_error = ?,
                 claim_token = NULL,
                 status = IF(attempts + 1 >= max_attempts, 'failed', 'pending'),
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL (attempts + 1) * ? SECOND),
                 attempts = attempts + 1
             WHERE id = ?",
            [mb_substr($error, 0, 2000), $backoffSeconds, $id]
        );

        $status = $this->database->query(
            "SELECT status FROM {$this->getTable()} WHERE id = ?",
            [$id]
        )->fetchColumn();

        return (string) $status;
    }

    /**
     * Release rows stuck in 'sending' back to pending.
     *
     * A worker killed mid-batch (deploy, timeout, fatal) leaves its claim
     * behind and those emails would otherwise never be looked at again.
     *
     * @param  int  $olderThanMinutes  How long a claim may sit before it counts as abandoned
     * @return int Rows released
     */
    public function releaseStuck(int $olderThanMinutes = 15): int
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', claim_token = NULL,
                 last_error = 'Reclaimed after an interrupted send'
             WHERE status = 'sending' AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$olderThanMinutes]
        );
    }

    /**
     * Pending rows per tier.
     *
     * Lets the panel spot mail piling up in a tier that has no worker
     * scheduled to drain it, which otherwise looks exactly like email being
     * broken and gives nobody anywhere to start looking.
     *
     * @return array<string, int> Tier name to pending count
     */
    public function pendingByTier(): array
    {
        $rows = $this->database->query(
            "SELECT tier, COUNT(*) AS total
             FROM {$this->getTable()}
             WHERE status IN ('pending', 'sending')
             GROUP BY tier"
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $counts = ['critical' => 0, 'standard' => 0, 'bulk' => 0];

        foreach ($rows as $row) {
            $counts[$row['tier']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Count rows per status, for the dashboard and the worker summary.
     *
     * @return array{pending: int, sending: int, sent: int, failed: int, cancelled: int}
     */
    public function statusCounts(): array
    {
        $rows = $this->database->query(
            "SELECT status, COUNT(*) AS total FROM {$this->getTable()} GROUP BY status"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $counts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Paginated queue listing for the admin panel.
     *
     * @param  string  $status  Restrict to one status, '' for all
     * @param  string  $search  Partial recipient address match
     * @param  string  $tier  Restrict to one tier, '' for all
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function findWithFilters(
        string $status = '',
        string $search = '',
        int $page = 1,
        int $perPage = 25,
        string $tier = '',
        string $orderBy = 'id DESC'
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);

        [$whereSql, $params] = $this->filterClause([
            'status' => $status,
            'search' => $search,
            'tier' => $tier,
        ]);

        $total = (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()} {$whereSql}",
            $params
        )->fetchColumn();

        // The body columns are LONGTEXT and never rendered in the listing, so
        // they are left out rather than hauling every email body into memory.
        // due_in_seconds is subtracted by MySQL so the countdown an operator
        // reads comes from the same clock that decides what claimBatch() picks
        // up. This used to be hours out when PHP did the subtraction on a
        // connection that followed the database host zone.
        // cancelled_by is read through a subquery rather than a join: the sort
        // whitelist and the filters above all name their columns unqualified,
        // and joining users would make half of them ambiguous. It only runs for
        // rows that carry one, and it reads a primary key.
        $sql = "SELECT id, to_email, to_name, subject, status, tier, attempts, max_attempts,
                       TIMESTAMPDIFF(SECOND, NOW(), next_attempt_at) AS due_in_seconds,
                       last_error, related_type, related_id, next_attempt_at, sent_at, created_at,
                       cancelled_at,
                       (SELECT COALESCE(u.display_name_cached, u.handle)
                        FROM users u WHERE u.id = {$this->getTable()}.cancelled_by) AS cancelled_by_name
                FROM {$this->getTable()}
                {$whereSql}
                ORDER BY {$orderBy}
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;

        $rows = $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $totalPages = (int) ceil($total / $perPage);

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    /**
     * How many rows gave up delivering (reached 'failed') in the last N minutes.
     *
     * Reads updated_at rather than created_at: a row queued hours ago that
     * only just exhausted its retries failed *now*, and that is the moment an
     * admin needs to hear about it.
     */
    public function failedCountSince(int $minutes): int
    {
        return (int) $this->database->query(
            "SELECT COUNT(*) FROM {$this->getTable()}
             WHERE status = 'failed' AND updated_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$minutes]
        )->fetchColumn();
    }

    /**
     * Put every failed row back in line for another attempt.
     *
     * @return int Rows requeued
     */
    public function retryAllFailed(): int
    {
        // The same operation as a whole-filter retry with no filter on it, and
        // kept as one statement rather than two copies of the same UPDATE.
        return $this->retryAllMatching([]);
    }

    /**
     * Queue rows for one source, newest first.
     *
     * @param  string  $type  Related type, e.g. 'post'
     * @param  int  $id  Related record ID
     * @return array<int, array<string, mixed>>
     */
    public function forRelated(string $type, int $id): array
    {
        return $this->database->query(
            "SELECT * FROM {$this->getTable()}
             WHERE related_type = ? AND related_id = ?
             ORDER BY id DESC",
            [$type, $id]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Put a failed row back in line for another attempt.
     *
     * Resets the counter so an admin retry is not immediately re-failed by an
     * attempts value left over from the original run.
     */
    public function retry(int $id): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', attempts = 0, last_error = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             WHERE id = ? AND status = 'failed'",
            [$id]
        ) > 0;
    }

    /**
     * Put back in line every selected row that is actually failed.
     *
     * An id for a row that is pending, sent, or gone is simply not touched;
     * the caller sees how many of its ids landed via the return value rather
     * than the request failing over a stale selection.
     *
     * @param  array<int, int>  $ids
     * @return int Rows requeued
     */
    public function retryMany(array $ids): int
    {
        $ids = $this->sanitizeIds($ids);

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', attempts = 0, last_error = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             WHERE status = 'failed' AND id IN ({$placeholders})",
            $ids
        );
    }

    /**
     * Stop a row the worker has not claimed yet.
     *
     * Scoped to 'pending' only: a 'sending' row may already be mid-transport,
     * so cancelling it here could race a worker that is about to mark it
     * sent, leaving the admin unsure whether the email actually went out.
     */
    public function cancel(int $id, int $cancelledBy): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?
             WHERE id = ? AND status = 'pending'",
            [$cancelledBy, $id]
        ) > 0;
    }

    /**
     * Cancel every selected row that is still pending.
     *
     * @param  array<int, int>  $ids
     * @return int Rows cancelled
     */
    public function cancelMany(array $ids, int $cancelledBy): int
    {
        $ids = $this->sanitizeIds($ids);

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?
             WHERE status = 'pending' AND id IN ({$placeholders})",
            array_merge([$cancelledBy], $ids)
        );
    }

    /**
     * Take a cancelled row off hold and put it back in line.
     *
     * Cancelling is something an admin does by hand, so undoing it has to be
     * possible; without this a mis-click was permanent. The row itself is
     * restored rather than copied the way resend does it, because a cancelled
     * email never went anywhere and a copy would just be a duplicate.
     *
     * attempts and last_error are deliberately left alone. Only a pending row
     * can be cancelled, and a pending row may already be several failed tries
     * into its backoff, so zeroing them here would quietly hand it delivery
     * attempts it never earned and erase why it was struggling.
     */
    public function restore(int $id): bool
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', cancelled_at = NULL, cancelled_by = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             WHERE id = ? AND status = 'cancelled'",
            [$id]
        ) > 0;
    }

    /**
     * Put back in line every selected row that is actually cancelled.
     *
     * @param  array<int, int>  $ids
     * @return int Rows restored
     */
    public function restoreMany(array $ids): int
    {
        $ids = $this->sanitizeIds($ids);

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', cancelled_at = NULL, cancelled_by = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             WHERE status = 'cancelled' AND id IN ({$placeholders})",
            $ids
        );
    }

    /**
     * Queue a fresh copy of a delivered email.
     *
     * Only 'sent' rows qualify: a pending or failed row already has a copy in
     * line, and resending it would double up rather than help. The new row
     * remembers where it came from via resent_from_id, purely for the admin
     * view; the worker treats it like any other pending email.
     *
     * @return int|null The new row's ID, or null when the source is not sent
     */
    public function resend(int $id): ?int
    {
        $source = $this->database->query(
            "SELECT to_email, to_name, subject, body_html, body_text, tier, related_type, related_id, max_attempts
             FROM {$this->getTable()} WHERE id = ? AND status = 'sent'",
            [$id]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($source === false) {
            return null;
        }

        $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (to_email, to_name, subject, body_html, body_text, tier, related_type, related_id, max_attempts, resent_from_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $source['to_email'],
                $source['to_name'],
                $source['subject'],
                $source['body_html'],
                $source['body_text'],
                $source['tier'],
                $source['related_type'],
                $source['related_id'],
                $source['max_attempts'],
                $id,
            ]
        );

        return (int) $this->database->lastInsertId();
    }

    /**
     * Resend every selected row that is actually sent.
     *
     * @param  array<int, int>  $ids
     * @return int Rows resent
     */
    public function resendMany(array $ids): int
    {
        $resent = 0;

        foreach ($this->sanitizeIds($ids) as $id) {
            if ($this->resend($id) !== null) {
                $resent++;
            }
        }

        return $resent;
    }

    /**
     * Normalise a bulk id selection: positive integers only, no duplicates.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function sanitizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * The WHERE clause behind both the listing and every whole-filter action.
     *
     * Shared on purpose. The page tells an admin that 4,415 emails match, and
     * the action they then run has to reach exactly those; two copies of this
     * would drift the moment a filter is added to one of them.
     *
     * Positional placeholders, because the database binds by position: a named
     * set assembled in a different order than the SQL reads binds the wrong
     * value to the wrong column.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return array{0: string, 1: array<int, string>} The clause and its params, in SQL order
     */
    private function filterClause(array $filters): array
    {
        $where = [];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (($filters['search'] ?? '') !== '') {
            $where[] = 'to_email LIKE ?';
            $params[] = '%'.$filters['search'].'%';
        }

        if (($filters['tier'] ?? '') !== '') {
            $where[] = 'tier = ?';
            $params[] = $filters['tier'];
        }

        return [$where === [] ? '' : 'WHERE '.implode(' AND ', $where), $params];
    }

    /**
     * How many rows of each status match a filter.
     *
     * The bulk bar needs this to say what a whole-filter action will really
     * touch. statusCounts() above answers for the queue as a whole, which is
     * what the tiles want; this answers for what is on screen.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return array{pending: int, sending: int, sent: int, failed: int, cancelled: int}
     */
    public function statusCountsMatching(array $filters): array
    {
        [$whereSql, $params] = $this->filterClause($filters);

        $rows = $this->database->query(
            "SELECT status, COUNT(*) AS total FROM {$this->getTable()} {$whereSql} GROUP BY status",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $counts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Cancel every pending row matching a filter.
     *
     * The filter widens which rows are considered; it never widens which
     * statuses may be touched. A filter holding sent mail still only cancels
     * the pending part of it, exactly as the by-id version does.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return int Rows cancelled
     */
    public function cancelAllMatching(array $filters, int $cancelledBy): int
    {
        [$whereSql, $params] = $this->scopedClause($filters, 'pending');

        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = ?
             {$whereSql}",
            array_merge([$cancelledBy], $params)
        );
    }

    /**
     * Requeue every failed row matching a filter.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return int Rows requeued
     */
    public function retryAllMatching(array $filters): int
    {
        [$whereSql, $params] = $this->scopedClause($filters, 'failed');

        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', attempts = 0, last_error = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             {$whereSql}",
            $params
        );
    }

    /**
     * Put back every cancelled row matching a filter.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return int Rows restored
     */
    public function restoreAllMatching(array $filters): int
    {
        [$whereSql, $params] = $this->scopedClause($filters, 'cancelled');

        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', cancelled_at = NULL, cancelled_by = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             {$whereSql}",
            $params
        );
    }

    /**
     * Queue a fresh copy of every sent row matching a filter.
     *
     * The only one of these that writes rows rather than updating them, so it
     * is one INSERT ... SELECT rather than a loop: a filter matching thousands
     * would otherwise be thousands of round trips.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return int Copies queued
     */
    public function resendAllMatching(array $filters): int
    {
        [$whereSql, $params] = $this->scopedClause($filters, 'sent');

        return $this->database->execute(
            "INSERT INTO {$this->getTable()}
                (to_email, to_name, subject, body_html, body_text, tier, related_type, related_id, max_attempts, resent_from_id)
             SELECT to_email, to_name, subject, body_html, body_text, tier, related_type, related_id, max_attempts, id
             FROM {$this->getTable()}
             {$whereSql}",
            $params
        );
    }

    /**
     * A filter's WHERE clause with the status an action is scoped to folded in.
     *
     * @param  array{status?: string, search?: string, tier?: string}  $filters
     * @return array{0: string, 1: array<int, string>}
     */
    private function scopedClause(array $filters, string $status): array
    {
        [$whereSql, $params] = $this->filterClause($filters);
        $params[] = $status;

        return [$whereSql === '' ? 'WHERE status = ?' : $whereSql.' AND status = ?', $params];
    }

    /**
     * Delete delivered rows past their retention window.
     *
     * @param  int  $days  Keep sent rows for this many days
     * @return int Rows deleted
     */
    public function pruneSent(int $days = 30): int
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()}
             WHERE status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }

    /**
     * Delete rows that gave up delivering, once they are older than the retention window.
     *
     * @return int Rows deleted
     */
    public function pruneFailed(int $days = 30): int
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()}
             WHERE status = 'failed' AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }
}
