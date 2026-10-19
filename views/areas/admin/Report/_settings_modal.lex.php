<?php
// Opened from the queue's Settings button. Reasons and safeguards are two panels
// behind a left menu; every form posts to ModerationSettingsController.
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$small = 'text-xs text-slate-400 dark:text-zink-400';
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 w-full';
$checkClass = 'form-checkbox rounded border-slate-200 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
$labelClass = 'inline-block mb-1 text-sm font-medium';

$severityPill = [
    'low' => 'bg-slate-100 text-slate-700 dark:bg-zink-500 dark:text-zink-100',
    'medium' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    'high' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    'critical' => 'bg-slate-800 text-slate-100 dark:bg-zink-800 dark:text-zink-100',
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

// A failed save comes back here; reopen the dialog on the form that failed, filled in
$settingsReopened = old('_settings', '') === '1';
$reopenedReason = $settingsReopened ? (string) old('_category', '') : '';
$field = static fn (string $slug, string $key, mixed $stored): string => $reopenedReason === $slug
    ? (string) old($key, '')
    : (string) ($stored ?? '');
$startOnSafeguards = $settingsReopened && $reopenedReason === '';

$safeguardHints = [
    'moderation.min_reporter_age_days' => 'Newer accounts can still report, but their reports do not count toward a rule.',
    'moderation.unfounded_limit' => 'Someone with this many reports marked unfounded inside the window below is not counted toward a rule.',
    'moderation.unfounded_window_days' => 'How far back unfounded reports are counted.',
    'moderation.burst_window_minutes' => 'When all the counted reports arrive inside this window, a moderator is asked first. It catches coordinated reporting.',
    'moderation.auto_suspension_cooldown_days' => 'After a rule suspends someone, no rule may suspend them again on its own for this long.',
    'moderation.auto_warn_reporters' => 'When someone reaches the unfounded limit, send them the standard warning once. Pausing their reports always stays with a moderator.',
];

ob_start(); ?>
<div class="flex flex-col sm:flex-row sm:h-[34rem]" data-panel-tabs>
    <aside class="shrink-0 sm:w-52 border-b sm:border-b-0 sm:border-e border-slate-200 dark:border-zink-500 bg-slate-50/70 dark:bg-zink-700/40">
        <div class="hidden sm:flex items-center gap-2 px-4 pt-4 pb-1">
            <span class="inline-flex items-center justify-center size-7 rounded-md bg-slate-900/10 text-slate-900 dark:bg-zink-100/15 dark:text-zink-50"><i data-lucide="shield" class="size-4"></i></span>
            <span class="text-sm font-medium">Moderation</span>
        </div>
        <div class="p-2">
            <p class="hidden sm:flex items-center h-8 px-2 text-xs font-medium text-slate-500 dark:text-zink-300">Settings</p>
            <nav class="flex sm:flex-col gap-1 overflow-x-auto pe-10 sm:pe-0" aria-label="Moderation settings">
                <button type="button" data-panel-tab="reasons" class="settings-nav-item <?= $startOnSafeguards ? '' : 'active' ?>">
                    <i data-lucide="flag" class="size-4"></i> Report reasons
                </button>
                <button type="button" data-panel-tab="safeguards" class="settings-nav-item <?= $startOnSafeguards ? 'active' : '' ?>">
                    <i data-lucide="shield-check" class="size-4"></i> Safeguards
                </button>
            </nav>
        </div>
    </aside>

    <main class="min-w-0 flex-1 overflow-y-auto p-6">
        <section data-panel="reasons" <?= $startOnSafeguards ? 'hidden' : '' ?>>
          <div class="flex flex-col gap-6">
            <div>
            <h3 class="text-base font-semibold">Report reasons</h3>
            <p class="<?= $muted ?>">
                The reasons readers choose from. A rule only acts on its own when it is set to, and every safeguard passes;
                otherwise it proposes the action to a moderator. Critical reasons always go to a person.
            </p>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-zink-500 border-y border-slate-200 dark:border-zink-500">
                <?php foreach ($categories as $category) {
                    $slug = (string) $category['slug'];
                    $id = 'reason-'.$slug;
                    $isReopened = $reopenedReason === $slug;
                    $action = $field($slug, 'auto_action', $category['auto_action']);
                    $severity = $field($slug, 'severity', $category['severity']);
                    $execution = $field($slug, 'execution', $category['execution']);
                    $active = $isReopened ? old('is_active', '') === '1' : (int) $category['is_active'] === 1;
                    $hours = $isReopened ? (string) old('suspension_hours', '') : (string) ($category['suspension_hours'] ?? '168');
                    $errorsFor = $isReopened ? errors() : [];
                    $fieldError = static fn (string $key): string => empty($errorsFor[$key])
                        ? ''
                        : '<p class="text-xs text-red-600 dark:text-red-400 mt-1">'.e(implode(' ', (array) $errorsFor[$key])).'</p>'; ?>
                <details <?= $isReopened ? 'open' : '' ?>>
                    <summary class="flex items-center justify-between gap-3 py-3 cursor-pointer select-none list-none">
                        <span class="min-w-0">
                            <span class="font-medium text-sm"><?= e((string) $category['label']) ?></span>
                            <?php if ((int) $category['is_active'] !== 1) { ?>
                            <span class="pill <?= $severityPill['low'] ?> ml-1">Turned off</span>
                            <?php } ?>
                            <span class="block <?= $small ?>"><?= e($ruleText($category)) ?><?= $category['auto_action'] !== 'none' ? ($category['execution'] === 'automatic' ? ', on its own' : ', asks a moderator') : '' ?></span>
                        </span>
                        <span class="flex items-center gap-2 shrink-0">
                            <span class="pill <?= $severityPill[$category['severity']] ?? $severityPill['low'] ?>"><?= e(ucfirst((string) $category['severity'])) ?></span>
                            <i data-lucide="chevron-down" class="size-4 text-slate-400"></i>
                        </span>
                    </summary>
                    <form method="post" action="/admin/reports/settings/reasons/<?= e($slug) ?>" class="flex flex-col gap-6 pb-5 pt-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_settings" value="1">
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
                                <select id="<?= e($id) ?>-severity" name="severity" class="form-select <?= $fieldClass ?>">
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
                                <select id="<?= e($id) ?>-action" name="auto_action" class="form-select <?= $fieldClass ?>">
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

                        <hr class="border-slate-200 dark:border-zink-500">
                        <div class="flex justify-end">
                            {% cmp="btn" type="submit" variant="blue" label="Save changes" %}
                        </div>
                    </form>
                </details>
                <?php } ?>
            </div>
          </div>
        </section>

        <section data-panel="safeguards" <?= $startOnSafeguards ? '' : 'hidden' ?>>
            <form method="post" action="/admin/reports/settings/safeguards" class="flex flex-col gap-6">
                <div>
                    <h3 class="text-base font-semibold">Safeguards</h3>
                    <p class="<?= $muted ?>">These apply to every reason. When one of them stops a rule, the rule asks a moderator instead, and the case says why.</p>
                </div>
                <?= csrf_field() ?>
                <input type="hidden" name="_settings" value="1">
                <?php
                $numeric = [];
                $toggles = [];
                foreach (\App\Services\ModerationConfigService::SAFEGUARD_LABELS as $name => $label) {
                    $key = substr($name, strlen('moderation.'));
                    [, $min, $max] = $bounds[$name];
                    $item = [
                        'label' => $label,
                        'hint' => $safeguardHints[$name],
                        'key' => $key,
                        'min' => (int) $min,
                        'max' => (int) $max,
                        'value' => $startOnSafeguards && old($key) !== null ? (string) old($key) : (string) $safeguards[$name],
                        'invalid' => $startOnSafeguards && !empty(errors()[$key]),
                    ];
                    if ($item['max'] === 1) {
                        $toggles[] = $item;
                    } else {
                        $numeric[] = $item;
                    }
                } ?>
                <div class="grid gap-4 sm:grid-cols-2">
                    <?php foreach ($numeric as $item) { ?>
                    <div class="flex flex-col gap-1.5">
                        <label for="safeguard-<?= e($item['key']) ?>" class="text-sm font-medium"><?= e($item['label']) ?></label>
                        <input type="number" id="safeguard-<?= e($item['key']) ?>" name="<?= e($item['key']) ?>" value="<?= e($item['value']) ?>"
                            min="<?= $item['min'] ?>" max="<?= $item['max'] ?>" step="1" required
                            aria-describedby="safeguard-<?= e($item['key']) ?>-hint"
                            class="<?= $fieldClass ?> <?= $item['invalid'] ? '!border-red-500' : '' ?>">
                        <p id="safeguard-<?= e($item['key']) ?>-hint" class="<?= $small ?>"><?= e($item['hint']) ?> From <?= $item['min'] ?> to <?= $item['max'] ?>.</p>
                        <?php if ($item['invalid']) { ?>
                        <p class="text-xs text-red-600 dark:text-red-400"><?= e(implode(' ', (array) errors()[$item['key']])) ?></p>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>

                <?php foreach ($toggles as $item) { ?>
                <hr class="border-slate-200 dark:border-zink-500">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex flex-col gap-0.5">
                        <label for="safeguard-<?= e($item['key']) ?>" class="text-sm font-medium"><?= e($item['label']) ?></label>
                        <span id="safeguard-<?= e($item['key']) ?>-hint" class="<?= $small ?>"><?= e($item['hint']) ?></span>
                    </div>
                    <input type="hidden" name="<?= e($item['key']) ?>" value="0">
                    <input type="checkbox" role="switch" class="switch" id="safeguard-<?= e($item['key']) ?>" name="<?= e($item['key']) ?>" value="1"
                        aria-describedby="safeguard-<?= e($item['key']) ?>-hint" <?= $item['value'] === '1' ? 'checked' : '' ?>>
                </div>
                <?php } ?>

                <div class="flex justify-end">
                    {% cmp="btn" type="submit" variant="blue" label="Save changes" %}
                </div>
            </form>
        </section>
    </main>
</div>
<?php
$settingsBody = ob_get_clean();
?>
{% cmp="modal" id="moderationSettings" title="Moderation settings" size="xl" bare="1" body="{$settingsBody}" openOnLoad="{$settingsReopened}" %}
