<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModerationCategoryModel;
use Framework\Database;

/**
 * Checks and saves the moderation settings screen: each report reason's rule
 * and the site-wide safeguards.
 *
 * The checks mirror what ModerationRuleEngine enforces when a rule fires, so
 * the screen refuses a setting up front instead of saving one the engine would
 * quietly hold back. Every save is audited with the old and new values.
 */
class ModerationConfigService
{
    public const LABEL_MAX = 60;

    public const DESCRIPTION_MAX = 255;

    public const THRESHOLD_MAX = 1000;

    // A rule suspends for at most a year and never permanently: permanent
    // suspension is a decision for a person.
    public const SUSPENSION_HOURS_MAX = 8760;

    /** What each safeguard is called on the settings page and in its errors. */
    public const SAFEGUARD_LABELS = [
        'moderation.min_reporter_age_days' => 'Minimum account age, in days',
        'moderation.unfounded_limit' => 'Unfounded reports before they stop counting',
        'moderation.unfounded_window_days' => 'Unfounded report window, in days',
        'moderation.burst_window_minutes' => 'Burst window, in minutes',
        'moderation.auto_suspension_cooldown_days' => 'Days between automatic suspensions',
        'moderation.auto_warn_reporters' => 'Warn reporters automatically',
    ];

    public function __construct(
        private Database $database,
        private ModerationCategoryModel $categories,
        private ModerationSettings $settings,
        private AuditService $audit,
    ) {}

    /**
     * Check a report reason's form and work out the row to save.
     *
     * @param  array<string, mixed>  $current  The category as stored
     * @param  array<string, mixed>  $input  The submitted form
     * @return array{fields: array<string, mixed>, errors: array<string, string[]>}
     */
    public function checkCategory(array $current, array $input, int $actorId): array
    {
        $errors = [];
        $text = static fn (string $key): string => trim(is_scalar($input[$key] ?? null) ? (string) $input[$key] : '');

        $label = $text('label');
        $description = $text('description');
        $severity = $text('severity');
        $action = $text('auto_action');
        $execution = $text('execution');

        if ($label === '' || mb_strlen($label) > self::LABEL_MAX) {
            $errors['label'][] = 'Give the reason a name of 1 to '.self::LABEL_MAX.' characters.';
        }

        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors['description'][] = 'Keep the description to '.self::DESCRIPTION_MAX.' characters.';
        }

        if (!in_array($severity, ModerationCategoryModel::SEVERITIES, true)) {
            $errors['severity'][] = 'Choose a severity from the list.';
        }

        if (!in_array($action, ModerationCategoryModel::ACTIONS, true)) {
            $errors['auto_action'][] = 'Choose what happens from the list.';
        }

        if (!in_array($execution, ModerationCategoryModel::EXECUTIONS, true)) {
            $errors['execution'][] = 'Choose whether the rule acts on its own or asks a moderator.';
        }

        $threshold = null;
        $hours = null;

        if ($action === 'none') {
            $execution = 'confirm';
        } elseif (!isset($errors['auto_action'])) {
            $threshold = $this->wholeNumber($text('threshold'), 1, self::THRESHOLD_MAX, 'threshold', 'The number of reports', $errors);

            if ($action === 'suspend') {
                $hours = $this->wholeNumber($text('suspension_hours'), 1, self::SUSPENSION_HOURS_MAX, 'suspension_hours', 'The suspension length', $errors);
            }

            // Escalating only ever hands the case to a person
            if ($action === 'escalate') {
                $execution = 'confirm';
            }
        }

        if ($execution === 'automatic') {
            if ($threshold !== null && $threshold < 2) {
                $errors['threshold'][] = 'A rule that acts on its own needs at least 2 reports, so one person can never set it off.';
            }

            if ($severity === 'critical') {
                $errors['execution'][] = 'Critical reasons always go to a person. Choose "Ask a moderator".';
            }
        }

        $active = (string) ($input['is_active'] ?? '') === '1';

        if (!$active && (int) $current['is_active'] === 1 && $this->categories->activeCount() <= 1) {
            $errors['is_active'][] = 'Readers need at least one reason to choose. Turn another reason on before turning this one off.';
        }

        $fields = [
            'label' => $label,
            'description' => $description === '' ? null : $description,
            'severity' => $severity,
            'auto_action' => $action,
            'threshold' => $threshold,
            'suspension_hours' => $hours,
            'execution' => $execution,
            'is_active' => $active ? 1 : 0,
        ];

        $fields += $this->acknowledgement($current, $fields, (string) ($input['acknowledge'] ?? '') === '1', $actorId, $errors);

