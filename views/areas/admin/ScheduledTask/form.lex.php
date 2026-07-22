{% extends "back.lex.php" %}

{% block title %}<?= $task === null ? 'New Task' : 'Edit Task' ?>{% endblock %}
{% block subtitle %}Pick a command and how often it should run. Everything here is a list or a bounded number, so there is nothing to mistype.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/scheduled-tasks';
$formAction = lurl($task === null ? $basePath.'/store' : $basePath.'/'.(int) $task['id'].'/update');
$cancelUrl = lurl($basePath);

$value = static function (string $key, $fallback = '') use ($task) {
    return $task === null ? $fallback : ($task[$key] ?? $fallback);
};

$labelValue = (string) $value('label');
$selectedCommand = (string) $value('command', '');
$scheduleType = (string) $value('schedule_type', 'every_minute');
$selectedZone = (string) $value('schedule_timezone', $viewerTimezone);
$intervalValue = (string) $value('interval_minutes', '10');
$minuteValue = (string) $value('minute_of_hour', '0');
$runAtValue = substr((string) $value('run_at', '03:00:00'), 0, 5);
$timeoutValue = (string) $value('timeout_seconds', '300');
$isActive = $task === null || !empty($task['is_active']);
$submitLabel = $task === null ? 'Create task' : 'Save task';

// The dropdown shows both the friendly name and the console name, since an
// operator reading a crontab or a log needs to recognise the same thing twice.
$commandChoices = ['' => 'Choose a command'];
foreach ($commandOptions as $commandName => $commandLabel) {
    $commandChoices[$commandName] = $commandLabel.' ('.$commandName.')';
}

$typeChoices = [
    'every_minute' => 'Every minute',
    'every_n_minutes' => 'Every N minutes',
    'hourly' => 'Hourly, at a set minute',
    'daily' => 'Daily, at a set time',
];

$zoneChoices = [];
foreach ($timezones as $zone) {
    $zoneChoices[$zone] = $zone;
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <form method="POST" action="<?= e($formAction) ?>">
        {{ csrf_field() }}

        <div class="card">
            <div class="card-body space-y-5">

                {% cmp="input" type="text" label="Name" name="label" value="{$labelValue}" placeholder="Subscriber mail" required="1" underlabel="What this task is called in the list." %}

                <div>
                    {% cmp="select" label="Command" name="command" options="{$commandChoices}" selectedKey="{$selectedCommand}" %}
                    <div class="mt-1 text-xs text-slate-500 dark:text-zink-200">
                        Only commands built to be scheduled appear here.
                    </div>
                </div>

                <!-- Filled in from the chosen command declared arguments -->
                <div id="argument-fields" class="space-y-4"></div>

                {% cmp="select" label="How often" name="schedule_type" options="{$typeChoices}" selectedKey="{$scheduleType}" %}

                <div data-schedule-part="every_n_minutes" class="hidden">
                    {% cmp="input" type="number" label="Interval, in minutes" name="interval_minutes" value="{$intervalValue}" min="1" max="1440" %}
                </div>

                <div data-schedule-part="hourly" class="hidden">
                    {% cmp="input" type="number" label="Minute of the hour" name="minute_of_hour" value="{$minuteValue}" min="0" max="59" %}
                </div>

                <div data-schedule-part="daily" class="hidden">
                    {% cmp="input" type="time" label="Time of day" name="run_at" value="{$runAtValue}" %}
                </div>

                <div data-schedule-part="daily hourly" class="hidden">
                    {% cmp="select" label="Timezone" name="schedule_timezone" options="{$zoneChoices}" selectedKey="{$selectedZone}" %}
                    <div class="mt-1 text-xs text-slate-500 dark:text-zink-200">
                        The time above is read in this zone, so it stays put when the clocks change.
                    </div>
                </div>

                <!-- What the chosen pace actually adds up to -->
                <div id="rate-hint" class="hidden p-3 rounded-md border bg-custom-50 border-custom-500 text-custom-800 dark:bg-zink-700 dark:text-custom-400 text-sm"></div>

                {% cmp="input" type="number" label="Timeout, in seconds" name="timeout_seconds" value="{$timeoutValue}" min="10" max="86400" required="1" underlabel="A task still going past this is stopped and recorded as timed out. Give long jobs more room." %}

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_active" name="is_active" value="1"
                           <?= $isActive ? 'checked' : '' ?>
                           class="size-4 rounded border-slate-200 dark:border-zink-500">
                    <label for="is_active" class="inline-block text-base font-medium">Switched on</label>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    {% cmp="btn" type="submit" variant="blue" label="{$submitLabel}" icon="save" %}
                    {% cmp="btn" variant="slate" label="Cancel" href="{$cancelUrl}" %}
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

    // Classes copied from the input component so generated fields match the
    // ones rendered server side.
    var fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800';
    var labelClass = 'inline-block mb-2 text-base font-medium';

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
            html += '<label for="' + escapeHtml(id) + '" class="' + labelClass + '">' + escapeHtml(rule.label || name) + '</label>';

            if (rule.type === 'enum') {
                html += '<select id="' + escapeHtml(id) + '" name="arguments[' + escapeHtml(name) + ']" class="' + fieldClass + '">';
                (rule.values || []).forEach(function (option) {
                    var chosen = String(current) === String(option) ? ' selected' : '';
                    html += '<option value="' + escapeHtml(String(option)) + '"' + chosen + '>' + escapeHtml(String(option)) + '</option>';
                });
                html += '</select>';
            } else if (rule.type === 'int') {
                html += '<input type="number" id="' + escapeHtml(id) + '" name="arguments[' + escapeHtml(name) + ']"';
                if (rule.min !== undefined) { html += ' min="' + Number(rule.min) + '"'; }
                if (rule.max !== undefined) { html += ' max="' + Number(rule.max) + '"'; }
                html += ' value="' + escapeHtml(String(current === undefined ? '' : current)) + '" class="' + fieldClass + '">';
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
