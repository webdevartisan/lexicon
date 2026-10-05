<?php

declare(strict_types=1);

namespace App\Services\Traffic;

use App\Models\SettingModel;
use UnexpectedValueException;

/**
 * Platform-wide traffic settings from the settings table. An unsaved key
 * reads as its default, and a stored value outside its bounds throws.
 */
class TrafficSettings
{
    /** Setting name => [default, lowest allowed value, highest allowed value]. */
    public const KEYS = [
        'traffic.enabled' => [1, 0, 1],
        'traffic.aggregation_enabled' => [1, 0, 1],
        'traffic.raw_retention_days' => [30, 1, 395],
    ];

    public const EXTRA_BOT_PATTERNS = 'traffic.extra_bot_patterns';

    /** When the last aggregation run started, UTC. Written by traffic:aggregate. */
    public const AGGREGATED_AT = 'traffic.aggregated_at';

    /** When the last run that rebuilt every blog started, UTC. */
    public const FULLY_AGGREGATED_AT = 'traffic.fully_aggregated_at';

    /** The ISO week the last weekly digest covered, e.g. 2026-W40. */
    public const DIGEST_WEEK = 'traffic.digest_week';

    /** Runs in between rebuild only the blogs whose views changed. */
    private const FULL_REBUILD_EVERY_MINUTES = 60;

    /** Aggregation runs every five minutes, so past this the numbers are behind. */
    private const DELAYED_AFTER_MINUTES = 20;

    public function __construct(private SettingModel $settings) {}

    /** The kill switch: off means the beacon endpoint records nothing. */
    public function enabled(): bool
    {
        return $this->read('traffic.enabled') === 1;
    }

    public function aggregationEnabled(): bool
    {
        return $this->read('traffic.aggregation_enabled') === 1;
    }

    public function rawRetentionDays(): int
    {
        return $this->read('traffic.raw_retention_days');
    }

    /**
     * Crawler patterns an administrator added on top of config/traffic.php, one per line.
     *
     * @return list<string>
     */
    public function extraBotPatterns(): array
    {
        $raw = (string) ($this->settings->get(self::EXTRA_BOT_PATTERNS) ?? '');

        return array_values(array_filter(array_map(
            static fn (string $line): string => strtolower(trim($line)),
            preg_split('/\R/', $raw) ?: []
        ), static fn (string $line): bool => $line !== ''));
    }

    public function aggregatedAt(): ?string
    {
        return $this->settings->get(self::AGGREGATED_AT);
    }

    /**
     * Whether the dashboards are showing stale numbers: totals paused, never
     * built, or not rebuilt for a while.
     */
    public function aggregationDelayed(): bool
    {
        $aggregatedAt = $this->aggregatedAt();

        if ($aggregatedAt === null || !$this->aggregationEnabled()) {
            return true;
        }

        $utc = new \DateTimeZone('UTC');

        return new \DateTimeImmutable($aggregatedAt, $utc)
            < new \DateTimeImmutable('-'.self::DELAYED_AFTER_MINUTES.' minutes', $utc);
    }

    /**
     * @param  bool  $full  Whether every blog was rebuilt
     */
    public function markAggregated(string $utcTimestamp, bool $full = true): void
    {
        $this->settings->set(self::AGGREGATED_AT, $utcTimestamp);

        if ($full) {
            $this->settings->set(self::FULLY_AGGREGATED_AT, $utcTimestamp);
        }
    }

    public function digestWeek(): ?string
    {
        return $this->settings->get(self::DIGEST_WEEK);
    }

    public function markDigestSent(string $isoWeek): void
    {
        $this->settings->set(self::DIGEST_WEEK, $isoWeek);
    }

    /**
     * Whether this run has to rebuild every blog: none has yet, or the last was
     * an hour ago. That one catches what a partial run can't see, such as a
     * visitor flagged as a script on a blog they didn't view since.
     */
    public function fullRebuildDue(): bool
    {
        $last = $this->settings->get(self::FULLY_AGGREGATED_AT);

        if ($last === null) {
            return true;
        }

        $utc = new \DateTimeZone('UTC');

        return new \DateTimeImmutable($last, $utc)
            <= new \DateTimeImmutable('-'.self::FULL_REBUILD_EVERY_MINUTES.' minutes', $utc);
    }

    /**
     * What is stored for each numeric key, or its default. Never throws, so the
     * settings page can open and show a bad value that needs fixing.
     *
     * @return array<string, string>
     */
    public function storedValues(): array
    {
        $values = [];

        foreach (self::KEYS as $name => [$default]) {
            $values[$name] = $this->settings->get($name) ?? (string) $default;
        }

        $values[self::EXTRA_BOT_PATTERNS] = (string) ($this->settings->get(self::EXTRA_BOT_PATTERNS) ?? '');

        return $values;
    }

    /**
     * @throws UnexpectedValueException When the value is outside its bounds
     */
    public function save(string $name, int $value): void
    {
        [, $floor, $ceiling] = self::KEYS[$name];

        if ($value < $floor || $value > $ceiling) {
            throw new UnexpectedValueException("{$name} must be between {$floor} and {$ceiling}, not {$value}.");
        }

        $this->settings->set($name, (string) $value);
    }

    public function saveExtraBotPatterns(string $patterns): void
    {
        $this->settings->set(self::EXTRA_BOT_PATTERNS, $patterns);
    }

    private function read(string $name): int
    {
        [$default, $floor, $ceiling] = self::KEYS[$name];
        $raw = $this->settings->get($name);

        if ($raw === null) {
            return $default;
        }

        if (!ctype_digit($raw) || (int) $raw < $floor || (int) $raw > $ceiling) {
            throw new UnexpectedValueException(
                "The traffic setting {$name} holds '{$raw}', which is not a whole number from {$floor} to {$ceiling}. "
                .'Save it again from the Settings page.'
            );
        }

        return (int) $raw;
    }
}
