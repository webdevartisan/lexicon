<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Works out when a task should next run.
 *
 * Nothing here touches the database or the clock beyond what it is handed, so
 * the awkward cases can be tested directly.
 *
 * Two rules matter. Occurrences are always computed forward from the moment
 * given, so a scheduler that was down for hours quietly skips what it missed
 * instead of firing every one of them at once on recovery. And the answer is
 * derived from the rule rather than from now plus an interval, so a task that
 * takes forty seconds does not drift a little later on every run until an
 * every five minutes job is firing every six.
 *
 * Daily and hourly times are wall clock in the task own timezone, converted to
 * UTC only at the end. Storing the converted time instead would look fine until
 * a daylight saving change moved every schedule by an hour.
 */
class ScheduleCalculator
{
    /** Format the rest of the app and the database agree on. */
    public const FORMAT = 'Y-m-d H:i:s';

    /**
     * Next run for a task, as a UTC timestamp string.
     *
     * @param  array<string, mixed>  $task  Row from scheduled_tasks
     * @param  DateTimeImmutable|null  $after  Compute the first occurrence after this, defaults to now
     * @return string|null UTC 'Y-m-d H:i:s', or null when the task should not be scheduled
     */
    public function nextRunAt(array $task, ?DateTimeImmutable $after = null): ?string
    {
        if (empty($task['is_active'])) {
            return null;
        }

        $after = $after ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $zone = $this->zoneFor((string) ($task['schedule_timezone'] ?? 'UTC'));

        // Reason about the rule in its own zone, then hand back UTC.
        $local = $after->setTimezone($zone);

        $next = match ((string) $task['schedule_type']) {
            'every_minute' => $this->nextMinute($local),
            'every_n_minutes' => $this->nextInterval($local, max(1, (int) ($task['interval_minutes'] ?? 5))),
            'hourly' => $this->nextHourly($local, (int) ($task['minute_of_hour'] ?? 0)),
            'daily' => $this->nextDaily($local, (string) ($task['run_at'] ?? '00:00:00')),
            default => null,
        };

        if ($next === null) {
            return null;
        }

        return $next->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    /**
     * How many times an hour a schedule fires, for the throughput hint.
     *
     * @param  array<string, mixed>  $task  Row or draft from the form
     */
    public function runsPerHour(array $task): int
    {
        return match ((string) $task['schedule_type']) {
            'every_minute' => 60,
            'every_n_minutes' => max(1, (int) floor(60 / max(1, (int) ($task['interval_minutes'] ?? 5)))),
            'hourly' => 1,
            default => 0,
        };
    }

    /**
     * Top of the next minute.
     */
    private function nextMinute(DateTimeImmutable $local): DateTimeImmutable
    {
        return $local->setTime((int) $local->format('G'), (int) $local->format('i'), 0)
            ->modify('+1 minute');
    }

    /**
     * Next slot on an N minute grid anchored to midnight.
     *
     * Anchoring rather than adding N to the current time is what stops the
     * schedule sliding. An every ten minutes task lands on :00, :10, :20 and
     * stays there no matter how long a run took.
     */
    private function nextInterval(DateTimeImmutable $local, int $minutes): DateTimeImmutable
    {
        $midnight = $local->setTime(0, 0, 0);
        $elapsed = (int) floor(($local->getTimestamp() - $midnight->getTimestamp()) / 60);
        $slot = ((int) floor($elapsed / $minutes) + 1) * $minutes;

        // Rolls into tomorrow on its own when the last slot of the day passes.
        return $midnight->modify("+{$slot} minutes");
    }

    /**
     * Next time the given minute of the hour comes round.
     */
    private function nextHourly(DateTimeImmutable $local, int $minute): DateTimeImmutable
    {
        $minute = max(0, min(59, $minute));
        $candidate = $local->setTime((int) $local->format('G'), $minute, 0);

        if ($candidate <= $local) {
            $candidate = $candidate->modify('+1 hour');
        }

        return $candidate;
    }

    /**
     * Next time the given wall clock time comes round.
     *
     * On a spring forward day a time inside the skipped hour does not exist,
     * and PHP rolls it to the following hour. The task runs once either way,
     * which is the behaviour that matters.
     */
    private function nextDaily(DateTimeImmutable $local, string $runAt): DateTimeImmutable
    {
        [$hour, $minute] = $this->parseTime($runAt);

        $candidate = $local->setTime($hour, $minute, 0);

        if ($candidate <= $local) {
            $candidate = $candidate->modify('+1 day')->setTime($hour, $minute, 0);
        }

        return $candidate;
    }

    /**
     * Split a stored TIME into hour and minute.
     *
     * @return array{0: int, 1: int}
     */
    private function parseTime(string $runAt): array
    {
        $parts = explode(':', $runAt);

        return [
            max(0, min(23, (int) ($parts[0] ?? 0))),
            max(0, min(59, (int) ($parts[1] ?? 0))),
        ];
    }

    /**
     * Resolve a stored zone, falling back to UTC.
     *
     * A task saved under a zone the server no longer recognises would
     * otherwise throw inside the dispatcher and take the whole tick with it.
     */
    private function zoneFor(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (\Exception) {
            return new DateTimeZone('UTC');
        }
    }
}