        return ['fields' => $fields, 'errors' => $errors];
    }

    /**
     * Save a checked category and audit what changed.
     *
     * @param  array<string, mixed>  $current  The category as stored
     * @param  array<string, mixed>  $fields  From checkCategory(), with no errors
     * @return array<string, array{from: mixed, to: mixed}> What changed; empty when nothing did
     */
    public function saveCategory(array $current, array $fields, int $actorId, ?string $ip): array
    {
        $changes = self::changes($current, $fields);

        if ($changes === []) {
            return [];
        }

        $this->database->transaction(function () use ($current, $fields, $changes, $actorId, $ip): void {
            $this->categories->updateSettings((string) $current['slug'], $fields);
            $this->audit->log($actorId, 'moderation.category_updated', 'moderation_category', null, [
                'slug' => $current['slug'],
                'changes' => $changes,
            ], $ip);
        });

        return $changes;
    }

    /**
     * Check the safeguards form.
     *
     * @param  array<string, mixed>  $input  Keyed by setting name without the 'moderation.' prefix
     * @return array{values: array<string, int>, errors: array<string, string[]>}
     */
    public function checkSafeguards(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (ModerationSettings::KEYS as $name => [, $floor, $ceiling]) {
            $field = self::field($name);
            $raw = trim(is_scalar($input[$field] ?? null) ? (string) $input[$field] : '');
            $value = $this->wholeNumber($raw, $floor, $ceiling, $field, self::SAFEGUARD_LABELS[$name], $errors);

            if ($value !== null) {
                $values[$name] = $value;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Save checked safeguards and audit what changed.
     *
     * @param  array<string, int>  $values  From checkSafeguards(), with no errors
     * @return array<string, array{from: mixed, to: mixed}> What changed; empty when nothing did
     */
    public function saveSafeguards(array $values, int $actorId, ?string $ip): array
    {
        $current = $this->settings->storedValues();
        $changes = [];

        foreach ($values as $name => $value) {
            if ($current[$name] !== (string) $value) {
                $changes[$name] = ['from' => $current[$name], 'to' => $value];
            }
        }

        if ($changes === []) {
            return [];
        }

        $this->database->transaction(function () use ($changes, $actorId, $ip): void {
            foreach ($changes as $name => $change) {
                $this->settings->save($name, $change['to']);
            }

            $this->audit->log($actorId, 'moderation.settings_updated', 'setting', null, ['changes' => $changes], $ip);
        });

        return $changes;
    }

    /**
     * The form field for a safeguard setting.
     */
    public static function field(string $settingName): string
    {
        return substr($settingName, strlen('moderation.'));
    }

    /**
     * A high severity reason that hides or suspends on its own needs an
     * administrator to accept that, on record. The acceptance lasts until the
     * rule itself changes, so an edited rule has to be accepted again.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $fields
     * @param  array<string, string[]>  $errors
     * @return array{automation_acknowledged_by: int|null, automation_acknowledged_at: string|null}
     */
    private function acknowledgement(array $current, array $fields, bool $ticked, int $actorId, array &$errors): array
    {
        $needed = $fields['severity'] === 'high'
            && $fields['execution'] === 'automatic'
            && in_array($fields['auto_action'], ['hide_content', 'suspend'], true);

        if (!$needed) {
            return ['automation_acknowledged_by' => null, 'automation_acknowledged_at' => null];
        }

        $ruleUnchanged = self::changes($current, array_intersect_key(
            $fields,
            array_flip(['severity', 'auto_action', 'threshold', 'suspension_hours', 'execution'])
        )) === [];

        if ($current['automation_acknowledged_at'] !== null && $ruleUnchanged) {
            return [
                'automation_acknowledged_by' => $current['automation_acknowledged_by'] === null ? null : (int) $current['automation_acknowledged_by'],
                'automation_acknowledged_at' => (string) $current['automation_acknowledged_at'],
            ];
        }

        if (!$ticked) {
            $errors['acknowledge'][] = 'Tick the box to confirm that this rule may act against people with no moderator looking first.';
        }

        return ['automation_acknowledged_by' => $actorId, 'automation_acknowledged_at' => gmdate('Y-m-d H:i:s')];
    }

    /**
     * Parse a whole number within bounds, recording an error when it is not one.
     *
     * @param  array<string, string[]>  $errors
     */
    private function wholeNumber(string $raw, int $min, int $max, string $field, string $subject, array &$errors): ?int
    {
        if ($raw === '' || !ctype_digit($raw) || (int) $raw < $min || (int) $raw > $max) {
            $errors[$field][] = "{$subject} must be a whole number from {$min} to {$max}.";

            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private static function changes(array $current, array $fields): array
    {
        $changes = [];

        foreach ($fields as $column => $value) {
            $before = $current[$column] ?? null;

            // Stored columns and form values differ in type (3 against "3"), so compare as text
            if (($before === null) !== ($value === null) || (string) $before !== (string) $value) {
                $changes[$column] = ['from' => $before, 'to' => $value];
            }
        }

        return $changes;
    }
}
