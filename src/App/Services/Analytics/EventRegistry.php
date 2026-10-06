<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * The events that can be stored and the props each one may carry, from
 * config/analytics.php. Names and props are never taken on trust: anything
 * unlisted is refused, and every value is typed and capped.
 */
final class EventRegistry
{
    /** Breakdowns read from the event's own columns rather than its props. */
    public const COLUMN_BREAKDOWNS = [
        'post' => 'post_id',
        'channel' => 'channel',
        'source' => 'referrer_source',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
    ];

    /**
     * @param  array<string, array<string, mixed>>  $events  The events list from config/analytics.php
     */
    public function __construct(private array $events) {}

    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * Whether the page script may send this event. Server events can't be forged from a page.
     */
    public function fromBeacon(string $name): bool
    {
        return ($this->events[$name]['source'] ?? null) === 'beacon';
    }

    public function needsView(string $name): bool
    {
        return (bool) ($this->events[$name]['needs_view'] ?? false);
    }

    public function oncePerVisit(string $name): bool
    {
        return (bool) ($this->events[$name]['once_per_visit'] ?? false);
    }

    /**
     * @return list<string> Events rolled into analytics_daily_events
     */
    public function kept(): array
    {
        return array_keys(array_filter($this->events, static fn (array $event): bool => !empty($event['kept'])));
    }

    /**
     * @return list<string>
     */
    public function breakdowns(string $name): array
    {
        return $this->events[$this->known($name)]['breakdowns'] ?? [];
    }

    /**
     * The props kept for this event, or null when any of them is unknown or
     * invalid. One bad prop refuses the whole event rather than storing half of it.
     *
     * @param  array<mixed>  $props
     * @return array<string, string|int>|null
     */
    public function clean(string $name, array $props): ?array
    {
        $allowed = $this->events[$this->known($name)]['props'] ?? [];
        $clean = [];

        foreach ($props as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                return null;
            }

            $typed = self::typed($allowed[$key], $value);
            if ($typed === null) {
                return null;
            }

            $clean[$key] = $typed;
        }

        return $clean;
    }

    private function known(string $name): string
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Unknown analytics event '{$name}'.");
        }

        return $name;
    }

    /**
     * @param  array{0: string, 1?: mixed, 2?: int}  $rule
     */
    private static function typed(array $rule, mixed $value): string|int|null
    {
        return match ($rule[0]) {
            'string' => is_string($value) && $value !== '' && mb_strlen($value) <= (int) $rule[1] ? $value : null,
            'int' => is_int($value) && $value >= (int) $rule[1] && $value <= (int) $rule[2] ? $value : null,
            'enum' => is_string($value) && in_array($value, (array) $rule[1], true) ? $value : null,
            default => throw new \LogicException("Unknown prop type '{$rule[0]}' in config/analytics.php."),
        };
    }
}
