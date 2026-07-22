{% extends "back.lex.php" %}

{% block title %}<?= $task === null ? 'New Task' : 'Edit Task' ?>{% endblock %}
{% block subtitle %}Pick a command and how often it should run. Everything here is a list or a bounded number, so there is nothing to mistype.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/scheduled-tasks';
$action = $task === null ? $basePath.'/store' : $basePath.'/'.(int) $task['id'].'/update';

$value = static function (string $key, $fallback = '') use ($task) {
    return $task === null ? $fallback : ($task[$key] ?? $fallback);
};

$scheduleType = (string) $value('schedule_type', 'every_minute');
$selectedCommand = (string) $value('command', '');
$selectedZone = (string) $value('schedule_timezone', $viewerTimezone);

$typeLabels = [
    'every_minute' => 'Every minute',
    'every_n_minutes' => 'Every N minutes',
    'hourly' => 'Hourly, at a set minute',
    'daily' => 'Daily, at a set time',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <form method="POST" action="<?= e(lurl($action)) ?>">
        {{ csrf_field() }}

        <div class="card">
            <div class="card-body space-y-5">

                <div>
                    <label for="label" class="inline-block mb-2 text-base font-medium">Name</label>
                    <input type="text" id="label" name="label" required maxlength="100"
                           value="<?= e((string) $value('label')) ?>"
                           placeholder="Subscriber mail"
                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">What this task is called in the list.</p>
                </div>

                <div>
                    <label for="command" class="inline-block mb-2 text-base font-medium">Command</label>
                    <select id="command" name="command" required
                            class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                        <option value="">Choose a command</option>
                        <?php foreach ($commandOptions as $name => $label) { ?>
                        <option value="<?= e($name) ?>" <?= $selectedCommand === $name ? 'selected' : '' ?>>
                            <?= e($label) ?> (<?= e($name) ?>)
                        </option>
                        <?php } ?>
                    </select>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                        Only commands built to be scheduled appear here.
                    </p>
                </div>

                <!-- Filled in from the chosen command declared arguments -->
                <div id="argument-fields" class="space-y-4"></div>

                <div>
                    <label for="schedule_type" class="inline-block mb-2 text-base font-medium">How often</label>
                    <select id="schedule_type" name="schedule_type" required
                            class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                        <?php foreach ($scheduleTypes as $type) { ?>
                        <option value="<?= e($type) ?>" <?= $scheduleType === $type ? 'selected' : '' ?>>
                            <?= e($typeLabels[$type] ?? $type) ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div data-schedule-part="every_n_minutes" class="hidden">
                    <label for="interval_minutes" class="inline-block mb-2 text-base font-medium">Interval, in minutes</label>
                    <input type="number" id="interval_minutes" name="interval_minutes" min="1" max="1440"
                           value="<?= e((string) $value('interval_minutes', '10')) ?>"
                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                </div>

                <div data-schedule-part="hourly" class="hidden">
                    <label for="minute_of_hour" class="inline-block mb-2 text-base font-medium">Minute of the hour</label>
                    <input type="number" id="minute_of_hour" name="minute_of_hour" min="0" max="59"
                           value="<?= e((string) $value('minute_of_hour', '0')) ?>"
                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                </div>

                <div data-schedule-part="daily" class="hidden">
                    <label for="run_at" class="inline-block mb-2 text-base font-medium">Time of day</label>
                    <input type="time" id="run_at" name="run_at"
                           value="<?= e(substr((string) $value('run_at', '03:00:00'), 0, 5)) ?>"
                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                </div>

                <div data-schedule-part="daily hourly" class="hidden">
                    <label for="schedule_timezone" class="inline-block mb-2 text-base font-medium">Timezone</label>
                    <select id="schedule_timezone" name="schedule_timezone"
                            class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                        <?php foreach ($timezones as $zone) { ?>
                        <option value="<?= e($zone) ?>" <?= $selectedZone === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                        <?php } ?>
                    </select>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                        The time above is read in this zone, so it stays put when the clocks change.
                    </p>
                </div>

                <!-- What the chosen pace actually adds up to -->
                <div id="rate-hint" class="hidden p-3 rounded-md bg-sky-50 border border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/30 text-sm text-sky-800 dark:text-sky-200"></div>

                <div>
                    <label for="timeout_seconds" class="inline-block mb-2 text-base font-medium">Timeout, in seconds</label>
                    <input type="number" id="timeout_seconds" name="timeout_seconds" min="10" max="86400" required
                           value="<?= e((string) $value('timeout_seconds', '300')) ?>"
                           class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                        A task still going past this is stopped and recorded as timed out. Give long jobs more room.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           <?= $task === null || !empty($task['is_active']) ? 'checked' : '' ?>
                           class="size-4 rounded border-slate-200 dark:border-zink-500">
                    <label for="is_active" class="text-base font-medium">Switched on</label>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="submit" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600">
                        <?= $task === null ? 'Create task' : 'Save task' ?>
                    </button>
                    <a href="<?= e(lurl($basePath)) ?>" class="btn bg-slate-200 border-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-zink-600 dark:border-zink-600 dark:text-zink-100">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    var schemas = <?= json_encode($schemas) ?>;
    var saved = <?= json_encode((object) $arguments) ?>;
    var hintEndpoint = <?= json_encode(lurl($basePath.'/hint')) ?>;

    var commandField = document.getElementById('command');
    var typeField = document.getElementById('schedule_type');
    var intervalField = document.getElementById('interval_minutes');
    var argumentBox = document.getElementById('argument-fields');
    var hintBox = document.getElementById('rate-hint');
    var hintTimer = null;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));

        return div.innerHTML;
    }

    // Each declared argument becomes a control of its own, so the form never
    // needs a free text box for something that reaches a command.
    function renderArguments() {
        var schema = schemas[commandField.value] || {};
        var html = '';

        Object.keys(schema).forEach(function (name) {
            var rule = schema[name];
            var current = Object.prototype.hasOwnProperty.call(saved, name) ? saved[name] : rule.default;
            var id = 'argument-' + name;

            html += '<div>';
            html += '<label for="' + escapeHtml(id) + '" class="inline-block mb-2 text-base font-medium">' + escapeHtml(rule.label || name) + '</label>';

            if (rule.type === 'enum') {
                html += '<select id="' + escapeHtml(id) + '" name="arguments[' + escapeHtml(name) + ']" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">';
                (rule.values || []).forEach(function (option) {
                    var chosen = String(current) === String(option) ? ' selected' : '';
                    html += '<option value="' + escapeHtml(String(option)) + '"' + chosen + '>' + escapeHtml(String(option)) + '</option>';
                });
                html += '</select>';
            } else if (rule.type === 'int') {
                html += '<input type="number" id="' + escapeHtml(id) + '" name="arguments[' + escapeHtml(name) + ']"';
                if (rule.min !== undefined) { html += ' min="' + Number(rule.min) + '"'; }
                if (rule.max !== undefined) { html += ' max="' + Number(rule.max) + '"'; }
                html += ' value="' + escapeHtml(String(current === undefined ? '' : current)) + '" class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">';
            } else if (rule.type === 'bool') {
                html += '<input type="checkbox" id="' + escapeHtml(id) + '" name="arguments[' + escapeHtml(name) + ']" value="1"' + (current ? ' checked' : '') + ' class="size-4 rounded border-slate-200 dark:border-zink-500">';
            }

            html += '</div>';
        });

        argumentBox.innerHTML = html;
    }

    function showScheduleParts() {
        var chosen = typeField.value;

        document.querySelectorAll('[data-schedule-part]').forEach(function (part) {
            var applies = part.getAttribute('data-schedule-part').split(' ').indexOf(chosen) !== -1;
            part.classList.toggle('hidden', !applies);
        });
    }

    function refreshHint() {
        if (!commandField.value) {
            hintBox.classList.add('hidden');

            return;
        }

        var params = new URLSearchParams({
            command: commandField.value,
            schedule_type: typeField.value,
            interval_minutes: intervalField ? intervalField.value : '5'
        });

        fetch(hintEndpoint + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (payload) {
                if (!payload || !payload.hint) {
                    hintBox.classList.add('hidden');

                    return;
                }

                hintBox.textContent = payload.hint;
                hintBox.classList.remove('hidden');
            })
            .catch(function () { hintBox.classList.add('hidden'); });
    }

    function queueHint() {
        clearTimeout(hintTimer);
        hintTimer = setTimeout(refreshHint, 250);
    }

    commandField.addEventListener('change', function () {
        renderArguments();
        queueHint();
    });
    typeField.addEventListener('change', function () {
        showScheduleParts();
        queueHint();
    });

    if (intervalField) {
        intervalField.addEventListener('input', queueHint);
    }

    renderArguments();
    showScheduleParts();
    refreshHint();
})();
</script>
{% endblock %}
