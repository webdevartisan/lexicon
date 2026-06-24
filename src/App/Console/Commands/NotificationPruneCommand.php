<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationModel;

/**
 * CLI command to prune stale notification rows.
 *
 * Two passes per the retention policy:
 *   1. Read notifications older than 30 days.
 *   2. Any notification (read or unread) older than 90 days.
 *
 * Usage: php cli notifications:prune
 * Cron:  0 3 * * * cd /var/www/html && php cli notifications:prune >> /var/log/notifications-prune.log 2>&1
 */
class NotificationPruneCommand
{
    public function __construct(
        private NotificationModel $notifications,
    ) {}

    /**
     * Execute the prune.
     *
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(): int
    {
        try {
            $start = microtime(true);

            echo "Starting notification pruning...\n";

            $result = $this->notifications->pruneStale();

            $duration = round((microtime(true) - $start) * 1000, 2);

            echo "✓ Pruning complete in {$duration}ms\n";
            echo "  Read >30 days  — deleted: {$result['read_pruned']}\n";
            echo "  Any  >90 days  — deleted: {$result['old_pruned']}\n";

            return 0;
        } catch (\Throwable $e) {
            echo "✗ Error during notification pruning: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";

            return 1;
        }
    }
}
