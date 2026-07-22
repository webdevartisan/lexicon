{% extends "back.lex.php" %}

{% block title %}Run History{% endblock %}
{% block subtitle %}Every time this task ran, what it printed, and how long it took.{% endblock %}

{% block body %}
<?php
$taskId = (int) $task['id'];
$basePath = '/admin/scheduled-tasks';
$historyPath = $basePath.'/'.$taskId.'/history';
$backUrl = lurl($basePath);

try {
    $viewerZone = new DateTimeZone($viewerTimezone);
} catch (Exception $e) {
    $viewerZone = new DateTimeZone('UTC');
}

$local = static function (?string $utc) use ($viewerZone): string {
    if (empty($utc)) {
        return '';
    }

    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone($viewerZone)
        ->format('d M Y, H:i:s');
};

// Run outcomes borrow the badge palette already in the compiled stylesheet
// rather than introducing colours of their own.
$badges = [
    'running' => ['sending', 'Running'],
    'success' => ['approved', 'Succeeded'],
    'failed' => ['failed', 'Failed'],
    'timed_out' => ['pending', 'Timed out'],
    'skipped_locked' => ['inactive', 'Skipped'],
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
                <h6 class="text-15"><?= e((string) $task['label']) ?></h6>
                <code class="text-xs text-slate-500 dark:text-zink-300"><?= e((string) $task['command']) ?></code>
            </div>
            {% cmp="btn" variant="slate" label="Back to tasks" icon="arrow-left" href="{$backUrl}" %}
        </div>
    </div>

    <?php if (empty($runs)) { ?>
        {% cmp="empty-state" icon="history" title="No runs yet" message="Once this task runs, every attempt and its output shows up here." %}
    <?php } else { ?>
    <div class="card">
        <div class="card-body">
            <div class="space-y-3">
                <?php foreach ($runs as $run) {
                    $status = (string) $run['status'];
                    $badge = $badges[$status] ?? ['draft', ucfirst($status)];
                    $badgeStatus = $badge[0];
                    $badgeLabel = $badge[1];
                    $output = (string) ($run['output'] ?? '');
                    ?>
                <details class="rounded-md border border-slate-200 dark:border-zink-500">
                    <summary class="flex flex-wrap items-center gap-3 p-3 cursor-pointer">
                        {% cmp="status-badge" status="{$badgeStatus}" label="{$badgeLabel}" %}
                        <span class="text-sm text-slate-700 dark:text-zink-100"><?= e($local($run['started_at'])) ?></span>
                        <span class="text-xs text-slate-500 dark:text-zink-300">
                            <?= e($triggerLabels[$run['trigger_source']] ?? (string) $run['trigger_source']) ?>
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
                        <pre class="p-3 rounded bg-slate-100 dark:bg-zink-600 text-xs overflow-x-auto whitespace-pre-wrap"><?= e($output) ?></pre>
                        <?php } ?>
                    </div>
                </details>
                <?php } ?>
            </div>

            {% cmp="paginator" pagination="{$pagination}" basePath="{$historyPath}" pageParam="page" itemSingular="run" itemPlural="runs" %}
        </div>
    </div>
    <?php } ?>
</div>
{% endblock %}
