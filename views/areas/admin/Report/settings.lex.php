{% extends "back.lex.php" %}

{% block title %}Moderation settings{% endblock %}
{% block subtitle %}What each report reason does once enough people agree, and the safeguards in front of every automatic action.{% endblock %}

{% block body %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<?php
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$small = 'text-xs text-slate-400 dark:text-zink-400';
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 w-full';
$checkClass = 'form-checkbox rounded border-slate-200 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
$labelClass = 'inline-block mb-1 text-sm font-medium';

$severityPill = [
    'low' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'medium' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
    'high' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
    'critical' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
];

$duration = static function (int $hours): string {
    if ($hours % 24 === 0) {
        $days = intdiv($hours, 24);

        return $days.' day'.($days === 1 ? '' : 's');
    }

    return $hours.' hour'.($hours === 1 ? '' : 's');
};

$ruleText = static function (array $category) use ($duration): string {
    $after = ' after '.(int) $category['threshold'].' report'.((int) $category['threshold'] === 1 ? '' : 's');

    return match ($category['auto_action']) {
        'hide_content' => 'Hide the item'.$after,
        'suspend' => 'Suspend the author for '.$duration((int) $category['suspension_hours']).$after,
        'escalate' => 'Hand the case to a person'.$after,
        default => 'No rule. Reports wait in the queue.',
    };
};

// Which reason's form came back with errors, so only that modal reopens with what was typed
$reopened = (string) old('_category', '');
$field = static fn (string $slug, string $key, mixed $stored): string => $reopened === $slug
    ? (string) old($key, '')
    : (string) ($stored ?? '');

$safeguardHints = [
    'moderation.min_reporter_age_days' => 'Newer accounts can still report, but their reports do not count toward a rule.',
    'moderation.unfounded_limit' => 'Someone with this many reports marked unfounded inside the window below is not counted toward a rule.',
    'moderation.unfounded_window_days' => 'How far back unfounded reports are counted.',
    'moderation.burst_window_minutes' => 'When all the counted reports arrive inside this window, a moderator is asked first. It catches coordinated reporting.',
    'moderation.auto_suspension_cooldown_days' => 'After a rule suspends someone, no rule may suspend them again on its own for this long.',
    'moderation.auto_warn_reporters' => 'When someone reaches the unfounded limit, send them the standard warning once. Pausing their reports always stays with a moderator.',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/_errors.lex.php" %}

    <div class="mb-4">
        <a href="/admin/reports" class="text-sm text-custom-500 hover:text-custom-600">Back to the moderation queue</a>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="text-base font-semibold mb-1">Report reasons</h2>
            <p class="<?= $muted ?> mb-4">
                The reasons readers choose from. A rule only acts on its own when it is set to, and every safeguard below passes;
                otherwise it proposes the action to a moderator. Critical reasons always go to a person.
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left <?= $small ?> uppercase">
                        <tr class="border-b border-slate-200 dark:border-zink-500">
                            <th scope="col" class="py-2 pr-4 font-medium">Reason</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Severity</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Rule</th>
                            <th scope="col" class="py-2 pr-4 font-medium">Acts</th>
                            <th scope="col" class="py-2 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                        <?php foreach ($categories as $category) { ?>
                        <tr>
                            <td class="py-3 pr-4 align-top">
                                <span class="font-medium"><?= e((string) $category['label']) ?></span>
                                <?php if ((int) $category['is_active'] !== 1) { ?>
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $severityPill['low'] ?>">Turned off</span>
                                <?php } ?>
                                <?php if (!empty($category['description'])) { ?>
                                <p class="<?= $small ?> mt-0.5"><?= e((string) $category['description']) ?></p>
                                <?php } ?>
                            </td>
                            <td class="py-3 pr-4 align-top">
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $severityPill[$category['severity']] ?? $severityPill['low'] ?>">
                                    <?= e(ucfirst((string) $category['severity'])) ?>
                                </span>
                            </td>
                            <td class="py-3 pr-4 align-top"><?= e($ruleText($category)) ?></td>
                            <td class="py-3 pr-4 align-top">
                                <?php if ($category['auto_action'] === 'none') { ?>
                                    <span class="<?= $small ?>">Not applicable</span>
                                <?php } elseif ($category['execution'] === 'automatic') { ?>
                                    On its own
                                    <?php if ($category['automation_acknowledged_at'] !== null) { ?>
                                    <p class="<?= $small ?>">Accepted <?= e(local_datetime((string) $category['automation_acknowledged_at'], 'M j, Y')) ?></p>
                                    <?php } ?>
                                <?php } else { ?>
                                    Asks a moderator
                                <?php } ?>
                            </td>
                            <td class="py-3 align-top text-right">
                                <button type="button" data-modal-target="reason-<?= e((string) $category['slug']) ?>"
                                    class="text-sm font-medium text-custom-500 hover:text-custom-600">
                                    Edit<span class="sr-only"> <?= e((string) $category['label']) ?></span>
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="text-base font-semibold mb-1">Safeguards</h2>
            <p class="<?= $muted ?> mb-4">These apply to every reason. When one of them stops a rule, the rule asks a moderator instead, and the case says why.</p>

            <form method="post" action="/admin/reports/settings/safeguards" class="grid grid-cols-1 lg:grid-cols-2 gap-4 max-w-4xl">
                <?= csrf_field() ?>
                <?php foreach (\App\Services\ModerationConfigService::SAFEGUARD_LABELS as $name => $label) {
                    $hint = $safeguardHints[$name];
                    $key = substr($name, strlen('moderation.'));
                    [, $min, $max] = $bounds[$name];
                    $value = old('_category', '') === '' && old($key) !== null ? (string) old($key) : (string) $safeguards[$name];
                    $invalid = !empty(errors()[$key]);
                    ?>
                <div>
                    <label for="safeguard-<?= e($key) ?>" class="<?= $labelClass ?>"><?= e($label) ?></label>
                    <?php if ((int) $max === 1) { ?>
                    <select id="safeguard-<?= e($key) ?>" name="<?= e($key) ?>" aria-describedby="safeguard-<?= e($key) ?>-hint"
                        class="<?= $fieldClass ?> <?= $invalid ? '!border-red-500' : '' ?>">
                        <option value="1"<?= $value === '1' ? ' selected' : '' ?>>On</option>
                        <option value="0"<?= $value === '0' ? ' selected' : '' ?>>Off</option>
                    </select>
                    <p id="safeguard-<?= e($key) ?>-hint" class="<?= $small ?> mt-1"><?= e($hint) ?></p>
                    <?php } else { ?>
                    <input type="number" id="safeguard-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value) ?>"
                        min="<?= (int) $min ?>" max="<?= (int) $max ?>" step="1" required
                        aria-describedby="safeguard-<?= e($key) ?>-hint"
                        class="<?= $fieldClass ?> <?= $invalid ? '!border-red-500' : '' ?>">
                    <p id="safeguard-<?= e($key) ?>-hint" class="<?= $small ?> mt-1"><?= e($hint) ?> From <?= (int) $min ?> to <?= (int) $max ?>.</p>
                    <?php } ?>
                    <?php if ($invalid) { ?>
                    <p class="text-xs text-red-600 dark:text-red-400 mt-1"><?= e(implode(' ', (array) errors()[$key])) ?></p>
                    <?php } ?>
                </div>
                <?php } ?>
                <div class="lg:col-span-2">
                    {% cmp="btn" type="submit" variant="blue" icon="save" label="Save safeguards" %}
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($categories as $category) {
    $slug = (string) $category['slug'];
    $id = 'reason-'.$slug;
    $isReopened = $reopened === $slug;
    $action = $field($slug, 'auto_action', $category['auto_action']);
    $severity = $field($slug, 'severity', $category['severity']);
    $execution = $field($slug, 'execution', $category['execution']);
    $active = $isReopened ? old('is_active', '') === '1' : (int) $category['is_active'] === 1;
    $hours = $isReopened ? (string) old('suspension_hours', '') : (string) ($category['suspension_hours'] ?? '168');
    $errorsFor = $isReopened ? errors() : [];
    $fieldError = static fn (string $key): string => empty($errorsFor[$key])
        ? ''
        : '<p class="text-xs text-red-600 dark:text-red-400 mt-1">'.e(implode(' ', (array) $errorsFor[$key])).'</p>';

    ob_start(); ?>
<form method="post" action="/admin/reports/settings/reasons/<?= e($slug) ?>" id="<?= e($id) ?>-form" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_category" value="<?= e($slug) ?>">
    <?php if ($errorsFor !== []) { ?>
    <p role="alert" class="px-4 py-3 text-sm text-red-600 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300">Nothing was saved. Fix the fields marked below.</p>
    <?php } ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="<?= e($id) ?>-label" class="<?= $labelClass ?>">Name readers see</label>
            <input type="text" id="<?= e($id) ?>-label" name="label" maxlength="60" required value="<?= e($field($slug, 'label', $category['label'])) ?>" class="<?= $fieldClass ?>">
            <?= $fieldError('label') ?>
        </div>
        <div>
            <label for="<?= e($id) ?>-severity" class="<?= $labelClass ?>">Severity</label>
            <select id="<?= e($id) ?>-severity" name="severity" class="<?= $fieldClass ?>">
                <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical, always a person'] as $value => $text) { ?>
                <option value="<?= $value ?>" <?= $severity === $value ? 'selected' : '' ?>><?= $text ?></option>
                <?php } ?>
            </select>
            <?= $fieldError('severity') ?>
        </div>
    </div>

    <div>
        <label for="<?= e($id) ?>-description" class="<?= $labelClass ?>">Description readers see</label>
        <input type="text" id="<?= e($id) ?>-description" name="description" maxlength="255" value="<?= e($field($slug, 'description', $category['description'])) ?>" class="<?= $fieldClass ?>">
        <?= $fieldError('description') ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="<?= e($id) ?>-action" class="<?= $labelClass ?>">What happens</label>
            <select id="<?= e($id) ?>-action" name="auto_action" class="<?= $fieldClass ?>">
                <?php foreach (['none' => 'Nothing', 'hide_content' => 'Hide the item', 'suspend' => 'Suspend the author', 'escalate' => 'Hand it to a person'] as $value => $text) { ?>
                <option value="<?= $value ?>" <?= $action === $value ? 'selected' : '' ?>><?= $text ?></option>
                <?php } ?>
            </select>
            <?= $fieldError('auto_action') ?>
        </div>
        <div>
            <label for="<?= e($id) ?>-threshold" class="<?= $labelClass ?>">After this many reports</label>
            <input type="number" id="<?= e($id) ?>-threshold" name="threshold" min="1" max="1000" step="1" value="<?= e($field($slug, 'threshold', $category['threshold'])) ?>" class="<?= $fieldClass ?>">
            <?= $fieldError('threshold') ?>
        </div>
        <div>
            <label for="<?= e($id) ?>-hours" class="<?= $labelClass ?>">Suspension, in hours</label>
            <input type="number" id="<?= e($id) ?>-hours" name="suspension_hours" min="1" max="8760" step="1" value="<?= e($hours) ?>" class="<?= $fieldClass ?>" aria-describedby="<?= e($id) ?>-hours-hint">
            <p id="<?= e($id) ?>-hours-hint" class="<?= $small ?> mt-1">Only used to suspend. 168 is a week.</p>
            <?= $fieldError('suspension_hours') ?>
        </div>
    </div>

    <fieldset>
        <legend class="<?= $labelClass ?>">Who decides</legend>
        <div class="flex flex-col gap-1.5">
            <label class="flex items-start gap-2 text-sm">
                <input type="radio" name="execution" value="confirm" class="mt-0.5" <?= $execution !== 'automatic' ? 'checked' : '' ?>>
                <span>A moderator confirms the action first</span>
            </label>
            <label class="flex items-start gap-2 text-sm">
                <input type="radio" name="execution" value="automatic" class="mt-0.5" <?= $execution === 'automatic' ? 'checked' : '' ?>>
                <span>The rule acts on its own when every safeguard passes</span>
            </label>
        </div>
        <?= $fieldError('execution') ?>
    </fieldset>

    <div class="p-3 text-sm border rounded-md border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/40 dark:text-amber-200">
        <p class="font-medium mb-1">High severity rules that act on their own</p>
        <p class="mb-2">
            They hide or suspend with no moderator looking first. The person affected has to be told the decision was automatic
            (DSA Art. 17(3)(c)), and their appeal must be decided by a person (Art. 20(6)). Only needed when this reason is high
            severity, hides or suspends, and acts on its own. Changing the rule later asks again.
        </p>
        <label class="flex items-start gap-2">
            <input type="checkbox" name="acknowledge" value="1" class="<?= $checkClass ?> mt-0.5" <?= $isReopened && old('acknowledge', '') === '1' ? 'checked' : '' ?>>
            <span>I accept that this rule may act against people on its own</span>
        </label>
        <?= $fieldError('acknowledge') ?>
    </div>

    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="<?= $checkClass ?> mt-0.5" <?= $active ? 'checked' : '' ?>>
        <span>Readers can choose this reason. Turning it off keeps the reports already filed under it.</span>
    </label>
    <?= $fieldError('is_active') ?>
</form>
<?php
    $body = ob_get_clean();
    $title = 'Edit "'.$category['label'].'"';
    $formId = $id.'-form';
    ?>
{% cmp="modal" id="{$id}" title="{$title}" icon="settings" size="lg" body="{$body}" form="{$formId}" confirmText="Save" openOnLoad="{$isReopened}" %}
<?php } ?>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}
