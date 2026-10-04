<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * The date range a Traffic page shows, in the blog's timezone. A range that
 * fails the checks falls back to the default and sets $rejected.
 */
final class TrafficRange
{
    /** Preset => days covered, today included. */
    public const PRESETS = ['7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365];

    public const DEFAULT = '30d';

    public const MAX_DAYS = 366;

    private function __construct(
        public readonly string $preset,
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly bool $rejected = false,
    ) {}

    /**
     * @param  array<string, mixed>  $query  The request's query parameters
     */
    public static function fromQuery(array $query, string $timezone): self
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone($timezone));
        $preset = is_string($query['range'] ?? null) ? $query['range'] : self::DEFAULT;

        if (isset(self::PRESETS[$preset])) {
            return self::preset($preset, $today);
        }

        if ($preset === 'custom') {
            $custom = self::custom($query['from'] ?? null, $query['to'] ?? null, $today);

            if ($custom !== null) {
                return $custom;
            }
        }

        return self::preset(self::DEFAULT, $today, rejected: true);
    }

    /**
     * The same number of days immediately before this range.
     */
    public function previous(): self
    {
        $days = $this->days();
        $to = $this->from->modify('-1 day');

        return new self($this->preset, $to->modify('-'.($days - 1).' days'), $to);
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /**
     * Every day in the range as Y-m-d, oldest first.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from; $day <= $this->to; $day = $day->modify('+1 day')) {
            $dates[] = $day->format('Y-m-d');
        }

        return $dates;
    }

    public function fromDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    public function toDate(): string
    {
        return $this->to->format('Y-m-d');
    }

    /**
     * Query parameters that reproduce this range, for links and the CSV export.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        if ($this->preset === 'custom') {
            return ['range' => 'custom', 'from' => $this->fromDate(), 'to' => $this->toDate()];
        }

        return ['range' => $this->preset];
    }

    private static function preset(string $preset, \DateTimeImmutable $today, bool $rejected = false): self
    {
        $days = self::PRESETS[$preset];

        return new self($preset, $today->modify('-'.($days - 1).' days'), $today, $rejected);
    }

    private static function custom(mixed $from, mixed $to, \DateTimeImmutable $today): ?self
    {
        $start = self::date($from, $today->getTimezone());
        $end = self::date($to, $today->getTimezone());

        if ($start === null || $end === null || $start > $end || $end > $today) {
            return null;
        }

        if ((int) $start->diff($end)->days + 1 > self::MAX_DAYS) {
            return null;
        }

        return new self('custom', $start, $end);
    }

    private static function date(mixed $value, \DateTimeZone $zone): ?\DateTimeImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);

        // Rejects rollovers such as 2026-02-31, which PHP would read as March 3rd.
        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
