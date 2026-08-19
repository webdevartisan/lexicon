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
     * @return array{pending: int, sending: int, sent: int, failed: int}
     */
    public function statusCounts(): array
    {
        $rows = $this->database->query(
            "SELECT status, COUNT(*) AS total FROM {$this->getTable()} GROUP BY status"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $counts = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];

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

        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where[] = 'to_email LIKE :search';
            $params[':search'] = '%'.$search.'%';
        }

        if ($tier !== '') {
            $where[] = 'tier = :tier';
            $params[':tier'] = $tier;
        }

        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

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
        $sql = "SELECT id, to_email, to_name, subject, status, tier, attempts, max_attempts,
                       TIMESTAMPDIFF(SECOND, NOW(), next_attempt_at) AS due_in_seconds,
                       last_error, related_type, related_id, next_attempt_at, sent_at, created_at
                FROM {$this->getTable()}
                {$whereSql}
                ORDER BY {$orderBy}
                LIMIT :limit OFFSET :offset";

        $params[':limit'] = $perPage;
        $params[':offset'] = ($page - 1) * $perPage;

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
     * Put every failed row back in line for another attempt.
     *
     * @return int Rows requeued
     */
    public function retryAllFailed(): int
    {
        return $this->database->execute(
            "UPDATE {$this->getTable()}
             SET status = 'pending', attempts = 0, last_error = NULL,
                 claim_token = NULL, next_attempt_at = NOW()
             WHERE status = 'failed'"
        );
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
}
