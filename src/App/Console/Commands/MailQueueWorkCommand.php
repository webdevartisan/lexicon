<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MailQueueService;
use Throwable;

/**
 * CLI command that delivers one batch of queued email.
 *
 * Sending happens here rather than in the web request that produced the mail,
 * so a large fan-out is spread across cron ticks instead of being cut off
 * partway by a provider's per-second limit. Batch size and spacing come from
 * the queue section of config/mail.php.
 *
 * Usage: php cli mail:queue-work
 * Cron:  * * * * * cd /var/www/html && php cli mail:queue-work >> /var/log/mail-queue.log 2>&1
 */
class MailQueueWorkCommand
{
    public function __construct(
        private MailQueueService $mailQueue,
    ) {}

    /**
     * Drain one batch.
     *
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(): int
    {
        try {
            $start = microtime(true);

            // Runs before claiming so mail abandoned by an interrupted worker
            // rejoins this batch instead of waiting for the next tick.
            $released = $this->mailQueue->releaseStuck();

            if ($released > 0) {
                echo "Reclaimed {$released} row(s) from an interrupted run.\n";
            }

            $result = $this->mailQueue->processBatch();

            if ($result['skipped']) {
                echo "Mail is disabled (MAIL_ENABLED=false); queue left untouched.\n";

                return 0;
            }

            if ($result['claimed'] === 0) {
                echo "No mail due for delivery.\n";

                return 0;
            }

            $duration = round((microtime(true) - $start) * 1000, 2);

            echo "Processed {$result['claimed']} queued email(s) in {$duration}ms\n";
            echo "  sent:     {$result['sent']}\n";
            echo "  retrying: {$result['retrying']}\n";
            echo "  failed:   {$result['failed']}\n";

            $counts = $this->mailQueue->statusCounts();
            echo "Queue now: {$counts['pending']} pending, {$counts['failed']} failed\n";

            return 0;
        } catch (Throwable $e) {
            echo "✗ Error processing the mail queue: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }
}
