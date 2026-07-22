{% extends "back.lex.php" %}

{% block title %}Scheduled Tasks{% endblock %}
{% block subtitle %}Recurring jobs and when they last ran. Cron only calls one command, everything else is set up here.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/scheduled-tasks';
$newTaskUrl = lurl($basePath.'/create');

try {
    $viewerZone = new DateTimeZone($viewerTimezone);
} catch (Exception $e) {
    $viewerZone = new DateTimeZone('UTC');
}

// Stored times are UTC. Everything an operator reads is their own clock.
$local = static function (?string $utc) use ($viewerZone): string {
    if (empty($utc)) {
        return '';
    }

    $when = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

    return $when->setTimezone($viewerZone)->format('d M, H:i');
};

$relative = static function (?string $utc): string {
    if (empty($utc)) {
        return '';
    }

    $seconds = strtotime($utc.' UTC') - time();
    $ahead = $seconds > 0;
    $seconds = abs($seconds);

    if ($seconds < 60) {
        $text = $seconds.'s';
    } elseif ($seconds < 3600) {
        $text = (int) round($seconds / 60).'m';
    } elseif ($seconds < 86400) {
        $text = (int) round($seconds / 3600).'h';
    } else {
        $text = (int) round($seconds / 86400).'d';
    }

    return $ahead ? 'in '.$text : $text.' ago';
};

// Reads back the picker in the words it was set with
$describe = static function (array $task): string {
    switch ($task['schedule_type']) {
        case 'every_minute':
            return 'Every minute';
        case 'every_n_minutes':
            return 'Every '.(int) $task['interval_minutes'].' min';
        case 'hourly':
            return 'Hourly at :'.str_pad((string) (int) $task['minute_of_hour'], 2, '0', STR_PAD_LEFT);
        case 'daily':
            return 'Daily '.substr((string) $task['run_at'], 0, 5).' '.$task['schedule_timezone'];
        default:
            return 'Unknown';
    }
};

// Icon and colour per outcome, keyed so the cache entry follows the icon
$statusStyles = [
    'success' => ['check-circle', 'text-green-500', 'Succeeded'],
    'failed' => ['x-circle', 'text-red-500', 'Failed'],
    'timed_out' => ['timer-off', 'text-amber-500', 'Timed out'],
    'skipped_locked' => ['skip-forward', 'text-slate-400', 'Skipped'],
];

