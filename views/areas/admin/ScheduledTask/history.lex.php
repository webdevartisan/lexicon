{% extends "back.lex.php" %}

{% block title %}Run History{% endblock %}
{% block subtitle %}Every time this task ran, what it printed, and how long it took.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/scheduled-tasks';

try {
    $viewerZone = new DateTimeZone($viewerTimezone);
} catch (Exception $e) {
    $viewerZone = new DateTimeZone('UTC');
}

$local = static function (?string $utc) use ($viewerZone): string {
    if (empty($utc)) {
        return '&mdash;';
    }

    return e((new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone($viewerZone)->format('d M Y, H:i:s'));
};

$statusStyles = [
    'running' => ['Running', 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300'],
    'success' => ['Succeeded', 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-300'],
    'failed' => ['Failed', 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300'],
    'timed_out' => ['Timed out', 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300'],
    'skipped_locked' => ['Skipped', 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200'],
];

$triggerLabels = [
    'cron' => 'Cron',
    'manual' => 'Run now',
    'pseudo' => 'Page visit',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="card mb-4">
        <div class="card-body flex flex-wrap items-center justify-between gap-3">
            <div>
                <h6 class="text-15"><?= e($task['label']) ?></h6>
                <code class="text-xs text-slate-500 dark:text-zink-300"><?= e($task['command']) ?></code>
            </div>
            <a href="<?= e(lurl($basePath)) ?>" class="btn bg-slate-200 border-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-zink-600 dark:border-zink-600 dark:text-zink-100">
                Back to tasks
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($runs)) { ?>
            <p class="text-slate-500 dark:text-zink-300 py-6 text-center">This task has not run yet.</p>
            <?php } else { ?>
            <div class="space-y-3">
                <?php foreach ($runs as $run) {
                    $style = $statusStyles[$run['status']] ?? ['Unknown', 'bg-slate-100 text-slate-600'];
                    $output = (string) ($run['output'] ?? '');
                    ?>
                <details class="rounded-md border border-slate-200 dark:border-zink-500">
                    <summary class="flex flex-wrap items-center gap-3 p-3 cursor-pointer">
                        <span class="px-2 py-0.5 rounded text-xs font-medium <?= e($style[1]) ?>"><?= e($style[0]) ?></span>
                        <span class="text-sm text-slate-700 dark:text-zink-100"><?= $local($run['started_at']) ?></span>
                        <span class="text-xs text-slate-500 dark:text-zink-300">
                            <?= e($triggerLabels[$run['trigger_source']] ?? $run['trigger_source']) ?>
                        </span>
                        <?php if ($run['duration_ms'] !== null) { ?>
                        <span class="text-xs text-slate-500 dark:text-zink-300"><?= e(number_format((int) $run['duration_ms'])) ?> ms</span>
                        <?php } ?>
                        <?php if ($run['exit_code'] !== null) { ?>
                        <span class="text-xs text-slate-400">exit <?= (int) $run['exit_code'] ?></span>
                        <?php } ?>
                    </summary>
                    <div class="px-3 pb-3">
                        <?php if ($output === '') { ?>
                        <p class="text-sm text-slate-500 dark:text-zink-300">No output.</p>
                        <?php } else { ?>
                        <!-- Command output is untrusted text and routinely carries
                             exception messages with user data in them -->
                        <pre class="p-3 rounded bg-slate-900 text-slate-100 text-xs overflow-x-auto whitespace-pre-wrap"><?= e($output) ?></pre>
                        <?php } ?>
                    </div>
                </details>
                <?php } ?>
            </div>

            <?php if ($pagination['total_pages'] > 1) { ?>
            <div class="flex items-center justify-between mt-4 text-sm">
                <span class="text-slate-500 dark:text-zink-300">
                    Page <?= (int) $pagination['current_page'] ?> of <?= (int) $pagination['total_pages'] ?>
                </span>
                <div class="flex gap-2">
                    <?php if ($pagination['has_previous']) { ?>
                    <a href="<?= e(lurl($basePath.'/'.(int) $task['id'].'/history?page='.((int) $pagination['current_page'] - 1))) ?>"
                       class="btn bg-slate-200 border-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-zink-600 dark:border-zink-600 dark:text-zink-100">Previous</a>
                    <?php } ?>
                    <?php if ($pagination['has_next']) { ?>
                    <a href="<?= e(lurl($basePath.'/'.(int) $task['id'].'/history?page='.((int) $pagination['current_page'] + 1))) ?>"
                       class="btn bg-slate-200 border-slate-200 text-slate-700 hover:bg-slate-300 dark:bg-zink-600 dark:border-zink-600 dark:text-zink-100">Next</a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
