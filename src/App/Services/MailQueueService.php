<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\Mailable;
use App\Mail\QueuedMail;
use App\Models\MailQueueModel;
use Throwable;

/**
 * Queues outgoing email and drains it at a pace the transport tolerates.
 *
 * Any Mailable can be queued: enqueue() renders it immediately and stores the
 * result, so the worker never reconstructs a Mailable and a later change to a
 * template's constructor cannot break mail that is already waiting.
 *
 * Everything goes through here, including the mail somebody is waiting on.
 * Inline sending was the only mode with no retry, so a provider hiccup on a
 * password reset lost it outright. A reset queued at the critical tier and
 * drained by a worker running every minute still lands in seconds, and it
 * survives the transport being briefly unavailable.
 */
class MailQueueService
{
    /**
     * @param  array<string, mixed>  $config  The 'queue' section of config/mail.php
     */
    public function __construct(
        private MailQueueModel $queue,
        private MailService $mailer,
        private array $config = [],
    ) {}

    /**
     * Queue one email for later delivery.
     *
     * A Mailable can carry several recipients; each becomes its own row so one
     * bad address cannot hold up the others and the admin view can report per
     * recipient.
     *
     * @param  Mailable  $mailable  Email to queue
     * @param  string|null  $relatedType  What triggered it, e.g. 'post'
     * @param  int|null  $relatedId  ID of that record
     * @return int Number of rows queued
     */
    public function enqueue(Mailable $mailable, ?string $relatedType = null, ?int $relatedId = null): int
    {
        $html = $mailable->getBody();
        $text = $mailable->getTextBody();

        // A plain-text Mailable puts its content in the body, leaving textBody
        // null. Store it as the text part so the queue never sends an empty
        // HTML body and drops the message entirely.
        if (!$mailable->isHtml()) {
            $text = $html;
        }

        $queued = 0;

        foreach ($mailable->getTo() as $address => $name) {
            $this->queue->enqueue([
                'to_email' => $address,
                'to_name' => $name !== '' ? $name : null,
                'subject' => $mailable->getSubject(),
                'body_html' => $html,
                'body_text' => $text,
                'tier' => $mailable->getTier(),
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'max_attempts' => (int) ($this->config['max_attempts'] ?? 3),
            ]);

            $queued++;
        }

        return $queued;
    }

    /**
     * Send one batch of due mail.
     *
     * Pacing is the whole point: providers reject bursts, so sends are spaced
     * by the configured delay and capped per run. Each row is isolated, so one
     * refusal never costs the rest of the batch.
     *
     * @param  string  $tier  Restrict to one tier, '' for any
     * @return array{claimed: int, sent: int, retrying: int, failed: int, skipped: bool}
     */
    public function processBatch(string $tier = ''): array
    {
        $result = ['claimed' => 0, 'sent' => 0, 'retrying' => 0, 'failed' => 0, 'skipped' => false];

        // Leave the queue alone entirely when mail is switched off. Claiming
        // rows here would spend their attempts on a transport that was never
        // going to run, and MAIL_ENABLED=false is a deliberate setting, not an
        // outage. Queued mail simply waits until it is switched back on.
        if (!$this->mailer->enabled()) {
            $result['skipped'] = true;

            return $result;
        }

        $batchSize = (int) ($this->config['batch_size'] ?? 10);
        $delayMs = (int) ($this->config['delay_ms'] ?? 500);
        $backoff = (int) ($this->config['backoff_seconds'] ?? 60);

        $rows = $this->queue->claimBatch($batchSize, $tier);
        $result['claimed'] = count($rows);

        foreach ($rows as $index => $row) {
            $id = (int) $row['id'];

            // Space out sends, but never pay the delay after the final one.
            if ($index > 0 && $delayMs > 0) {
                usleep($delayMs * 1000);
            }

            try {
                if ($this->mailer->sendOrFail(new QueuedMail($row))) {
                    $this->queue->markSent($id);
                    $result['sent']++;
                }
            } catch (Throwable $e) {
                $status = $this->queue->markFailed($id, $e->getMessage(), $backoff);
                $result[$status === 'failed' ? 'failed' : 'retrying']++;
            }
        }

        return $result;
    }

    /**
     * Return claims abandoned by an interrupted worker to the pending pool.
     *
     * @return int Rows released
     */
    public function releaseStuck(): int
    {
        return $this->queue->releaseStuck((int) ($this->config['stuck_after_minutes'] ?? 15));
    }

    /**
     * Pending mail per tier, for the panel undrained tier warning.
     *
     * @return array<string, int>
     */
    public function pendingByTier(): array
    {
        return $this->queue->pendingByTier();
    }

    /**
     * Queue depth by status, for the worker summary and the admin panel.
     *
     * @return array{pending: int, sending: int, sent: int, failed: int}
     */
    public function statusCounts(): array
    {
        return $this->queue->statusCounts();
    }
}