$heartbeatText = $heartbeatAge === null ? '' : $relative(gmdate('Y-m-d H:i:s', time() - $heartbeatAge));
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if ($heartbeatAge === null) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 text-sm border-l-4 rounded-md bg-custom-50 border-custom-500 text-custom-800 dark:bg-zink-700 dark:text-custom-400">
        {% cache 'lucide:sched:info' ttl=31536000 %}<i data-lucide="info" class="size-5 shrink-0 mt-0.5"></i>{% endcache %}
        <div>
            <p class="font-medium">The scheduler has not run yet</p>
            <p class="mt-0.5">Add this one line to your crontab and nothing here needs touching again.</p>
            <code class="block mt-2 p-2 rounded bg-slate-100 dark:bg-zink-600 text-xs break-all"><?= e($cronLine) ?></code>
        </div>
    </div>
    <?php } elseif ($heartbeatStale) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 text-sm border-l-4 rounded-md bg-red-50 border-red-500 text-red-800 dark:bg-zink-700 dark:text-red-200">
        {% cache 'lucide:sched:alert-octagon' ttl=31536000 %}<i data-lucide="alert-octagon" class="size-5 shrink-0 mt-0.5"></i>{% endcache %}
        <div>
            <p class="font-medium">The scheduler last ran <?= e($heartbeatText) ?></p>
            <p class="mt-0.5">Nothing below is running. Check that the crontab entry is still in place.</p>
            <code class="block mt-2 p-2 rounded bg-slate-100 dark:bg-zink-600 text-xs break-all"><?= e($cronLine) ?></code>
        </div>
    </div>
    <?php } ?>

    <?php if (!$runsDetached) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 text-sm border-l-4 rounded-md bg-orange-50 border-orange-500 text-orange-800 dark:bg-zink-700 dark:text-orange-200">
        {% cache 'lucide:sched:alert-triangle' ttl=31536000 %}<i data-lucide="alert-triangle" class="size-5 shrink-0 mt-0.5"></i>{% endcache %}
        <div>
            <p class="font-medium">Running tasks in the same process</p>
            <p class="mt-0.5">
                This host does not allow <code>proc_open</code>, so tasks run inside the tick that started them.
                They cannot be stopped if one hangs, and a fatal error in one will end that tick early.
            </p>
        </div>
    </div>
    <?php } ?>

    <?php if (empty($tasks)) { ?>
        {% cmp="empty-state" icon="clock" title="No tasks yet" message="Add a task to have the scheduler run it on a timetable." %}
        <div class="mt-4 text-center">
            {% cmp="btn" variant="blue" label="New task" icon="plus" href="{$newTaskUrl}" %}
        </div>
    <?php } else { ?>
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h6 class="text-15 grow">Tasks</h6>
                {% cmp="btn" variant="blue" label="New task" icon="plus" href="{$newTaskUrl}" %}
            </div>

            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="ltr:text-left rtl:text-right bg-slate-100 dark:bg-zink-600">
                        <tr>
                            <th class="px-3.5 py-2.5 font-semibold w-10"></th>
                            <th class="px-3.5 py-2.5 font-semibold">Task</th>
                            <th class="px-3.5 py-2.5 font-semibold">Schedule</th>
                            <th class="px-3.5 py-2.5 font-semibold">Next run</th>
                            <th class="px-3.5 py-2.5 font-semibold">Last run</th>
                            <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                        <?php foreach ($tasks as $task) {
                            $taskId = (int) $task['id'];
                            $isRunning = !empty($task['running_since']);
                            $isActive = !empty($task['is_active']);
                            $lastStatus = (string) ($task['last_status'] ?? '');
                            $style = $statusStyles[$lastStatus] ?? null;
                            $statusIcon = $style[0] ?? 'minus';
                            $statusColour = $style[1] ?? 'text-slate-300';
                            $statusTip = $isRunning ? 'Running' : ($style[2] ?? 'Not run yet');
                            $historyUrl = lurl($basePath.'/'.$taskId.'/history');
                            $editUrl = lurl($basePath.'/'.$taskId.'/edit');
                            $toggleIcon = $isActive ? 'toggle-right' : 'toggle-left';
                            $toggleTip = $isActive ? 'Switch off' : 'Switch on';
                            ?>
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors" data-task-row="<?= $taskId ?>">
                            <td class="px-3.5 py-2.5">
                                <span data-task-status="<?= $taskId ?>"
                                      data-tooltip data-tooltip-content="<?= e($statusTip) ?>" data-tooltip-placement="top"
                                      aria-label="<?= e($statusTip) ?>">
                                    <?php if ($isRunning) { ?>
                                        <span class="inline-block size-4 rounded-full border-2 border-custom-500 border-t-transparent animate-spin"></span>
                                    <?php } elseif (!$isActive) { ?>
                                        {% cache 'lucide:sched:ban' ttl=31536000 %}<i data-lucide="ban" class="size-4 text-slate-400"></i>{% endcache %}
                                    <?php } else { ?>
                                        {% cache 'lucide:sched-status:' . $statusIcon ttl=31536000 %}<i data-lucide="<?= e($statusIcon) ?>" class="size-4"></i>{% endcache %}
                                    <?php } ?>
                                </span>
                            </td>
                            <td class="px-3.5 py-2.5">
                                <div class="font-medium text-slate-900 dark:text-zink-50"><?= e((string) $task['label']) ?></div>
                                <code class="text-xs text-slate-500 dark:text-zink-300"><?= e((string) $task['command']) ?></code>
                            </td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e($describe($task)) ?></td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">
                                <?php if (!$isActive) { ?>
                                    <span class="text-slate-400">Switched off</span>
                                <?php } else { ?>
                                    <?= e($local($task['next_run_at'])) ?>
                                    <span class="block text-xs text-slate-400"><?= e($relative($task['next_run_at'])) ?></span>
                                <?php } ?>
                            </td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">
                                <?php if (empty($task['last_run_at'])) { ?>
                                    <span class="text-slate-400">Never</span>
                                <?php } else { ?>
                                    <?= e($relative($task['last_run_at'])) ?>
                                    <span class="block text-xs text-slate-400">
                                        <?= $task['last_duration_ms'] === null ? '' : e(number_format((int) $task['last_duration_ms']).' ms') ?>
                                    </span>
                                <?php } ?>
                            </td>
                            <td class="px-3.5 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="<?= e(lurl($basePath.'/'.$taskId.'/run')) ?>" class="m-0">
                                        {{ csrf_field() }}
                                        <button type="submit" data-task-run="<?= $taskId ?>" <?= $isRunning ? 'disabled' : '' ?>
                                                data-tooltip data-tooltip-content="Run now" data-tooltip-placement="top"
                                                aria-label="Run now"
                                                class="p-2 rounded-md text-slate-500 hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 transition-colors disabled:opacity-40">
                                            {% cache 'lucide:sched:play' ttl=31536000 %}<i data-lucide="play" class="size-4"></i>{% endcache %}
                                        </button>
                                    </form>

                                    {% cmp="icon-action" href="{$historyUrl}" icon="scroll-text" tip="Run history" %}
                                    {% cmp="icon-action" href="{$editUrl}" icon="pencil" tip="Edit task" %}

                                    <form method="POST" action="<?= e(lurl($basePath.'/'.$taskId.'/toggle')) ?>" class="m-0">
                                        {{ csrf_field() }}
                                        <button type="submit"
                                                data-tooltip data-tooltip-content="<?= e($toggleTip) ?>" data-tooltip-placement="top"
                                                aria-label="<?= e($toggleTip) ?>"
                                                class="p-2 rounded-md text-slate-500 hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 transition-colors">
                                            {% cache 'lucide:sched-toggle:' . $toggleIcon ttl=31536000 %}<i data-lucide="<?= e($toggleIcon) ?>" class="size-4"></i>{% endcache %}
                                        </button>
                                    </form>

                                    <form method="POST" action="<?= e(lurl($basePath.'/'.$taskId.'/delete')) ?>" class="m-0"
                                          onsubmit="return confirm('Delete this task and its history?');">
                                        {{ csrf_field() }}
                                        <button type="submit"
                                                data-tooltip data-tooltip-content="Delete task" data-tooltip-placement="top"
                                                aria-label="Delete task"
                                                class="p-2 rounded-md text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                                            {% cache 'lucide:sched:trash-2' ttl=31536000 %}<i data-lucide="trash-2" class="size-4"></i>{% endcache %}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<script>
(function () {
    var endpoint = <?= json_encode(lurl($basePath.'/statuses')) ?>;

    // Only poll while something is actually running, and give up after a
    // while so a tab left open overnight is not still asking every few
    // seconds in the morning.
    var deadline = Date.now() + (5 * 60 * 1000);
    var timer = null;

    function anyRunning() {
        return document.querySelector('[data-task-status] .animate-spin') !== null;
    }

    function refresh() {
        fetch(endpoint, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (payload) {
                if (!payload) {
                    return;
                }

                var stillGoing = false;

                Object.keys(payload.tasks).forEach(function (id) {
                    var state = payload.tasks[id];
                    var cell = document.querySelector('[data-task-status="' + id + '"]');
                    var button = document.querySelector('[data-task-run="' + id + '"]');

                    if (!cell) {
                        return;
                    }

                    if (state.running) {
                        stillGoing = true;

                        if (button) {
                            button.disabled = true;
                        }

                        return;
                    }

                    // A run that just finished is worth showing properly
                    // rather than guessing at the icon here.
                    if (cell.querySelector('.animate-spin')) {
                        window.location.reload();
                    }
                });

                if (!stillGoing) {
                    stop();
                }
            })
            .catch(function () { stop(); });
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        if (timer || !anyRunning()) {
            return;
        }

        timer = setInterval(function () {
            if (Date.now() > deadline) {
                stop();

                return;
            }

            refresh();
        }, 3000);
    }

    start();
})();
</script>
{% endblock %}
