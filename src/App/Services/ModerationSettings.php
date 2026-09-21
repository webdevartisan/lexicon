<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingModel;
use UnexpectedValueException;

/**
 * The site-wide safeguards that sit in front of every automatic moderation
 * action, read from the settings table.
 *
 * A key nobody has saved yet reads as its default below. A key holding
 * something other than a whole number within its bounds is refused loudly: these
 * values decide whether the system may act against a person on its own, and
 * quietly substituting one would hide exactly the kind of mistake that matters.
 */
class ModerationSettings
{
    /** Setting name => [default, lowest allowed value, highest allowed value]. */
    public const KEYS = [
        'moderation.min_reporter_age_days' => [7, 0, 365],
        'moderation.unfounded_limit' => [3, 1, 100],
        'moderation.unfounded_window_days' => [90, 1, 3650],
        'moderation.burst_window_minutes' => [10, 0, 1440],
        'moderation.auto_suspension_cooldown_days' => [30, 0, 3650],
        'moderation.auto_warn_reporters' => [1, 0, 1],
    ];

    public function __construct(private SettingModel $settings) {}

    /** Accounts younger than this can report, but their reports do not count toward a rule. */
    public function minReporterAgeDays(): int
    {
        return $this->read('moderation.min_reporter_age_days');
    }

    /** This many unfounded reports within the window and a person's reports stop counting. */
    public function unfoundedLimit(): int
    {
        return $this->read('moderation.unfounded_limit');
    }

    public function unfoundedWindowDays(): int
    {
        return $this->read('moderation.unfounded_window_days');
    }

    /** Counted reports all arriving inside this window hold an automatic action for a person. */
    public function burstWindowMinutes(): int
    {
        return $this->read('moderation.burst_window_minutes');
    }

    /** After a rule suspends someone, it may not do so again on its own for this long. */
    public function autoSuspensionCooldownDays(): int
    {
        return $this->read('moderation.auto_suspension_cooldown_days');
    }

    /** Warn a reporter by themselves the first time they reach the unfounded limit. */
    public function autoWarnReporters(): bool
    {
        return $this->read('moderation.auto_warn_reporters') === 1;
    }

    /**
     * Every safeguard's current value, keyed by setting name.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        $values = [];

        foreach (array_keys(self::KEYS) as $name) {
            $values[$name] = $this->read($name);
        }

        return $values;
    }

    /**
     * What is stored for each safeguard, as text, or its default when nothing
     * is. Unlike all(), a bad stored value comes back as it is instead of
     * throwing, so the settings page can still open and show what to fix.
     *
     * @return array<string, string>
     */
    public function storedValues(): array
    {
        $values = [];

        foreach (self::KEYS as $name => [$default]) {
            $values[$name] = $this->settings->get($name) ?? (string) $default;
        }

        return $values;
    }

    /**
     * Save one safeguard. The caller has already checked the value against
     * its bounds; checking again here keeps a bad value out of the table.
     *
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

    private function read(string $name): int
    {
        [$default, $floor, $ceiling] = self::KEYS[$name];
        $raw = $this->settings->get($name);

        if ($raw === null) {
            return $default;
        }

        if (!ctype_digit($raw) || (int) $raw < $floor || (int) $raw > $ceiling) {
            throw new UnexpectedValueException(
                "The moderation setting {$name} holds '{$raw}', which is not a whole number from {$floor} to {$ceiling}. "
                .'Save it again from the moderation settings page.'
            );
        }

        return (int) $raw;
    }
}
