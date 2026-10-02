<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\TrafficRollupModel;
use App\Models\TrafficSaltModel;
use App\Services\Traffic\TrafficSettings;

/**
 * Rolls raw page views into the daily tables and deletes the salts of finished days.
 *
 * Rebuilds the last two days by default, since blogs ahead of or behind UTC are
 * still on yesterday or already on tomorrow. --days never reaches past raw
 * retention, or a day would be rebuilt from half its views.
 *
 * Usage: php cli traffic:aggregate [--days=2]
 */
class TrafficAggregateCommand implements SchedulableCommandInterface
{
    public function __construct(
        private TrafficRollupModel $rollups,
        private TrafficSaltModel $salts,
        private TrafficSettings $settings,
    ) {}

    public static function scheduleLabel(): string
    {
        return 'Aggregate traffic';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function argumentSchema(): array
    {
        return [
            'days' => [
                'type' => 'int',
                'label' => 'Rebuild this many past days',
                'min' => 1,
                'max' => 394,
                'default' => 2,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return int Exit code, 0 for success
     */
    public function handle(array $arguments = []): int
    {
        if (!$this->settings->aggregationEnabled()) {
            echo "Traffic aggregation is switched off in Settings. Nothing was rebuilt.\n";

            return 0;
        }

        $days = $this->days($arguments);
        if ($days === null) {
            return 1;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $now->modify("-{$days} days")->format('Y-m-d');

        $config = require ROOT_PATH.'/config/traffic.php';
        $written = $this->rollups->rebuildFrom($from, (int) $config['read_seconds'], (int) $config['bounce_seconds']);

        // After this, yesterday's anonymous visitors can never be linked to today's.
        $salts = $this->salts->deleteBefore($now->format('Y-m-d'));

        $this->settings->markAggregated($now->format('Y-m-d H:i:s'));

        echo "Rebuilt from {$from}: {$written['daily']} daily rows, {$written['dimensions']} breakdown rows, "
            ."{$written['site']} platform rows. Deleted {$salts} old salt(s).\n";

        return 0;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function days(array $arguments): ?int
    {
        $days = (int) ($arguments['days'] ?? 2);
        $retention = $this->settings->rawRetentionDays();

        if ($days < 1 || $days >= $retention) {
            echo '--days must be from 1 to '.($retention - 1)." (raw views are kept for {$retention} days).\n";

            return null;
        }

        return $days;
    }
}
