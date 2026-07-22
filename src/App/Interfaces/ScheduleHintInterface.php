<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Lets a command explain what its schedule actually adds up to.
 *
 * Interval and batch size multiply, and the result is not obvious. Draining
 * mail ten at a time every ten minutes reads as frequent but works out to
 * sixty an hour, which is days of waiting for a large subscriber list. The
 * scheduler itself knows nothing about email, so the command supplies the
 * sentence and the form shows it live as the picker changes.
 *
 * Optional on purpose. Commands with no meaningful throughput skip it.
 */
interface ScheduleHintInterface
{
    /**
     * Describe the throughput of a given schedule in plain words.
     *
     * @param  array<string, mixed>  $arguments  Values from the task row
     * @param  int  $runsPerHour  How often the picker will fire this task
     * @return string|null Sentence for the form, or null when there is nothing useful to say
     */
    public static function scheduleHint(array $arguments, int $runsPerHour): ?string;
}
