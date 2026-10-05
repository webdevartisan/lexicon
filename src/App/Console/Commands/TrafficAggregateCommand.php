<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Interfaces\SchedulableCommandInterface;
use App\Models\TrafficHitModel;
use App\Models\TrafficRollupModel;
use App\Models\TrafficSaltModel;
use App\Services\Traffic\TrafficSettings;

/**
 * Rolls raw page views into the daily tables and deletes the salts of finished days.
 *
 * Rebuilds the last two days by default, since blogs ahead of or behind UTC are
 * still on yesterday or already on tomorrow. --days never reaches past raw
 * retention, or a day would be rebuilt from half its views. Between hourly full
 * runs, only blogs with new views or leave pings since the last run are rebuilt.
 * Each run also flags script-like visitors and adds the next days' partitions.
 *
 * Usage: php cli traffic:aggregate [--days=2] [--rebuild=changed|full]
 */
class TrafficAggregateCommand implements SchedulableCommandInterface
{
    /** Partitions kept ready ahead of today, so a view never lands in the catch-all. */
    private const PARTITION_DAYS_AHEAD = 3;

    public function __construct(
        private TrafficRollupModel $rollups,
        private TrafficHitModel $hits,
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
            'rebuild' => [
                'type' => 'enum',
                'label' => 'Blogs to rebuild',
                'values' => ['changed', 'full'],
                'default' => 'changed',
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

        $config = require ROOT_PATH.'/config/traffic.php';
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $now->modify("-{$days} days")->format('Y-m-d');

        $partitions = $this->hits->addDayPartitions(self::PARTITION_DAYS_AHEAD);
        $suspect = $this->hits->markSuspects($from, (int) $config['script_min_views']);

        $blogIds = $this->changedBlogs($arguments);
        $written = $this->rollups->rebuildFrom($from, (int) $config['read_seconds'], (int) $config['bounce_seconds'], $blogIds);

        // After this, yesterday's anonymous visitors can never be linked to today's.
        $salts = $this->salts->deleteBefore($now->format('Y-m-d'));

        $this->settings->markAggregated($now->format('Y-m-d H:i:s'), $blogIds === null);

        $which = $blogIds === null ? 'every blog' : count($blogIds).' changed blog(s)';
        echo "Rebuilt from {$from} for {$which}: {$written['daily']} daily rows, {$written['dimensions']} breakdown rows. "
            ."{$suspect} script-like view(s) left out. Added {$partitions} day partition(s). Deleted {$salts} old salt(s).\n";

        return 0;
    }

    /**
     * The blogs to rebuild, or null for all of them.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<int>|null
     */
    private function changedBlogs(array $arguments): ?array
    {
        $since = $this->settings->aggregatedAt();

        if (($arguments['rebuild'] ?? 'changed') === 'full' || $since === null || $this->settings->fullRebuildDue()) {
            return null;
        }

        // A minute's overlap, so a view written while the last run was reading isn't missed.
        $overlap = (new \DateTimeImmutable($since, new \DateTimeZone('UTC')))->modify('-1 minute');

        return $this->hits->blogsChangedSince($overlap->format('Y-m-d H:i:s'));
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
