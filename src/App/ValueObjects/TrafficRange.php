<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * The date range a Traffic page shows, in the blog's timezone, and what it is
 * compared with. A range that fails the checks falls back to the default and
 * sets $rejected.
 */
final class TrafficRange
{
    /** Preset => days covered, today included. */
    public const PRESETS = ['7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365];

    /** Presets that follow the calendar rather than counting back from today. */
    public const CALENDAR = ['today', 'yesterday', 'month', 'last_month'];

    public const DEFAULT = '30d';

    public const MAX_DAYS = 366;

    /** Compared with the days just before, or with the same days a year earlier. */
    public const COMPARE_PREVIOUS = 'previous';

    public const COMPARE_YEAR = 'year';

    private function __construct(
        public readonly string $preset,
        public readonly \DateTimeImmutable $from,
        public readonly \DateTimeImmutable $to,
        public readonly bool $rejected = false,
        public readonly string $compare = self::COMPARE_PREVIOUS,
    ) {}

    /**
     * @param  array<string, mixed>  $query  The request's query parameters
     */
    public static function fromQuery(array $query, string $timezone): self
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone($timezone));
        $preset = $query['range'] ?? self::DEFAULT;
        $compare = ($query['compare'] ?? null) === self::COMPARE_YEAR ? self::COMPARE_YEAR : self::COMPARE_PREVIOUS;

        if (is_string($preset) && isset(self::PRESETS[$preset])) {
            return self::preset($preset, $today, compare: $compare);
        }

        if (is_string($preset) && in_array($preset, self::CALENDAR, true)) {
            return self::calendar($preset, $today, $compare);
        }

        if ($preset === 'custom') {
            $custom = self::custom($query['from'] ?? null, $query['to'] ?? null, $today, $compare);

            if ($custom !== null) {
                return $custom;
            }
        }

        return self::preset(self::DEFAULT, $today, rejected: true, compare: $compare);
    }

    /**
     * What this range is compared with: the same number of days immediately
     * before, or the same days a year earlier.
     */
    public function previous(): self
    {
        if ($this->compare === self::COMPARE_YEAR) {
            return new self($this->preset, $this->from->modify('-1 year'), $this->to->modify('-1 year'), compare: $this->compare);
        }

        $days = $this->days();
        $to = $this->from->modify('-1 day');

        return new self($this->preset, $to->modify('-'.($days - 1).' days'), $to, compare: $this->compare);
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
     * Whether every day of the range is still in raw views kept for $retentionDays.
     */
    public function withinRaw(int $retentionDays, \DateTimeImmutable $today): bool
    {
        return $this->from >= $today->modify('-'.($retentionDays - 1).' days');
    }

    /**
     * Names the range and its comparison in cache keys.
     */
    public function key(): string
    {
        return $this->fromDate().':'.$this->toDate().':'.$this->compare;
    }

    /**
     * Query parameters that reproduce this range, for links and the CSV export.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        $query = $this->preset === 'custom'
            ? ['range' => 'custom', 'from' => $this->fromDate(), 'to' => $this->toDate()]
            : ['range' => $this->preset];

        if ($this->compare === self::COMPARE_YEAR) {
            $query['compare'] = self::COMPARE_YEAR;
        }

        return $query;
    }

    private static function preset(
        string $preset,
        \DateTimeImmutable $today,
        bool $rejected = false,
        string $compare = self::COMPARE_PREVIOUS
    ): self {
        $days = self::PRESETS[$preset];

        return new self($preset, $today->modify('-'.($days - 1).' days'), $today, $rejected, $compare);
    }

    private static function calendar(string $preset, \DateTimeImmutable $today, string $compare): self
    {
        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->modify('-1 day'), $today->modify('-1 day')],
            'month' => [$today->modify('first day of this month'), $today],
            default => [$today->modify('first day of last month'), $today->modify('last day of last month')],
        };

        return new self($preset, $from, $to, compare: $compare);
    }

    private static function custom(mixed $from, mixed $to, \DateTimeImmutable $today, string $compare): ?self
    {
        $start = self::date($from, $today->getTimezone());
        $end = self::date($to, $today->getTimezone());

        if ($start === null || $end === null || $start > $end || $end > $today) {
            return null;
        }

        if ((int) $start->diff($end)->days + 1 > self::MAX_DAYS) {
            return null;
        }

        return new self('custom', $start, $end, compare: $compare);
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
