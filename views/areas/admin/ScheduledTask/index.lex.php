{% extends "back.lex.php" %}

{% block title %}Scheduled Tasks{% endblock %}
{% block subtitle %}Recurring jobs and when they last ran. Cron only calls one command, everything else is set up here.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/scheduled-tasks';

try {
    $viewerZone = new DateTimeZone($viewerTimezone);
} catch (Exception $e) {
    $viewerZone = new DateTimeZone('UTC');
}

// Stored times are UTC. Everything an operator reads is their own clock.
$local = static function (?string $utc) use ($viewerZone): string {
    if (empty($utc)) {
        return '&mdash;';
    }

    $when = new DateTimeImmutable($utc, new DateTimeZone('UTC'));

    return e($when->setTimezone($viewerZone)->format('d M, H:i'));
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

$statusStyles = [
    'success' => ['check-circle', 'text-green-500', 'Succeeded'],
    'failed' => ['x-circle', 'text-red-500', 'Failed'],
    'timed_out' => ['timer-off', 'text-amber-500', 'Timed out'],
    'skipped_locked' => ['skip-forward', 'text-slate-400', 'Skipped'],
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if ($heartbeatAge === null) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 rounded-md bg-sky-50 border border-sky-200 dark:bg-sky-500/10 dark:border-sky-500/30">
        {% cache 'lucide:info:schedsetup' ttl=31536000 %}<i data-lucide="info" class="size-5 text-sky-600 dark:text-sky-300 shrink-0 mt-0.5"></i>{% endcache %}
        <div class="text-sm">
            <p class="font-medium text-sky-800 dark:text-sky-200">The scheduler has not run yet</p>
            <p class="text-sky-700 dark:text-sky-300 mt-0.5">Add this one line to your crontab and nothing here needs touching again.</p>
            <code class="block mt-2 p-2 rounded bg-sky-100 dark:bg-sky-900/40 text-xs break-all"><?= e($cronLine) ?></code>
        </div>
    </div>
    <?php } elseif ($heartbeatStale) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 rounded-md bg-red-50 border border-red-200 dark:bg-red-500/10 dark:border-red-500/30" id="heartbeat-warning">
        {% cache 'lucide:alert-octagon:schedhb' ttl=31536000 %}<i data-lucide="alert-octagon" class="size-5 text-red-600 dark:text-red-300 shrink-0 mt-0.5"></i>{% endcache %}
        <div class="text-sm">
            <p class="font-medium text-red-800 dark:text-red-200">The scheduler last ran <?= e($relative(gmdate('Y-m-d H:i:s', time() - $heartbeatAge))) ?></p>
            <p class="text-red-700 dark:text-red-300 mt-0.5">Nothing below is running. Check that the crontab entry is still in place.</p>
            <code class="block mt-2 p-2 rounded bg-red-100 dark:bg-red-900/40 text-xs break-all"><?= e($cronLine) ?></code>
        </div>
    </div>
    <?php } ?>

    <?php if (!$runsDetached) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 rounded-md bg-amber-50 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30">
        {% cache 'lucide:alert-triangle:schedinline' ttl=31536000 %}<i data-lucide="alert-triangle" class="size-5 text-amber-600 dark:text-amber-300 shrink-0 mt-0.5"></i>{% endcache %}
        <div class="text-sm">
            <p class="font-medium text-amber-800 dark:text-amber-200">Running tasks in the same process</p>
            <p class="text-amber-700 dark:text-amber-300 mt-0.5">
                This host does not allow <code>proc_open</code>, so tasks run inside the tick that started them.
                They cannot be stopped if one hangs, and a fatal error in one will end that tick early.
            </p>
        </div>
    </div>
    <?php } ?>

    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h6 class="text-15 grow">Tasks</h6>
                <a href="<?= e(lurl($basePath.'/create')) ?>" class="btn bg-custom-500 border-custom-500 text-white hover:bg-custom-600">
                    {% cache 'lucide:plus:schednew' ttl=31536000 %}<i data-lucide="plus" class="inline-block size-4 mr-1"></i>{% endcache %}
                    New task
                </a>
            </div>

            <?php if (empty($tasks)) { ?>
            <p class="text-slate-500 dark:text-zink-300 py-6 text-center">No tasks yet.</p>
            <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="ltr:text-left rtl:text-right bg-slate-100 dark:bg-zink-600">
                        <tr>
                            <th class="px-3 py-2.5 font-semibold w-10"></th>
                            <th class="px-3 py-2.5 font-semibold">Task</th>
                            <th class="px-3 py-2.5 font-semibold">Schedule</th>
                            <th class="px-3 py-2.5 font-semibold">Next run</th>
                            <th class="px-3 py-2.5 font-semibold">Last run</th>
                            <th class="px-3 py-2.5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task) {
                            $taskId = (int) $task['id'];
                            $isRunning = !empty($task['running_since']);
                            $isActive = !empty($task['is_active']);
                            $style = $statusStyles[$task['last_status']] ?? null;
                            ?>
                        <tr class="border-b border-slate-200 dark:border-zink-500" data-task-row="<?= $taskId ?>">
                            <td class="px-3 py-2.5">
                                <span data-task-status="<?= $taskId ?>" title="<?= e($isRunning ? 'Running' : ($style[2] ?? 'Not run yet')) ?>">
                                    <?php if ($isRunning) { ?>
                                        <span class="inline-block size-4 rounded-full border-2 border-custom-500 border-t-transparent animate-spin"></span>
                                    <?php } elseif (!$isActive) { ?>
                                        {% cache 'lucide:ban:schedoff' ttl=31536000 %}<i data-lucide="ban" class="size-4 text-slate-400"></i>{% endcache %}
                                    <?php } elseif ($style !== null) { ?>
                                        <i data-lucide="<?= e($style[0]) ?>" class="size-4 <?= e($style[1]) ?>"></i>
                                    <?php } else { ?>
                                        {% cache 'lucide:minus:schednever' ttl=31536000 %}<i data-lucide="minus" class="size-4 text-slate-300"></i>{% endcache %}
                                    <?php } ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="font-medium text-slate-800 dark:text-zink-50"><?= e($task['label']) ?></div>
                                <code class="text-xs text-slate-500 dark:text-zink-300"><?= e($task['command']) ?></code>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 dark:text-zink-200"><?= e($describe($task)) ?></td>
                            <td class="px-3 py-2.5">
                                <?php if (!$isActive) { ?>
                                    <span class="text-slate-400">Switched off</span>
                                <?php } else { ?>
                                    <span class="text-slate-600 dark:text-zink-200"><?= $local($task['next_run_at']) ?></span>
                                    <span class="block text-xs text-slate-400"><?= e($relative($task['next_run_at'])) ?></span>
                                <?php } ?>
                            </td>
                            <td class="px-3 py-2.5">
                                <?php if (empty($task['last_run_at'])) { ?>
                                    <span class="text-slate-400">Never</span>
                                <?php } else { ?>
                                    <span class="text-slate-600 dark:text-zink-200"><?= e($relative($task['last_run_at'])) ?></span>
                                    <span class="block text-xs text-slate-400">
                                        <?= $task['last_duration_ms'] === null ? '' : e(number_format((int) $task['last_duration_ms']).' ms') ?>
                                    </span>
                                <?php } ?>
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    <form method="POST" action="<?= e(lurl($basePath.'/'.$taskId.'/run')) ?>" class="inline">
                                        {{ csrf_field() }}
                                        <button type="submit" title="Run now" data-task-run="<?= $taskId ?>" <?= $isRunning ? 'disabled' : '' ?>
                                            class="p-1.5 rounded text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 disabled:opacity-40 disabled:cursor-not-allowed">
                                            {% cache 'lucide:play:schedrun' ttl=31536000 %}<i data-lucide="play" class="size-4"></i>{% endcache %}
                                        </button>
                                    </form>
                                    <a href="<?= e(lurl($basePath.'/'.$taskId.'/history')) ?>" title="History"
                                       class="p-1.5 rounded text-slate-500 hover:bg-slate-100 dark:hover:bg-zink-600">
                                        {% cache 'lucide:scroll-text:schedhist' ttl=31536000 %}<i data-lucide="scroll-text" class="size-4"></i>{% endcache %}
                                    </a>
                                    <a href="<?= e(lurl($basePath.'/'.$taskId.'/edit')) ?>" title="Edit"
                                       class="p-1.5 rounded text-slate-500 hover:bg-slate-100 dark:hover:bg-zink-600">
                                        {% cache 'lucide:pencil:schededit' ttl=31536000 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                                    </a>
                                    <form method="POST" action="<?= e(lurl($basePath.'/'.$taskId.'/toggle')) ?>" class="inline">
                                        {{ csrf_field() }}
                                        <button type="submit" title="<?= $isActive ? 'Switch off' : 'Switch on' ?>"
                                            class="p-1.5 rounded text-slate-500 hover:bg-slate-100 dark:hover:bg-zink-600">
                                            <i data-lucide="<?= $isActive ? 'toggle-right' : 'toggle-left' ?>" class="size-4"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= e(lurl($basePath.'/'.$taskId.'/delete')) ?>" class="inline"
                                          onsubmit="return confirm('Delete this task and its history?');">
                                        {{ csrf_field() }}
                                        <button type="submit" title="Delete"
                                            class="p-1.5 rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10">
                                            {% cache 'lucide:trash-2:scheddel' ttl=31536000 %}<i data-lucide="trash-2" class="size-4"></i>{% endcache %}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </div>
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

                        if (!cell.querySelector('.animate-spin')) {
                            cell.innerHTML = '<span class="inline-block size-4 rounded-full border-2 border-custom-500 border-t-transparent animate-spin"></span>';
                        }

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
