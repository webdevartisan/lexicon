<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Interfaces\ScheduleHintInterface;
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
class MailQueueWorkCommand implements SchedulableCommandInterface, ScheduleHintInterface
{
    public function __construct(
        private MailQueueService $mailQueue,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Outbound mail';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [
            'tier' => [
                'type' => 'enum',
                'label' => 'Tier',
                'values' => ['critical', 'standard', 'bulk'],
                'default' => 'standard',
                'required' => true,
            ],
        ];
    }

    /**
     * Spell out what a given schedule works out to in practice.
     *
     * Interval and batch size multiply, and the answer surprises people.
     * Ten at a time every ten minutes reads as often but comes to sixty an
     * hour, which is days of waiting for a decent sized subscriber list.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function scheduleHint(array $arguments, int $runsPerHour): ?string
    {
        if ($runsPerHour < 1) {
            return null;
        }

        $config = require ROOT_PATH.'/config/mail.php';
        $batchSize = (int) ($config['queue']['batch_size'] ?? 10);
        $perHour = $batchSize * $runsPerHour;

        if ($perHour < 1) {
            return null;
        }

        $tier = (string) ($arguments['tier'] ?? 'standard');

        // Only the bulk tier ever sees a list big enough for the projection to
        // mean anything. On the others it would just be noise, and the number
        // that actually matters there is how long someone waits.
        if ($tier === 'bulk') {
            $hoursForList = (int) ceil(5000 / $perHour);

            return "About {$perHour} emails an hour. A 5,000 recipient send would take roughly {$hoursForList} hours to go out.";
        }

        $waitMinutes = (int) ceil(60 / $runsPerHour);

        return "About {$perHour} emails an hour, and a message waits up to {$waitMinutes} minute(s) before a worker picks it up.";
    }

    /**
     * Drain one batch.
     *
     * @param  array<string, mixed>  $arguments  Accepts 'tier' to drain one tier only
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(array $arguments = []): int
    {
        $tier = (string) ($arguments['tier'] ?? '');

        try {
            $start = microtime(true);

            // Runs before claiming so mail abandoned by an interrupted worker
            // rejoins this batch instead of waiting for the next tick.
            $released = $this->mailQueue->releaseStuck();

            if ($released > 0) {
                echo "Reclaimed {$released} row(s) from an interrupted run.\n";
            }

            $result = $this->mailQueue->processBatch($tier);

            if ($result['skipped']) {
                echo "Mail is disabled (MAIL_ENABLED=false); queue left untouched.\n";

                return 0;
            }

            if ($result['claimed'] === 0) {
                echo 'No mail due for delivery'.($tier !== '' ? " in the {$tier} tier" : '').".\n";

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
